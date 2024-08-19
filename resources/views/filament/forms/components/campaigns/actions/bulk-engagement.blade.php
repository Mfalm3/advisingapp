{{--
<COPYRIGHT>

    Copyright © 2016-2024, Canyon GBS LLC. All rights reserved.

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
--}}
@php
    use AdvisingApp\Campaign\Settings\CampaignSettings;
    use AdvisingApp\Engagement\Enums\EngagementDeliveryMethod;
    use AdvisingApp\Engagement\Models\EngagementBatch;
    use Carbon\Carbon;
@endphp

<x-filament::fieldset>
    <x-slot name="label">
        @if ($action['delivery_method'] === EngagementDeliveryMethod::Email->value)
            Email
        @else
            Text Message
        @endif
    </x-slot>

    <dl class="max-w-md divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-white">
        <div class="flex flex-col pb-3">
            <dt class="mb-1 text-sm text-gray-500 dark:text-gray-400">Delivery Method</dt>
            <dd class="flex flex-row space-x-2 text-sm font-semibold">
                <x-filament::badge>
                    {{ $action['delivery_method'] }}
                </x-filament::badge>
            </dd>
        </div>
        @if (isset($action['subject']))
            <div class="flex flex-col pt-3">
                <dt class="mb-1 text-sm text-gray-500 dark:text-gray-400">Subject</dt>
                <dd class="text-sm font-semibold">{{ $action['subject'] }}</dd>
            </div>
        @endif
        @if ($action['body'])
            <div class="flex flex-col pt-3">
                <dt class="mb-1 text-sm text-gray-500 dark:text-gray-400">Body</dt>
                <dd class="prose dark:prose-invert text-sm font-semibold">
                    {!! EngagementBatch::renderWithMergeTags(
                    tiptap_converter()->asHTML(
                        $action['body'],
                        newImages: $this->componentFileAttachments['data']['actions'][$actionIndex]['data']['body'] ?? [],
                    ),
                ) !!}
                </dd>
            </div>
        @endif
        <div class="flex flex-col pt-3">
            <dt class="mb-1 text-sm text-gray-500 dark:text-gray-400">Execute At</dt>
            <dd class="text-sm font-semibold">{{ Carbon::parse($action['execute_at'])->format('M j, Y H:i:s') }}
                {{ app(CampaignSettings::class)->getActionExecutionTimezoneLabel() }}</dd>
        </div>
    </dl>
</x-filament::fieldset>
