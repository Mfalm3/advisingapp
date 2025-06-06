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

namespace AdvisingApp\Segment\Actions;

use AdvisingApp\Segment\Filament\Resources\SegmentResource\Pages\GetSegmentQuery;
use AdvisingApp\Segment\Models\Segment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use function Livewire\trigger;

class TranslateSegmentFilters
{
    /**
     * @return Builder<Model>
     */
    public function execute(Segment | string $segment): Builder
    {
        // Create a fake Livewire component to replicate the table on the EditSegment page.
        $page = app('livewire')->new(GetSegmentQuery::class);

        if ($segment instanceof Segment) {
            $segment = $segment->getKey();
        }

        // Mount the fake Livewire component with the desired segment.
        trigger('mount', $page, [$segment], null, null);

        // Extract the filtered table query from the fake Livewire component,
        // which already respects both dynamic and static populations.
        return $page->getFilteredTableQuery();
    }

    /**
     * @param Builder<Model> $query
     *
     * @return Builder<Model>
     */
    public function applyFilterToQuery(Segment | string $segment, Builder $query): Builder
    {
        // Create a fake Livewire component to replicate the table on the EditSegment page.
        $page = app('livewire')->new(GetSegmentQuery::class);

        if ($segment instanceof Segment) {
            $segment = $segment->getKey();
        }

        // Mount the fake Livewire component with the desired segment.
        trigger('mount', $page, [$segment], null, null);

        // Extract the filtered table query from the fake Livewire component,
        // which already respects both dynamic and static populations.
        return $page->filterTableQuery($query);
    }
}
