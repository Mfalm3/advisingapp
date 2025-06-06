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

namespace AdvisingApp\CaseManagement\Models;

use AdvisingApp\Audit\Models\Concerns\Auditable as AuditableTrait;
use AdvisingApp\CaseManagement\Cases\CaseNumber\Contracts\CaseNumberGenerator;
use AdvisingApp\CaseManagement\Enums\CaseAssignmentStatus;
use AdvisingApp\CaseManagement\Enums\CaseUpdateDirection;
use AdvisingApp\CaseManagement\Enums\SlaComplianceStatus;
use AdvisingApp\CaseManagement\Enums\SystemCaseClassification;
use AdvisingApp\CaseManagement\Exceptions\CaseNumberExceededReRollsException;
use AdvisingApp\CaseManagement\Observers\CaseObserver;
use AdvisingApp\Division\Models\Division;
use AdvisingApp\Interaction\Models\Concerns\HasManyMorphedInteractions;
use AdvisingApp\Notification\Models\Contracts\CanTriggerAutoSubscription;
use AdvisingApp\Notification\Models\Contracts\Subscribable;
use AdvisingApp\Notification\Models\EmailMessage;
use AdvisingApp\Prospect\Models\Prospect;
use AdvisingApp\StudentDataModel\Models\Concerns\BelongsToEducatable;
use AdvisingApp\StudentDataModel\Models\Contracts\Educatable;
use AdvisingApp\StudentDataModel\Models\Contracts\Identifiable;
use AdvisingApp\StudentDataModel\Models\Scopes\LicensedToEducatable;
use AdvisingApp\StudentDataModel\Models\Student;
use App\Models\BaseModel;
use App\Models\User;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

/**
 * @property-read Student|Prospect $respondent
 *
 * @mixin IdeHelperCaseModel
 */
#[ObservedBy([CaseObserver::class])]
class CaseModel extends BaseModel implements Auditable, CanTriggerAutoSubscription, Identifiable
{
    use BelongsToEducatable;
    use SoftDeletes;
    use AuditableTrait;
    use HasManyMorphedInteractions;
    use HasRelationships;

    protected $fillable = [
        'respondent_type',
        'respondent_id',
        'division_id',
        'status_id',
        'priority_id',
        'assigned_to_id',
        'close_details',
        'res_details',
        'created_by_id',
        'status_updated_at',
    ];

    protected $casts = [
        'status_updated_at' => 'immutable_datetime',
    ];

    public function getTable()
    {
        return 'cases';
    }

    public function save(array $options = [])
    {
        $attempts = 0;

        do {
            try {
                DB::beginTransaction();

                $save = parent::save($options);
            } catch (UniqueConstraintViolationException $e) {
                $attempts++;
                $save = false;

                if ($attempts < 3) {
                    $this->case_number = app(CaseNumberGenerator::class)->generate();
                }

                DB::rollBack();

                if ($attempts >= 3) {
                    throw new CaseNumberExceededReRollsException(
                        previous: $e,
                    );
                }

                continue;
            }

            DB::commit();

            break;
        } while ($attempts < 3);

        return $save;
    }

    public function identifier(): string
    {
        return $this->id;
    }

    public function getSubscribable(): ?Subscribable
    {
        return $this->respondent instanceof Subscribable ? $this->respondent : null;
    }

