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

namespace AdvisingApp\Campaign\Jobs;

use AdvisingApp\Campaign\Models\CampaignAction;
use AdvisingApp\Campaign\Models\Scopes\CampaignActionNotCancelled;
use AdvisingApp\Campaign\Notifications\CampaignActionFinished;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;

class ExecuteCampaignActions implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * @return array<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping(Tenant::current()->id))->dontRelease()->expireAfter(180)];
    }

    public function handle(): void
    {
        CampaignAction::query()
            ->where('execute_at', '<=', now())
            ->whereNull('execution_dispatched_at')
            ->tap(new CampaignActionNotCancelled())
            ->campaignEnabled()
            ->cursor()
            ->each(function (CampaignAction $action) {
                Bus::batch([
                    new ExecuteCampaignAction($action),
                ])
                    ->name('Execute Campaign Action: ' . $action->getKey())
                    ->allowFailures()
                    ->finally(function (Batch $batch) use ($action) {
                        if ($action->campaign->createdBy instanceof User) {
                            $action->campaign->createdBy->notify(new CampaignActionFinished($action));
                        }

                        $action->execution_finished_at = now();
                        $action->save();
                    })
                    ->dispatch();

                $action->execution_dispatched_at = now();
                $action->save();
            });
    }
}
