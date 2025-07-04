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

use AdvisingApp\Ai\Enums\AiAssistantApplication;
use AdvisingApp\Ai\Enums\AiModel;
use AdvisingApp\Ai\Models\AiAssistant;
use AdvisingApp\Ai\Models\AiMessage;
use AdvisingApp\Ai\Models\AiThread;
use AdvisingApp\Ai\Settings\AiSettings;
use AdvisingApp\IntegrationOpenAi\Services\BaseOpenAiService;
use AdvisingApp\Report\Enums\TrackedEventType;
use AdvisingApp\Report\Jobs\RecordTrackedEvent;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use GuzzleHttp\Stream\Stream as GuzzleStream;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use OpenAI\Resources\Assistants;
use OpenAI\Resources\Threads;
use OpenAI\Resources\ThreadsMessages;
use OpenAI\Resources\ThreadsRuns;
use OpenAI\Resources\VectorStores;
use OpenAI\Responses\Assistants\AssistantResponse;
use OpenAI\Responses\StreamResponse;
use OpenAI\Responses\Threads\Messages\ThreadMessageListResponse;
use OpenAI\Responses\Threads\Messages\ThreadMessageResponse;
use OpenAI\Responses\Threads\Runs\ThreadRunListResponse;
use OpenAI\Responses\Threads\Runs\ThreadRunResponse;
use OpenAI\Responses\Threads\ThreadDeleteResponse;
use OpenAI\Responses\Threads\ThreadResponse;
use OpenAI\Responses\VectorStores\VectorStoreDeleteResponse;
use OpenAI\Testing\ClientFake;

use function Tests\asSuperAdmin;

it('can create an assistant', function () {
    asSuperAdmin();

    /** @var BaseOpenAiService $service */
    $service = AiModel::OpenAiGptTest->getService();

    /** @var ClientFake $client */
    $client = $service->getClient();

    $client->addResponses([
        AssistantResponse::fake([
            'id' => $assistantId = Str::random(),
        ]),
    ]);

    $assistant = AiAssistant::factory()->make();

    $service->createAssistant($assistant);

    expect($assistant->assistant_id)
        ->toBe($assistantId);

    $client->assertSent(Assistants::class, 1);
});

it('can update an assistant', function () {
    asSuperAdmin();

    /** @var BaseOpenAiService $service */
    $service = AiModel::OpenAiGptTest->getService();

    /** @var ClientFake $client */
    $client = $service->getClient();

    $client->addResponses([
        AssistantResponse::fake(),
    ]);

    $assistant = AiAssistant::factory()
        ->create([
            'application' => AiAssistantApplication::PersonalAssistant,
            'assistant_id' => Str::random(),
            'is_default' => true,
            'model' => AiModel::OpenAiGptTest,
        ]);

    $service->updateAssistant($assistant);

    $client->assertSent(Assistants::class, 1);
});

it('can create a thread', function () {
    asSuperAdmin();

    /** @var BaseOpenAiService $service */
    $service = AiModel::OpenAiGptTest->getService();

    /** @var ClientFake $client */
    $client = $service->getClient();

    $client->addResponses([
        ThreadResponse::fake([
            'id' => $threadId = Str::random(),
        ]),
    ]);

    $thread = AiThread::factory()->make();

    $service->createThread($thread);

    expect($thread->thread_id)
        ->toBe($threadId);

    $client->assertSent(Threads::class, 1);
});

it('can create a thread with existing messages', function () {
    asSuperAdmin();

    /** @var BaseOpenAiService $service */
    $service = AiModel::OpenAiGptTest->getService();

    /** @var ClientFake $client */
    $client = $service->getClient();

    $client->addResponses([
        ThreadResponse::fake([
            'id' => $threadId = Str::random(),
        ]),
    ]);

    $thread = AiThread::factory()
        ->for(auth()->user())
        ->for(AiAssistant::factory()->state([
            'application' => AiAssistantApplication::Test,
            'assistant_id' => Str::random(),
            'is_default' => true,
            'model' => AiModel::OpenAiGptTest,
        ]), 'assistant')
        ->has(AiMessage::factory()->count(3), 'messages')
        ->create();

    $service->createThread($thread);

    expect($thread->thread_id)
        ->toBe($threadId);

    $client->assertSent(Threads::class, 1);
});

