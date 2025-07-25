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

namespace AdvisingApp\CaseManagement\Services\CaseType;

use AdvisingApp\CaseManagement\Enums\CaseAssignmentStatus;
use AdvisingApp\CaseManagement\Enums\SystemCaseClassification;
use AdvisingApp\CaseManagement\Models\CaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class WorkloadAssigner implements CaseTypeAssigner
{
    public function execute(CaseModel $case): void
    {
        $caseType = $case->priority->type;

        $lastAssignee = $caseType->lastAssignedUser;
        $user = null;

        if ($lastAssignee) {
            $lowestCase = User::query()->whereRelation('team.manageableCaseTypes', 'case_types.id', $caseType->getKey())
                ->withCount([
                    'cases as case_count' => function (Builder $query) {
                        $query->whereRelation('status', 'classification', '!=', SystemCaseClassification::Closed);
                    },
                ])
                ->orderBy('case_count', 'asc')
                ->first()->case_count ?? 0;

            $user = User::query()->whereRelation('team.manageableCaseTypes', 'case_types.id', $caseType->getKey())
                /** @phpstan-ignore-next-line */
                ->where(function (QueryBuilder $query) {
                    $query->selectRaw('count(*)')
                        ->from('cases')
                        ->join('case_assignments', 'case_assignments.case_model_id', '=', 'cases.id')
                        ->whereColumn('users.id', 'case_assignments.user_id')
                        ->whereExists(function (QueryBuilder $query) {
                            $query->selectRaw('*')
                                ->from('case_statuses')
                                ->whereColumn('cases.status_id', 'case_statuses.id')
                                ->where('classification', '!=', SystemCaseClassification::Closed)
                                ->whereNull('case_statuses.deleted_at');
                        })
                        ->whereNull('cases.deleted_at')
                        ->whereNull('case_assignments.deleted_at');
                }, '<=', $lowestCase)
                ->where('name', '>=', $lastAssignee->name)
                ->where(fn (Builder $query) => $query
                    ->where('name', '!=', $lastAssignee->name)
                    ->orWhere('users.id', '>', $lastAssignee->getKey()))
                ->orderBy('name')->orderBy('id')->first();
        }

        if ($user === null) {
            $user = User::query()->whereRelation('team.manageableCaseTypes', 'case_types.id', $caseType->getKey())
                ->withCount([
                    'cases as case_count' => function (Builder $query) {
                        $query->whereRelation('status', 'classification', '!=', SystemCaseClassification::Closed);
                    },
                ])
                ->orderBy('case_count', 'asc')
                ->orderBy('name')->orderBy('id')->first();
        }

        if ($user !== null) {
            $caseType->last_assigned_id = $user->getKey();
            $caseType->save();
            $case->assignments()->create([
                'user_id' => $user->getKey(),
                'assigned_by_id' => null,
                'assigned_at' => now(),
                'status' => CaseAssignmentStatus::Active,
            ]);
        }
    }
}