    /** @return MorphTo<Educatable> */
    public function respondent(): MorphTo
    {
        return $this->morphTo(
            name: 'respondent',
            type: 'respondent_type',
            id: 'respondent_id',
        );
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    /**
     * @return HasMany<CaseUpdate, $this>
     */
    public function caseUpdates(): HasMany
    {
        return $this->hasMany(CaseUpdate::class);
    }

    /**
     * @return BelongsTo<CaseStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(CaseStatus::class);
    }

    /**
     * @return BelongsTo<CasePriority, $this>
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(CasePriority::class);
    }

    /**
     * @return BelongsTo<CaseFormSubmission, $this>
     */
    public function caseFormSubmission(): BelongsTo
    {
        return $this->belongsTo(CaseFormSubmission::class, 'case_form_submission_id');
    }

    /**
     * @return HasMany<CaseAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(CaseAssignment::class);
    }

    /**
     * @return HasOne<CaseAssignment, $this>
     */
    public function assignedTo(): HasOne
    {
        return $this->hasOne(CaseAssignment::class)
            ->latest('assigned_at')
            ->where('status', CaseAssignmentStatus::Active);
    }

    /**
     * @return HasOne<CaseAssignment, $this>
     */
    public function initialAssignment(): HasOne
    {
        return $this->hasOne(CaseAssignment::class)
            ->oldest('assigned_at');
    }

    /**
     * @return HasMany<CaseHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(CaseHistory::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn(
            'status_id',
            CaseStatus::where('classification', SystemCaseClassification::Open)->pluck('id')
        );
    }

    /**
     * @return HasOne<CaseUpdate, $this>
     */
    public function latestInboundCaseUpdate(): HasOne
    {
        return $this->hasOne(CaseUpdate::class)
            ->ofMany([
                'created_at' => 'max',
            ], function (Builder $query) {
                $query
                    ->where('direction', CaseUpdateDirection::Inbound)
                    ->where('internal', false);
            });
    }

    /**
     * @return HasOne<CaseUpdate, $this>
     */
    public function latestOutboundCaseUpdate(): HasOne
    {
        return $this->hasOne(CaseUpdate::class)
            ->ofMany([
                'created_at' => 'max',
            ], function (Builder $query) {
                $query
                    ->where('direction', CaseUpdateDirection::Outbound)
                    ->where('internal', false);
            });
    }

    /**
     * @return MorphMany<EmailMessage, $this>
     */
    public function emailMessages(): MorphMany
    {
        return $this->morphMany(EmailMessage::class, 'related');
    }

    public function getLatestResponseSeconds(): int
    {
        if (! $this->latestInboundCaseUpdate) {
            return $this->created_at->diffInSeconds(now());
        }

        if (
            $this->isResolved() &&
            ($resolvedAt = $this->getResolvedAt())->isAfter($this->latestInboundCaseUpdate->created_at)
        ) {
            return $resolvedAt->diffInSeconds($this->latestInboundCaseUpdate->created_at);
        }

        if (
            $this->latestOutboundCaseUpdate &&
            $this->latestOutboundCaseUpdate->created_at->isAfter(
                $this->latestInboundCaseUpdate->created_at,
            )
        ) {
            return $this->latestOutboundCaseUpdate->created_at->diffInSeconds(
                $this->latestInboundCaseUpdate->created_at,
            );
        }

        return $this->latestInboundCaseUpdate->created_at->diffInSeconds();
    }

    public function getResolutionSeconds(): int
    {
        if (! $this->isResolved()) {
            return round($this->created_at->diffInSeconds());
        }

        return round($this->created_at->diffInSeconds($this->getResolvedAt()));
    }

    public function getSlaResponseSeconds(): ?int
    {
        return $this->priority?->sla?->response_seconds;
    }

    public function getSlaResolutionSeconds(): ?int
    {
        return $this->priority?->sla?->resolution_seconds;
    }

    public function getResponseSlaComplianceStatus(): ?SlaComplianceStatus
    {
        $slaResponseSeconds = $this->getSlaResponseSeconds();

        if (! $slaResponseSeconds) {
            return null;
        }

        $latestResponseSeconds = $this->getLatestResponseSeconds();

        return $latestResponseSeconds <= $slaResponseSeconds
            ? SlaComplianceStatus::Compliant
            : SlaComplianceStatus::NonCompliant;
    }

    public function getResolutionSlaComplianceStatus(): ?SlaComplianceStatus
    {
        $slaResolutionSeconds = $this->getSlaResolutionSeconds();

        if (! $slaResolutionSeconds) {
            return null;
        }

        $resolutionSeconds = $this->getResolutionSeconds();

        return ($resolutionSeconds <= $slaResolutionSeconds)
            ? SlaComplianceStatus::Compliant
            : SlaComplianceStatus::NonCompliant;
    }

    public function getResolvedAt(): CarbonInterface
    {
        return $this->status_updated_at ?? $this->updated_at ?? $this->created_at;
    }

    public function isResolved(): bool
    {
        return $this->status->classification === SystemCaseClassification::Closed;
    }

    /**
     * @return HasOne<CaseFeedback, $this>
     */
    public function feedback(): HasOne
    {
        return $this->hasOne(CaseFeedback::class, 'case_id');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('licensed', function (Builder $builder) {
            $builder->tap(new LicensedToEducatable('respondent'));
        });
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format(config('project.datetime_format') ?? 'Y-m-d H:i:s');
    }
}
