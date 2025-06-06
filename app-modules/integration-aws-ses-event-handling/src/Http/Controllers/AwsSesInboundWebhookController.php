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

namespace AdvisingApp\IntegrationAwsSesEventHandling\Http\Controllers;

use AdvisingApp\IntegrationAwsSesEventHandling\DataTransferObjects\SesEventData;
use AdvisingApp\IntegrationAwsSesEventHandling\Events\SesBounceEvent;
use AdvisingApp\IntegrationAwsSesEventHandling\Events\SesClickEvent;
use AdvisingApp\IntegrationAwsSesEventHandling\Events\SesComplaintEvent;
use AdvisingApp\IntegrationAwsSesEventHandling\Events\SesDeliveryDelayEvent;
use AdvisingApp\IntegrationAwsSesEventHandling\Events\SesDeliveryEvent;
use AdvisingApp\IntegrationAwsSesEventHandling\Events\SesOpenEvent;
use AdvisingApp\IntegrationAwsSesEventHandling\Events\SesRejectEvent;
use AdvisingApp\IntegrationAwsSesEventHandling\Events\SesRenderingFailureEvent;
use AdvisingApp\IntegrationAwsSesEventHandling\Events\SesSendEvent;
use AdvisingApp\IntegrationAwsSesEventHandling\Events\SesSubscriptionEvent;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Exception;
use Illuminate\Http\Request;

class AwsSesInboundWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = SesEventData::fromRequest($request);

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->findOrFail(data_get($data->mail->tags, 'tenant_id'))->first();

        $tenant->execute(function () use ($data) {
            match ($data->eventType) {
                'Bounce' => SesBounceEvent::dispatch($data),
                'Click' => SesClickEvent::dispatch($data),
                'Complaint' => SesComplaintEvent::dispatch($data),
                'Delivery' => SesDeliveryEvent::dispatch($data),
                'DeliveryDelay' => SesDeliveryDelayEvent::dispatch($data),
                'Open' => SesOpenEvent::dispatch($data),
                'Reject' => SesRejectEvent::dispatch($data),
                'RenderingFailure' => SesRenderingFailureEvent::dispatch($data),
                'Send' => SesSendEvent::dispatch($data),
                'Subscription' => SesSubscriptionEvent::dispatch($data),
                default => throw new Exception('Unknown AWS SES event type'),
            };
        });

        return response(status: 200);
    }
}
