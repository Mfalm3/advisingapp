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

namespace AdvisingApp\Engagement\Actions;

use AdvisingApp\Engagement\DataTransferObjects\EngagementCreationData;
use AdvisingApp\Engagement\Models\Engagement;
use AdvisingApp\Engagement\Notifications\EngagementNotification;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateEngagement
{
    public function execute(EngagementCreationData $data, bool $notifyNow = false): Engagement
    {
        $engagement = new Engagement();
        $engagement->user()->associate($data->user);

        throw_if(
            ! $data->recipient instanceof Model,
            new Exception('Recipient must be a single user, not a collection.')
        );

        $engagement->recipient()->associate($data->recipient);

        $engagement->channel = $data->channel;
        $engagement->subject = $data->subject;
        $engagement->scheduled_at = $data->scheduledAt;

        $engagement->recipient_route = $data->recipientRoute;

        if (! $engagement->scheduled_at) {
            $engagement->dispatched_at = now();
        }

        if ($data->campaignAction) {
            $engagement->campaignAction()->associate($data->campaignAction);
        }

        DB::transaction(function () use ($data, $engagement, $notifyNow) {
            $engagement->save();

            if ($data->campaignAction) {
                $engagement->body = $data->body;
            } else {
                [$engagement->body] = tiptap_converter()->saveImages(
                    $data->body,
                    disk: 's3-public',
                    record: $engagement,
                    recordAttribute: 'body',
                    newImages: $data->temporaryBodyImages,
                );
            }

            $engagement->save();

            if (! $engagement->scheduled_at) {
                $notification = (new EngagementNotification($engagement))->afterCommit();

                if ($notifyNow) {
                    $engagement->recipient->notifyNow($notification);
                } else {
                    $engagement->recipient->notify($notification);
                }
            }
        });

        return $engagement;
    }
}
