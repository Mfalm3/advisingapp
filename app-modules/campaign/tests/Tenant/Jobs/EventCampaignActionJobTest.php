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

use AdvisingApp\Authorization\Enums\LicenseType;
use AdvisingApp\Campaign\Enums\CampaignActionType;
use AdvisingApp\Campaign\Jobs\EventCampaignActionJob;
use AdvisingApp\Campaign\Models\Campaign;
use AdvisingApp\Campaign\Models\CampaignAction;
use AdvisingApp\Campaign\Models\CampaignActionEducatable;
use AdvisingApp\Campaign\Models\CampaignActionEducatableRelated;
use AdvisingApp\MeetingCenter\Enums\EventAttendeeStatus;
use AdvisingApp\MeetingCenter\Models\Event;
use AdvisingApp\MeetingCenter\Models\EventAttendee;
use AdvisingApp\MeetingCenter\Notifications\RegistrationLinkToEventAttendeeNotification;
use AdvisingApp\Prospect\Models\Prospect;
use AdvisingApp\Segment\Enums\SegmentModel;
use AdvisingApp\Segment\Enums\SegmentType;
use AdvisingApp\Segment\Models\Segment;
use AdvisingApp\StudentDataModel\Models\Contracts\Educatable;
use AdvisingApp\StudentDataModel\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\assertDatabaseCount;

it('will execute appropriately on each educatable in the segment', function (Educatable $educatable) {
    Bus::fake();
    Notification::fake();

    /** @var Segment $segment */
    $segment = Segment::factory()->create([
        'type' => SegmentType::Static,
        'model' => match ($educatable::class) {
            Student::class => SegmentModel::Student,
            Prospect::class => SegmentModel::Prospect,
            default => throw new Exception('Invalid model type'),
        },
    ]);

    $campaign = Campaign::factory()
        ->for($segment, 'segment')
        ->for(User::factory()->licensed(LicenseType::cases()), 'createdBy')
        ->create();

    /** @var Event $event */
    $event = Event::factory()->create();

    $event->attendees()->truncate();

    /** @var CampaignAction $action */
    $action = CampaignAction::factory()
        ->for($campaign, 'campaign')
        ->create([
            'type' => CampaignActionType::Event,
            'data' => [
                'event' => $event->id,
            ],
        ]);

    $campaignActionEducatable = CampaignActionEducatable::factory()
        ->for($action, 'campaignAction')
        // @phpstan-ignore argument.type
        ->for($educatable, 'educatable')
        ->create();

    expect($campaignActionEducatable->succeeded_at)->toBeNull()
        ->and($campaignActionEducatable->last_failed_at)->toBeNull();

    [$job] = (new EventCampaignActionJob($campaignActionEducatable))->withFakeBatch();

    $job->handle();

    assertDatabaseCount(
        EventAttendee::class,
        1,
    );

    $attendee = EventAttendee::query()
        ->where('event_id', $event->getKey())
        ->where('email', $educatable->primaryEmailAddress->address)
        ->where('status', EventAttendeeStatus::Invited)
        ->first();

    expect($attendee)->not()->toBeNull();

    Notification::assertSentTo($attendee, RegistrationLinkToEventAttendeeNotification::class);

    $campaignActionEducatable->refresh();

    expect($campaignActionEducatable->succeeded_at)->not()->toBeNull()
        ->and($campaignActionEducatable->last_failed_at)->toBeNull()
        ->and($campaignActionEducatable->related)->toHaveCount(1);

    /** @var CampaignActionEducatableRelated $campaignActionEducatableRelated */
    $campaignActionEducatableRelated = $campaignActionEducatable->related->first();

    expect($campaignActionEducatableRelated->related->is($attendee))->toBeTrue();
})
    ->with([
        'student' => [
            fn () => Student::factory()->create(),
        ],
        'prospect' => [
            fn () => Prospect::factory()->create(),
        ],
    ]);

it('will not duplicate an invite if the segment educatable was already invited', function (Educatable $educatable) {
    Bus::fake();
    Notification::fake();

    /** @var Segment $segment */
    $segment = Segment::factory()->create([
        'type' => SegmentType::Static,
        'model' => match ($educatable::class) {
            Student::class => SegmentModel::Student,
            Prospect::class => SegmentModel::Prospect,
            default => throw new Exception('Invalid model type'),
        },
    ]);

    $campaign = Campaign::factory()
        ->for($segment, 'segment')
        ->for(User::factory()->licensed(LicenseType::cases()), 'createdBy')
        ->create();

    /** @var Event $event */
    $event = Event::factory()->create();

    $event->attendees()->truncate();

    EventAttendee::factory()->create([
        'event_id' => $event->getKey(),
        'email' => $educatable->primaryEmailAddress->address,
        'status' => EventAttendeeStatus::Invited,
    ]);

    /** @var CampaignAction $action */
    $action = CampaignAction::factory()
        ->for($campaign, 'campaign')
        ->create([
            'type' => CampaignActionType::Event,
            'data' => [
                'event' => $event->id,
            ],
        ]);

    $campaignActionEducatable = CampaignActionEducatable::factory()
        ->for($action, 'campaignAction')
        // @phpstan-ignore argument.type
        ->for($educatable, 'educatable')
        ->create();

    expect($campaignActionEducatable->succeeded_at)->toBeNull()
        ->and($campaignActionEducatable->last_failed_at)->toBeNull();

    [$job] = (new EventCampaignActionJob($campaignActionEducatable))->withFakeBatch();

    $job->handle();

    assertDatabaseCount(
        EventAttendee::class,
        1,
    );

    $attendee = EventAttendee::query()
        ->where('event_id', $event->getKey())
        ->where('email', $educatable->primaryEmailAddress->address)
        ->where('status', EventAttendeeStatus::Invited)
        ->first();

    expect($attendee)->not()->toBeNull();

    Notification::assertNotSentTo($attendee, RegistrationLinkToEventAttendeeNotification::class);

    $campaignActionEducatable->refresh();

    expect($campaignActionEducatable->succeeded_at)->not()->toBeNull()
        ->and($campaignActionEducatable->last_failed_at)->toBeNull()
        ->and($campaignActionEducatable->related()->count())->toEqual(0);
})
    ->with([
        'student' => [
            fn () => Student::factory()->create(),
        ],
        'prospect' => [
            fn () => Prospect::factory()->create(),
        ],
    ]);