it('can create a thread with more than 32 messages', function () {
    asSuperAdmin();

    /** @var BaseOpenAiService $service */
    $service = AiModel::OpenAiGptTest->getService();

    /** @var ClientFake $client */
    $client = $service->getClient();

    $client->addResponses([
        ThreadResponse::fake([
            'id' => $threadId = Str::random(),
        ]),
        ThreadMessageResponse::fake([]),
        ThreadMessageResponse::fake([]),
        ThreadMessageResponse::fake([]),
    ]);

    $thread = AiThread::factory()
        ->for(auth()->user())
        ->for(AiAssistant::factory()->state([
            'application' => AiAssistantApplication::Test,
            'assistant_id' => Str::random(),
            'is_default' => true,
            'model' => AiModel::OpenAiGptTest,
        ]), 'assistant')
        ->has(AiMessage::factory()->count(35), 'messages')
        ->create();

    $service->createThread($thread);

    expect($thread->thread_id)
        ->toBe($threadId);

    $client->assertSent(Threads::class, 1);
    $client->assertSent(ThreadsMessages::class, 3);
});

it('can delete a thread', function () {
    asSuperAdmin();

    /** @var BaseOpenAiService $service */
    $service = AiModel::OpenAiGptTest->getService();

    /** @var ClientFake $client */
    $client = $service->getClient();

    $client->addResponses([
        ThreadResponse::fake([
            'tool_resources' => [
                'file_search' => [
                    'vector_store_ids' => [1, 2, 3],
                ],
            ],
        ]),
        VectorStoreDeleteResponse::fake(),
        VectorStoreDeleteResponse::fake(),
        VectorStoreDeleteResponse::fake(),
        ThreadDeleteResponse::fake(),
    ]);

    $thread = AiThread::factory()
        ->make([
            'thread_id' => Str::random(),
        ]);

    $service->deleteThread($thread);

    expect($thread->thread_id)
        ->toBeNull();

    $client->assertSent(Threads::class, 2);
    $client->assertSent(VectorStores::class, 3);
});

it('can send a message', function () {
    Queue::fake();

    asSuperAdmin();

    /** @var BaseOpenAiService $service */
    $service = AiModel::OpenAiGptTest->getService();

    /** @var ClientFake $client */
    $client = $service->getClient();

    $client->addResponses([
        ThreadRunListResponse::fake([
            'data' => [
                [
                    'status' => 'completed',
                ],
            ],
        ]),
        ThreadMessageResponse::fake([
            'id' => $messageId = Str::random(),
        ]),
        new StreamResponse('', new GuzzleResponse(200, [], GuzzleStream::factory())),
    ]);

    $message = AiMessage::factory()
        ->for(AiThread::factory()
            ->for(AiAssistant::factory()->state([
                'application' => AiAssistantApplication::PersonalAssistant,
                'assistant_id' => Str::random(),
                'is_default' => true,
                'model' => AiModel::OpenAiGptTest,
            ]), 'assistant')
            ->for(auth()->user())
            ->state([
                'thread_id' => Str::random(),
            ]), 'thread')
        ->make();

    $service->sendMessage($message, [], function () {});

    expect($message)
        ->message_id->toBe($messageId);

    $client->assertSent(ThreadsRuns::class, 2);
    $client->assertSent(ThreadsMessages::class, 1);

    expect(Queue::pushed(RecordTrackedEvent::class))
        ->toHaveCount(1)
        ->each
        ->toHaveProperties(['type' => TrackedEventType::AiExchange]);
});

it('can complete a message response', function () {
    asSuperAdmin();

    /** @var BaseOpenAiService $service */
    $service = AiModel::OpenAiGptTest->getService();

    /** @var ClientFake $client */
    $client = $service->getClient();

    $client->addResponses([
        ThreadRunListResponse::fake([
            'data' => [
                [
                    'status' => 'completed',
                ],
            ],
        ]),
        ThreadMessageResponse::fake([]),
        new StreamResponse('', new GuzzleResponse(200, [], GuzzleStream::factory())),
    ]);

    $message = AiMessage::factory()
        ->for(AiThread::factory()
            ->for(AiAssistant::factory()->state([
                'application' => AiAssistantApplication::PersonalAssistant,
                'assistant_id' => Str::random(),
                'is_default' => true,
                'model' => AiModel::OpenAiGptTest,
            ]), 'assistant')
            ->for(auth()->user())
            ->state([
                'thread_id' => Str::random(),
            ]), 'thread')
        ->make();

    $service->completeResponse($message, function () {});

    $client->assertSent(ThreadsRuns::class, 2);
    $client->assertSent(ThreadsMessages::class, 1);
});

