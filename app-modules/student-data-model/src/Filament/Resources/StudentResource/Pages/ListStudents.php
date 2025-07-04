<?php

/*
<COPYRIGHT>

    Copyright © 2016-2025, Canyon GBS LLC. All rights reserved.

    Advising App™ is licensed under the Elastic License 2.0. For more details,
    see https://github.com/canyongbs/advisingapp/blob/main/LICENSE.

    Notice:

    - You may not provide the software to third parties as a hosted or managed
      service, where the service provides users with access to any substantial set of
      the features or functionality of the software.
    - You may not move, change, disable, or circumvent the license key functionality
      in the software, and you may not remove or obscure any functionality in the
      software that is protected by the license key.
    - You may not alter, remove, or obscure any licensing, copyright, or other notices
      of the licensor in the software. Any use of the licensor’s trademarks is subject
      to applicable law.
    - Canyon GBS LLC respects the intellectual property rights of others and expects the
      same in return. Canyon GBS™ and Advising App™ are registered trademarks of
      Canyon GBS LLC, and we are committed to enforcing and protecting our trademarks
      vigorously.
    - The software solution, including services, infrastructure, and code, is offered as a
      Software as a Service (SaaS) by Canyon GBS LLC.
    - Use of this software implies agreement to the license terms and conditions as stated
      in the Elastic License 2.0.

    For more information or inquiries please visit our website at
    https://www.canyongbs.com or contact us via email at legal@canyongbs.com.

</COPYRIGHT>
*/

namespace AdvisingApp\StudentDataModel\Filament\Resources\StudentResource\Pages;

