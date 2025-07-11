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

namespace AdvisingApp\Research\Jobs;

use AdvisingApp\Research\Models\ResearchRequest;
use AdvisingApp\Research\Notifications\ResearchTranscriptNotification;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Queue\SerializesModels;

class EmailResearchRequest implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        protected ResearchRequest $researchRequest,
        protected ?string $note,
        protected User $sender,
        protected User $recipient,
        protected bool $shouldNotify = true,
    ) {}

    /**
     * @return array<int, SkipIfBatchCancelled>
     */
    public function middleware(): array
    {
        return [new SkipIfBatchCancelled()];
    }

    public function handle(): void
    {
        $this->recipient->notifyNow(new ResearchTranscriptNotification($this->researchRequest, $this->note, $this->sender));

        if ($this->shouldNotify) {
            $recipientName = $this->sender->is($this->recipient) ? 'yourself' : $this->recipient->name;
            Notification::make()
                ->success()
                ->title("You emailed a research report to {$recipientName}.")
                ->sendToDatabase($this->sender);
        }
    }
}