it('can retry a message', function () {
    asSuperAdmin();

    /** @var BaseOpenAiService $service */
    $service = AiModel::OpenAiGptTest->getService();

    /** @var ClientFake $client */
    $client = $service->getClient();

    $client->addResponses([
        ThreadRunListResponse::fake([
            'data' => [
                [
                    'status' => 'completed',
                ],
            ],
        ]),
        ThreadMessageResponse::fake([
            'id' => $messageId = Str::random(),
        ]),
        new StreamResponse('', new GuzzleResponse(200, [], GuzzleStream::factory())),
    ]);

    $message = AiMessage::factory()
        ->for(AiThread::factory()
            ->for(AiAssistant::factory()->state([
                'application' => AiAssistantApplication::PersonalAssistant,
                'assistant_id' => Str::random(),
                'is_default' => true,
                'model' => AiModel::OpenAiGptTest,
            ]), 'assistant')
            ->for(auth()->user())
            ->state([
                'thread_id' => Str::random(),
            ]), 'thread')
        ->make([
            'message_id' => null,
        ]);

    $service->retryMessage($message, [], function () {});

    expect($message)
        ->message_id->toBe($messageId);

    $client->assertSent(ThreadsRuns::class, 2);
    $client->assertSent(ThreadsMessages::class, 1);
});

it('can await the response of a previous run instead of sending a message again when retrying', function () {
    asSuperAdmin();

    /** @var BaseOpenAiService $service */
    $service = AiModel::OpenAiGptTest->getService();

    /** @var ClientFake $client */
    $client = $service->getClient();

    $client->addResponses([
        ThreadRunListResponse::fake([
            'data' => [
                [
                    'status' => 'queued',
                ],
            ],
        ]),
        ThreadRunResponse::fake([
            'status' => 'completed',
        ]),
        ThreadMessageListResponse::fake([
            'data' => [
                [
                    'content' => [
                        [
                            'text' => [
                                'value' => 'Hello, how can I help you?',
                            ],
                        ],
                    ],
                    'id' => Str::random(),
                ],
            ],
        ]),
    ]);

    $message = AiMessage::factory()
        ->for(AiThread::factory()
            ->for(AiAssistant::factory()->state([
                'application' => AiAssistantApplication::PersonalAssistant,
                'assistant_id' => Str::random(),
                'is_default' => true,
                'model' => AiModel::OpenAiGptTest,
            ]), 'assistant')
            ->for(auth()->user())
            ->state([
                'thread_id' => Str::random(),
            ]), 'thread')
        ->make([
            'message_id' => Str::random(),
        ]);

    $service->retryMessage($message, [], function () {});

    $client->assertSent(ThreadsRuns::class, 2);
});

it('can create a run if one does not exist without sending the message again when retrying', function () {
    asSuperAdmin();

    /** @var BaseOpenAiService $service */
    $service = AiModel::OpenAiGptTest->getService();

    /** @var ClientFake $client */
    $client = $service->getClient();

    $client->addResponses([
        ThreadRunListResponse::fake([
            'data' => [],
        ]),
        ThreadRunResponse::fake([
            'status' => 'completed',
        ]),
        ThreadMessageListResponse::fake([
            'data' => [
                [
                    'content' => [
                        [
                            'text' => [
                                'value' => 'Hello, how can I help you?',
                            ],
                        ],
                    ],
                    'id' => Str::random(),
                ],
            ],
        ]),
    ]);

    $message = AiMessage::factory()
        ->for(AiThread::factory()
            ->for(AiAssistant::factory()->state([
                'application' => AiAssistantApplication::PersonalAssistant,
                'assistant_id' => Str::random(),
                'is_default' => true,
                'model' => AiModel::OpenAiGptTest,
            ]), 'assistant')
            ->for(auth()->user())
            ->state([
                'thread_id' => Str::random(),
            ]), 'thread')
        ->make([
            'message_id' => Str::random(),
        ]);

    $service->retryMessage($message, [], function () {});

    $client->assertSent(ThreadsRuns::class, 2);
});

it('can complete a prompt', function () {
    Queue::fake();

    asSuperAdmin();

    /** @var BaseOpenAiService $service */
    $service = AiModel::OpenAiGptTest->getService();

    Http::fake([
        '*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => $response = Str::random(),
                ],
            ]],
        ]),
    ]);

    $aiSettings = app(AiSettings::class);
    $aiSettings->temperature = $temperature = (float) random_int(1, 10) / 10;
    $aiSettings->save();

    expect($service->complete($prompt = Str::random(), $content = Str::random()))
        ->toBe($response);

    Http::assertSent(function (Request $request) use ($content, $prompt, $temperature): bool {
        expect($request->hasHeader('api-key'))
            ->toBeTrue();

        if ($request['temperature'] !== $temperature) {
            return false;
        }

        if ($request['messages'] !== [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => $content],
        ]) {
            return false;
        }

        return true;
    });

    expect(Queue::pushed(RecordTrackedEvent::class))
        ->toHaveCount(1)
        ->each
        ->toHaveProperties(['type' => TrackedEventType::AiExchange]);
});