use AdvisingApp\Alert\Filament\Actions\BulkCreateAlertAction;
use AdvisingApp\CareTeam\Filament\Actions\AddCareTeamMemberAction;
use AdvisingApp\CaseManagement\Filament\Actions\BulkCreateCaseAction;
use AdvisingApp\Engagement\Filament\Actions\BulkEmailAction;
use AdvisingApp\Engagement\Filament\Actions\BulkTextAction;
use AdvisingApp\Interaction\Filament\Actions\BulkCreateInteractionAction;
use AdvisingApp\Notification\Filament\Actions\SubscribeBulkAction;
use AdvisingApp\Notification\Filament\Actions\SubscribeTableAction;
use AdvisingApp\Segment\Actions\BulkSegmentAction;
use AdvisingApp\Segment\Actions\TranslateSegmentFilters;
use AdvisingApp\Segment\Enums\SegmentModel;
use AdvisingApp\Segment\Models\Segment;
use AdvisingApp\StudentDataModel\Actions\DeleteStudent;
use AdvisingApp\StudentDataModel\Filament\Actions\StudentTagsBulkAction;
use AdvisingApp\StudentDataModel\Filament\Resources\StudentResource;
use AdvisingApp\StudentDataModel\Models\Student;
use App\Enums\CareTeamRoleType;
use App\Enums\TagType;
use App\Models\Tag;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make(Student::displayNameKey())
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('primaryEmailAddress.address')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('primaryPhoneNumber.number')
                    ->label('Phone')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sisid')
                    ->label('SIS ID')
                    ->searchable(),
                TextColumn::make('otherid')
                    ->label('Other ID')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('my_segments')
                    ->label('My Population Segments')
                    ->options(
                        auth()->user()->segments()
                            ->where('model', SegmentModel::Student)
                            ->pluck('name', 'id'),
                    )
                    ->searchable()
                    ->optionsLimit(20)
                    ->query(fn (Builder $query, array $data) => $this->segmentFilter($query, $data)),
                SelectFilter::make('all_segments')
                    ->label('All Population Segments')
                    ->options(
                        Segment::all()
                            ->where('model', SegmentModel::Student)
                            ->pluck('name', 'id'),
                    )
                    ->searchable()
                    ->optionsLimit(20)
                    ->query(fn (Builder $query, array $data) => $this->segmentFilter($query, $data)),
                Filter::make('subscribed')
                    ->query(fn (Builder $query): Builder => $query->whereRelation('subscriptions.user', 'id', auth()->id())),
                Filter::make('care_team')
                    ->label('Care Team')
                    ->query(
                        function (Builder $query) {
                            return $query
                                ->whereRelation('careTeam', 'user_id', '=', auth()->id())
                                ->get();
                        }
                    ),
                SelectFilter::make('alerts')
                    ->multiple()
                    ->relationship('alerts.status', 'name')
                    ->searchable()
                    ->preload()
                    ->optionsLimit(20),
                TernaryFilter::make('sap')
                    ->label('SAP'),
                TernaryFilter::make('dual'),
                TernaryFilter::make('ferpa')
                    ->label('FERPA'),
                Filter::make('holds')
                    ->form([
                        TextInput::make('hold'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['hold'],
                                fn (Builder $query, $hold): Builder => $query->where('holds', 'ilike', "%{$hold}%"),
                            );
                    }),

                SelectFilter::make('tags')
                    ->label('Tags')
                    ->options(fn (): array => Tag::query()->where('type', TagType::Student)->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->preload()
                    ->optionsLimit(20)
                    ->multiple()
                    ->query(
                        function (Builder $query, array $data) {
                            if (blank($data['values'])) {
                                return;
                            }

                            $query->whereHas('tags', function (Builder $query) use ($data) {
                                $query->whereIn('tag_id', $data['values']);
                            });
                        }
                    ),
                TernaryFilter::make('firstgen')
                    ->label('First Generation'),
            ])
            ->actions([
                ViewAction::make()
                    ->visible(function (Student $record) {
                        /** @var User $user */
                        $user = auth()->user();

                        return $user->can('product_admin.*.view');
                    }),
                SubscribeTableAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ActionGroup::make([
                        SubscribeBulkAction::make(context: 'student')->authorize(fn (): bool => auth()->user()->can('student.*.update')),
                        AddCareTeamMemberAction::make(CareTeamRoleType::Student),
                        StudentTagsBulkAction::make()->visible(fn (): bool => auth()->user()->can('student.*.update')),
                    ])->dropdown(false),
                    ActionGroup::make([
                        BulkEmailAction::make(context: 'students')->authorize(fn () => Gate::allows('update', [auth()->user(), Student::class])),
                        BulkTextAction::make(context: 'students')->authorize(fn () => Gate::allows('update', [auth()->user(), Student::class])),
                    ])->dropdown(false),
                    ActionGroup::make([
                        BulkCreateCaseAction::make()
                            ->authorize(fn () => auth()->user()->can('student.*.update')),
                        BulkCreateAlertAction::make()
                            ->visible(fn (): bool => auth()->user()->can('student.*.update')),
                        BulkCreateInteractionAction::make()
                            ->authorize(fn () => auth()->user()->can('student.*.update')),
                    ])->dropdown(false),
                    ActionGroup::make([
                        BulkSegmentAction::make(segmentModel: SegmentModel::Student),
                    ])->dropdown(false),
                    ActionGroup::make([
                        DeleteBulkAction::make()
                            ->label('Delete')
                            ->modalDescription('Are you sure you wish to delete the selected record(s)? This action cannot be reversed')
                            ->action(function (Collection $records) {
                                $deletedCount = 0;
                                $notDeleteCount = 0;

                                /** @var Collection|Student[] $records */
                                foreach ($records as $record) {
                                    /** @var Student $record */
                                    $response = Gate::inspect('delete', $record);

                                    if ($response->allowed()) {
                                        app(DeleteStudent::class)->execute($record);
                                        $deletedCount++;
                                    } else {
                                        $notDeleteCount++;
                                    }
                                }

                                $wasWere = fn ($count) => $count === 1 ? 'was' : 'were';

                                $notification = match (true) {
                                    $deletedCount === 0 => [
                                        'title' => 'None deleted',
                                        'status' => 'danger',
                                        'body' => "{$notDeleteCount} {$wasWere($notDeleteCount)} skipped because you do not have permission to delete.",
                                    ],
                                    $deletedCount > 0 && $notDeleteCount > 0 => [
                                        'title' => 'Some deleted',
                                        'status' => 'warning',
                                        'body' => "{$deletedCount} {$wasWere($deletedCount)} deleted, but {$notDeleteCount} {$wasWere($notDeleteCount)} skipped because you do not have permission to delete.",
                                    ],
                                    default => [
                                        'title' => 'Deleted',
                                        'status' => 'success',
                                        'body' => null,
                                    ],
                                };

                                Notification::make()
                                    ->title($notification['title'])
                                    ->{$notification['status']}()
                                    ->body($notification['body'])
                                    ->send();
                            }),
                    ])->dropdown(false),
                ]),
            ]);
    }

    protected function segmentFilter(Builder $query, array $data): void
    {
        if (blank($data['value'])) {
            return;
        }

        $query->whereKey(
            app(TranslateSegmentFilters::class)
                ->execute($data['value'])
                ->pluck($query->getModel()->getQualifiedKeyName()),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
