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

namespace AdvisingApp\Report\Filament\Widgets;

use AdvisingApp\Prospect\Models\Prospect;
use AdvisingApp\StudentDataModel\Models\Student;
use AdvisingApp\Task\Enums\TaskStatus;
use AdvisingApp\Task\Models\Task;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Number;

class TaskStats extends StatsOverviewReportWidget
{
    protected int | string | array $columnSpan = [
        'sm' => 2,
        'md' => 4,
        'lg' => 4,
    ];

    public function getStats(): array
    {
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();

        $shouldBypassCache = filled($startDate) || filled($endDate);

        return [
            Stat::make('Total Tasks', Number::abbreviate(
                $shouldBypassCache
                    ? Task::query()
                        ->when(
                            $startDate && $endDate,
                            fn (Builder $query): Builder => $query->whereBetween('created_at', [$startDate, $endDate])
                        )
                        ->count()
                    : Cache::tags(["{{$this->cacheTag}}"])->remember('tasks-count', now()->addHours(24), fn () => Task::query()->count()),
                maxPrecision: 2,
            )),

            Stat::make('Staff with Open Tasks', Number::abbreviate(
                $shouldBypassCache
                    ? User::query()
                        ->whereHas(
                            'assignedTasks',
                            fn ($query) => $query
                                ->whereIn('status', [TaskStatus::Pending, TaskStatus::InProgress])
                                ->when(
                                    $startDate && $endDate,
                                    fn (Builder $query): Builder => $query->whereBetween('created_at', [$startDate, $endDate])
                                )
                        )->count()
                    : Cache::tags(["{{$this->cacheTag}}"])->remember(
                        'users-with-open-tasks-count',
                        now()->addHours(24),
                        fn () => User::query()
                            ->whereHas(
                                'assignedTasks',
                                fn (Builder $query): Builder => $query->whereIn('status', [TaskStatus::Pending, TaskStatus::InProgress])
                            )->count()
                    ),
                maxPrecision: 2,
            )),

            Stat::make('Students with Open Tasks', Number::abbreviate(
                $shouldBypassCache
                    ? Student::query()
                        ->whereHas(
                            'tasks',
                            fn ($query) => $query
                                ->whereIn('status', [TaskStatus::Pending, TaskStatus::InProgress])
                                ->when(
                                    $startDate && $endDate,
                                    fn (Builder $query): Builder => $query->whereBetween('created_at', [$startDate, $endDate])
                                )
                        )->count()
                    : Cache::tags(["{{$this->cacheTag}}"])->remember(
                        'students-with-open-tasks-count',
                        now()->addHours(24),
                        fn () => Student::query()
                            ->whereHas(
                                'tasks',
                                fn (Builder $query): Builder => $query->whereIn('status', [TaskStatus::Pending, TaskStatus::InProgress])
                            )->count()
                    ),
                maxPrecision: 2,
            )),

            Stat::make('Prospects with Open Tasks', Number::abbreviate(
                $shouldBypassCache
                    ? Prospect::query()
                        ->whereHas(
                            'tasks',
                            fn ($query) => $query
                                ->whereIn('status', [TaskStatus::Pending, TaskStatus::InProgress])
                                ->when(
                                    $startDate && $endDate,
                                    fn (Builder $query): Builder => $query->whereBetween('created_at', [$startDate, $endDate])
                                )
                        )->count()
                    : Cache::tags(["{{$this->cacheTag}}"])->remember(
                        'prospects-with-open-tasks-count',
                        now()->addHours(24),
                        fn () => Prospect::query()
                            ->whereHas(
                                'tasks',
                                fn (Builder $query): Builder => $query->whereIn('status', [TaskStatus::Pending, TaskStatus::InProgress])
                            )->count()
                    ),
                maxPrecision: 2,
            )),
        ];
    }
}
