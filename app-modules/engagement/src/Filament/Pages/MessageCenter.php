<?php

namespace Assist\Engagement\Filament\Pages;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use Filament\Pages\Page;
use Assist\Task\Models\Task;
use Livewire\WithPagination;
use Filament\Actions\ViewAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Assist\Prospect\Models\Prospect;
use Illuminate\Database\Eloquent\Model;
use Assist\Engagement\Models\Engagement;
use Assist\AssistDataModel\Models\Student;
use Assist\Engagement\Models\EngagementResponse;
use Assist\ServiceManagement\Models\ServiceRequest;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Assist\AssistDataModel\Models\Contracts\Educatable;
use Assist\Timeline\Actions\AggregatesTimelineRecordsForModel;

class MessageCenter extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';

    protected static string $view = 'engagement::filament.pages.message-center';

    protected static ?string $navigationGroup = 'Productivity Tools';

    protected static ?int $navigationSort = 2;

    protected array $modelsToTimeline = [
        Engagement::class,
        EngagementResponse::class,
    ];

    public User $user;

    public bool $loadingInbox = true;

    public bool $loadingTimeline = false;

    public ?Educatable $selectedEducatable;

    public Collection $aggregateRecordsForEducatable;

    public Model $currentRecordToView;

    public string $search = '';

    // TODO students, prospects, all
    public string $peopleScope = 'all';

    public bool $filterSubscribed = true;

    public bool $filterOpenTasks = false;

    public bool $filterOpenServiceRequests = false;

    public ?string $filterStartDate = null;

    public ?string $filterEndDate = null;

    public array $paginationOptions = [
        10,
        25,
        50,
    ];

    public int $pagination = 10;

    public function mount(): void
    {
        /** @var User $user */
        $this->user = auth()->user();
    }

    public function selectEducatable(string $educatable, string $morphClass): void
    {
        $this->loadingTimeline = true;

        $this->selectedEducatable = $this->getRecordFromMorphAndKey($morphClass, $educatable);

        $this->aggregateRecordsForEducatable = resolve(AggregatesTimelineRecordsForModel::class)->handle($this->selectedEducatable, $this->modelsToTimeline);

        $this->loadingTimeline = false;
    }

    public function selectChanged($value): void
    {
        [$educatableId, $morphClass] = explode(',', $value);

        $this->selectEducatable($educatableId, $morphClass);
    }

    // TODO Extract this away... This is used in multiple places
    public function getRecordFromMorphAndKey($morphReference, $key)
    {
        $className = Relation::getMorphedModel($morphReference);

        if (is_null($className)) {
            throw new Exception("Model not found for reference: {$morphReference}");
        }

        return $className::whereKey($key)->firstOrFail();
    }

    public function viewRecord($record, $morphReference)
    {
        $this->currentRecordToView = $this->getRecordFromMorphAndKey($morphReference, $record);

        $this->mountAction('view');
    }

    public function viewAction(): ViewAction
    {
        return $this->currentRecordToView->timeline()->modalViewAction($this->currentRecordToView);
    }

    public function getLatestActivityForEducatables($ids)
    {
        $latestEngagementsForEducatables = DB::table('engagements')
            ->whereIn('recipient_id', $ids)
            ->select('recipient_id as educatable_id', DB::raw('MAX(deliver_at) as latest_deliver_at'))
            ->groupBy('educatable_id');

        $latestEngagementResponsesForEducatables = DB::table('engagement_responses')
            ->whereIn('sender_id', $ids)
            ->select('sender_id as educatable_id', DB::raw('MAX(sent_at) as latest_deliver_at'))
            ->groupBy('educatable_id');

        $combinedLatestActivity = $latestEngagementsForEducatables->unionAll($latestEngagementResponsesForEducatables);

        return DB::table(DB::raw("({$combinedLatestActivity->toSql()}) as combined"))
            ->select('educatable_id', DB::raw('MAX(latest_deliver_at) as latest_activity'))
            ->groupBy('educatable_id')
            ->mergeBindings($combinedLatestActivity);
    }

    public function applyFilters(Builder $query, string $dateColumn, string $idColumn)
    {
        $query
            ->when($this->filterStartDate, function (Builder $query) use ($dateColumn) {
                $query->where($dateColumn, '>=', Carbon::parse($this->filterStartDate));
            })
            ->when($this->filterEndDate, function (Builder $query) use ($dateColumn) {
                $query->where($dateColumn, '<=', Carbon::parse($this->filterEndDate));
            })
            ->when($this->filterSubscribed === true, function (Builder $query) use ($idColumn) {
                $query->whereIn($idColumn, $this->user->subscriptions()->pluck('subscribable_id'));
            })
            ->when($this->filterOpenTasks === true, function (Builder $query) use ($idColumn) {
                $query->whereIn(
                    $idColumn,
                    Task::query()
                        ->open()
                        ->pluck('concern_id')
                );
            })
            ->when($this->filterOpenServiceRequests === true, function (Builder $query) use ($idColumn) {
                $query->whereIn(
                    $idColumn,
                    ServiceRequest::query()
                        ->open()
                        ->pluck('respondent_id')
                );
            });
    }

    public function getEducatableIds($engagementScope, $engagementResponseScope): Collection
    {
        $engagementEducatableIds = Engagement::query()
            ->$engagementScope()
            ->tap(function (Builder $query) {
                $this->applyFilters($query, 'deliver_at', 'recipient_id');
            })
            ->pluck('recipient_id')
            ->unique();

        $engagementResponseEducatableIds = EngagementResponse::query()
            ->$engagementResponseScope()
            ->tap(function (Builder $query) {
                $this->applyFilters($query, 'sent_at', 'sender_id');
            })
            ->pluck('sender_id')
            ->unique();

        return $engagementEducatableIds->concat($engagementResponseEducatableIds)->unique();
    }

    public function getStudentIds(): Collection
    {
        return $this->getEducatableIds('sentToStudent', 'sentByStudent');
    }

    public function getProspectIds(): Collection
    {
        return $this->getEducatableIds('sentToProspect', 'sentByProspect');
    }

    protected function getViewData(): array
    {
        $this->loadingInbox = true;

        $studentIds = $this->getStudentIds();
        $prospectIds = $this->getProspectIds();

        $studentLatestActivity = $this->getLatestActivityForEducatables($studentIds);
        $prospectLatestActivity = $this->getLatestActivityForEducatables($prospectIds);

        // ray()->showQueries();
        $studentPopulationQuery = Student::query()
            ->when($this->search, function ($query, $search) {
                $query->where('full_name', 'like', "%{$search}%")
                    ->orWhere('sisid', 'like', "%{$search}%")
                    ->orWhere('otherid', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->joinSub($studentLatestActivity, 'latest_activity', function ($join) {
                $join->on('students.sisid', '=', 'latest_activity.educatable_id');
            })
            ->select('students.sisid', 'students.full_name', 'latest_activity.latest_activity', DB::raw("'student' as type"));

        $prospectPopulationQuery = Prospect::query()
            ->when($this->search, function ($query, $search) {
                $query->where('full_name', 'like', "%{$search}%");
            })
            ->joinSub($prospectLatestActivity, 'latest_activity', function ($join) {
                $join->on(DB::raw('prospects.id::VARCHAR'), '=', 'latest_activity.educatable_id');
            })
            ->select(DB::raw('prospects.id::VARCHAR'), 'prospects.full_name', 'latest_activity.latest_activity', DB::raw("'prospect' as type"));

        $educatables = $studentPopulationQuery->union($prospectPopulationQuery)
            ->orderBy('latest_activity', 'desc')
            ->paginate($this->pagination);

        foreach ($educatables as $educatable) {
            ray('educatable', $educatable);
        }

        // ray()->stopShowingQueries();

        $this->loadingInbox = false;

        return [
            'educatables' => $educatables,
        ];
    }
}
