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

namespace AdvisingApp\IntegrationOpenAi\Services;

use AdvisingApp\Ai\Exceptions\AiStreamEndedUnexpectedlyException;
use AdvisingApp\Ai\Exceptions\MessageResponseException;
use AdvisingApp\Ai\Exceptions\MessageResponseTimeoutException;
use AdvisingApp\Ai\Models\AiAssistant;
use AdvisingApp\Ai\Models\AiMessage;
use AdvisingApp\Ai\Models\AiMessageFile;
use AdvisingApp\Ai\Models\AiThread;
use AdvisingApp\Ai\Models\Contracts\AiFile;
use AdvisingApp\Ai\Services\Concerns\HasAiServiceHelpers;
use AdvisingApp\Ai\Services\Contracts\AiService;
use AdvisingApp\Ai\Settings\AiSettings;
use AdvisingApp\IntegrationOpenAi\DataTransferObjects\Assistants\AssistantsDataTransferObject;
use AdvisingApp\IntegrationOpenAi\DataTransferObjects\Assistants\FileSearchDataTransferObject;
use AdvisingApp\IntegrationOpenAi\DataTransferObjects\Assistants\ToolResourcesDataTransferObject;
use AdvisingApp\IntegrationOpenAi\DataTransferObjects\Threads\ThreadsDataTransferObject;
use AdvisingApp\IntegrationOpenAi\Exceptions\FileUploadsCannotBeDisabled;
use AdvisingApp\IntegrationOpenAi\Exceptions\FileUploadsCannotBeEnabled;
use AdvisingApp\IntegrationOpenAi\Services\Concerns\UploadsFiles;
use AdvisingApp\Report\Enums\TrackedEventType;
use AdvisingApp\Report\Jobs\RecordTrackedEvent;
use AdvisingApp\Research\Models\ResearchRequest;
use Closure;
use Exception;
use Generator;
use Illuminate\Support\Facades\Http;
use OpenAI\Contracts\ClientContract;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Responses\Threads\Messages\ThreadMessageResponse;
use OpenAI\Responses\Threads\Runs\ThreadRunResponse;
use OpenAI\Responses\Threads\ThreadResponse;
use OpenAI\Testing\ClientFake;
use Throwable;

abstract class BaseOpenAiService implements AiService
{
    use HasAiServiceHelpers;
    use UploadsFiles;

    public const FORMATTING_INSTRUCTIONS = 'When you answer, it is crucial that you format your response using rich text in markdown format. Do not ever mention in your response that the answer is being formatted/rendered in markdown.';

    protected ClientContract $client;

    abstract public function getApiKey(): string;

    abstract public function getApiVersion(): string;

    abstract public function getModel(): string;

    public function getClient(): ClientContract
    {
        return $this->client;
    }

    public function complete(string $prompt, string $content, bool $shouldTrack = true): string
    {
        try {
            $response = Http::asJson()
                ->withHeader('api-key', $this->getApiKey())
                ->post("{$this->getDeployment()}/deployments/{$this->getModel()}/chat/completions?api-version={$this->getApiVersion()}", [
                    'messages' => [
                        ['role' => 'system', 'content' => $prompt],
                        ['role' => 'user', 'content' => $content],
                    ],
                    'temperature' => app(AiSettings::class)->temperature,
                ]);
        } catch (Throwable $exception) {
            report($exception);

            throw new MessageResponseException('Failed to complete the prompt: [' . $exception->getMessage() . '].');
        }

        if (! $response->successful()) {
            throw new MessageResponseException('Failed to complete the prompt: [' . $response->body() . '].');
        }

        if ($shouldTrack) {
            dispatch(new RecordTrackedEvent(
                type: TrackedEventType::AiExchange,
                occurredAt: now(),
            ));
        }

        return $response->json(
            key: 'choices.0.message.content',
            default: fn () => throw new MessageResponseException('Missing response content when completing a prompt: [' . $response->body() . '].'),
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function stream(string $prompt, string $content, bool $shouldTrack = true, array $options = []): Closure
    {
        throw new Exception('Streaming a single response is not supported by this service.');
    }

    public function createAssistant(AiAssistant $assistant): void
    {
        $newAssistantResponse = $this->client->assistants()->create([
            'name' => $assistant->name,
            'instructions' => $this->generateAssistantInstructions($assistant),
            'model' => $this->getModel(),
            'metadata' => [
                'last_updated_at' => now(),
            ],
        ]);

        $assistant->assistant_id = $newAssistantResponse->id;
    }

    public function updateAssistant(AiAssistant $assistant): void
    {
        $this->client->assistants()->modify($assistant->assistant_id, [
            'instructions' => $this->generateAssistantInstructions($assistant),
            'name' => $assistant->name,
            'model' => $this->getModel(),
        ]);
    }

    public function retrieveAssistant(AiAssistant $assistant): ?AssistantsDataTransferObject
    {
        $assistantResponse = $this->client->assistants()->retrieve($assistant->assistant_id);

        return AssistantsDataTransferObject::from([
            'id' => $assistantResponse->id,
            'name' => $assistantResponse->name,
            'description' => $assistantResponse->description,
            'model' => $assistantResponse->model,
            'instructions' => $assistantResponse->instructions,
            'tools' => $assistantResponse->tools,
            'toolResources' => ToolResourcesDataTransferObject::from([
                'codeInterpreter' => $assistantResponse->toolResources->codeInterpreter ?? null,
                'fileSearch' => FileSearchDataTransferObject::from([
                    'vectorStoreIds' => $assistantResponse->toolResources->fileSearch->vectorStoreIds ?? [],
                ]),
            ]),
        ]);
    }

    public function updateAssistantTools(AiAssistant $assistant, array $tools): void
    {
        $tools = collect($tools)->map(function ($tool) {
            return [
                'type' => $tool,
            ];
        })->toArray();

        $this->client->assistants()->modify($assistant->assistant_id, [
            'tools' => $tools,
        ]);
    }

    public function enableAssistantFileUploads(AiAssistant $assistant): void
    {
        throw new FileUploadsCannotBeEnabled();
    }

    public function disableAssistantFileUploads(AiAssistant $assistant): void
    {
        throw new FileUploadsCannotBeDisabled();
    }

    public function createThread(AiThread $thread): void
    {
        $existingMessagePopulationLimit = 32;
        $existingMessages = [];
        $existingMessagesOverflow = [];

        if ($thread->exists) {
            $allExistingMessages = $thread->messages()
                ->orderBy('id')
                ->get()
                ->toBase()
                ->map(fn (AiMessage $message): array => [
                    'content' => $message->content,
                    'role' => $message->user_id ? 'user' : 'assistant',
                ]);

            $existingMessages = $allExistingMessages
                ->take($existingMessagePopulationLimit)
                ->values()
                ->all();

            $existingMessagesOverflow = $allExistingMessages
                ->slice($existingMessagePopulationLimit)
                ->values()
                ->all();
        }

        $newThreadResponse = $this->client->threads()->create([
            'messages' => $existingMessages,
        ]);

        $thread->thread_id = $newThreadResponse->id;

        if (count($existingMessagesOverflow)) {
            foreach ($existingMessagesOverflow as $overflowMessage) {
                $this->client->threads()->messages()->create($thread->thread_id, $overflowMessage);
            }
        }
    }

    public function retrieveThread(AiThread $thread): ?ThreadsDataTransferObject
    {
        $threadResponse = $this->client->threads()->retrieve($thread->thread_id);

        return ThreadsDataTransferObject::from([
            'id' => $thread->thread_id,
            'vectorStoreIds' => $threadResponse->toolResources?->fileSearch?->vectorStoreIds ?? [],
        ]);
    }

    public function modifyThread(AiThread $thread, array $parameters): ?ThreadsDataTransferObject
    {
        /** @var ThreadResponse $updatedThreadResponse */
        $updatedThreadResponse = $this->client->threads()->modify($thread->thread_id, $parameters);

        return ThreadsDataTransferObject::from([
            'id' => $updatedThreadResponse->id,
            'vectorStoreIds' => $updatedThreadResponse->toolResources?->fileSearch?->vectorStoreIds ?? [],
        ]);
    }

    public function deleteThread(AiThread $thread): void
    {
        try {
            foreach ($this->retrieveThread($thread)?->vectorStoreIds as $vectorStoreId) {
                $this->deleteVectorStore($vectorStoreId);
            }

            $this->client->threads()->delete($thread->thread_id);
        } catch (ErrorException $e) {
            if ($e->getMessage() !== 'Resource not found') {
                throw $e;
            }

            report($e);
        }

        $thread->thread_id = null;
    }

    public function sendMessage(AiMessage $message, array $files, Closure $saveResponse): Closure
    {
        $latestRun = $this->client->threads()->runs()->list($message->thread->thread_id, [
            'order' => 'desc',
            'limit' => 1,
        ])->data[0] ?? null;

        // An existing run might be in progress, so we need to wait for it to complete first.
        if ($latestRun) {
            $this->awaitPreviousThreadRunCompletion($latestRun);
        }

        $newMessageResponse = $this->createMessage($message->thread->thread_id, $message->content, $files);

        $instructions = $this->generateAssistantInstructions($message->thread->assistant, withDynamicContext: true);

        $message->context = $instructions;
        $message->message_id = $newMessageResponse->id;
        $message->save();

        $message->files()->saveMany($files);

        dispatch(new RecordTrackedEvent(
            type: TrackedEventType::AiExchange,
            occurredAt: now(),
        ));

        try {
            if (is_null($message->thread->name)) {
                $prompt = $message->context . "\nThe following is the start of a chat between you and a user:\n" . $message->content;

                $message->thread->name = $this->complete(
                    $prompt,
                    'Generate a title for this chat, in 5 words or less. Do not respond with any greetings or salutations, and do not include any additional information or context. Just respond with the title:',
                    false
                );

                $message->thread->saved_at = now();

                $message->thread->save();

                dispatch(new RecordTrackedEvent(
                    type: TrackedEventType::AiThreadSaved,
                    occurredAt: now(),
                ));
            }
        } catch (Exception $e) {
            report($e);

            $message->thread->name = 'Untitled Chat';
        }

        return $this->streamRun($message, $instructions, $saveResponse);
    }

    public function completeResponse(AiMessage $response, Closure $saveResponse): Closure
    {
        $latestRun = $this->client->threads()->runs()->list($response->thread->thread_id, [
            'order' => 'desc',
            'limit' => 1,
        ])->data[0] ?? null;

        // An existing run might be in progress, so we need to wait for it to complete first.
        if ($latestRun) {
            $this->awaitPreviousThreadRunCompletion($latestRun);
        }

        $this->createMessage(
            $response->thread->thread_id,
            'Continue generating the response, do not mention that I told you as I will paste it directly after the last message.',
        );

        return $this->streamRun($response, $this->generateAssistantInstructions($response->thread->assistant, withDynamicContext: true), $saveResponse);
    }

    public function retryMessage(AiMessage $message, array $files, Closure $saveResponse): Closure
    {
        $latestRun = $this->client->threads()->runs()->list($message->thread->thread_id, [
            'order' => 'desc',
            'limit' => 1,
        ])->data[0] ?? null;

        if ($latestRun && (! $this->isThreadRunCompleted($latestRun))) {
            $latestRun = $this->awaitPreviousThreadRunCompletion($latestRun, shouldCancelIfQueued: false);

            if (
                filled($message->message_id) &&
                (in_array($latestRun?->status, ['completed', 'incomplete']))
            ) {
                $latestMessageResponse = $this->client->threads()->messages()->list($message->thread->thread_id, [
                    'order' => 'desc',
                    'limit' => 1,
                ])->data[0];

                return function () use ($latestMessageResponse, $latestRun, $saveResponse): Generator {
                    $response = new AiMessage();

                    yield json_encode(['type' => 'content', 'content' => base64_encode($latestMessageResponse->content[0]->text->value)]);

                    $response->content = $latestMessageResponse->content[0]->text->value;
                    $response->message_id = $latestMessageResponse->id;

                    if ($latestRun->status === 'incomplete') {
                        yield json_encode(['type' => 'content', 'content' => base64_encode('...'), 'incomplete' => true]);
                        $response->content .= '...';
                    }

                    $saveResponse($response);
                };
            }
        }

        $instructions = $this->generateAssistantInstructions($message->thread->assistant, withDynamicContext: true);

        if (blank($message->message_id)) {
            $newMessageResponse = $this->createMessage($message->thread->thread_id, $message->content, $files);

            $message->context = $instructions;
            $message->message_id = $newMessageResponse->id;
            $message->save();
        }

        foreach ($files as $file) {
            $file->message()->associate($message);
            $file->save();
        }

        return $this->streamRun($message, $instructions, $saveResponse);
    }

    public function getMaxAssistantInstructionsLength(): int
    {
        $limit = 32768;

        $limit -= strlen(resolve(AiSettings::class)->prompt_system_context);
        $limit -= strlen(static::FORMATTING_INSTRUCTIONS);

        $limit -= 600; // For good measure.
        $limit -= ($limit % 100); // Round down to the nearest 100.

        return $limit;
    }

    public function isAssistantExisting(AiAssistant $assistant): bool
    {
        return filled($assistant->assistant_id);
    }

    public function isThreadExisting(AiThread $thread): bool
    {
        return filled($thread->thread_id);
    }

    public function supportsMessageFileUploads(): bool
    {
        return true;
    }

    public function supportsAssistantFileUploads(): bool
    {
        return true;
    }

    public function isFileReady(AiFile $file): bool
    {
        return true;
    }

    public function fake(): void
    {
        $this->client = new ClientFake();
    }

    public function hasTemperature(): bool
    {
        return true;
    }

    public function isResearchRequestReady(ResearchRequest $researchRequest): bool
    {
        return true;
    }

    /**
     * @return array<string>
     */
    public function getResearchRequestRequestSearchQueries(ResearchRequest $researchRequest, string $prompt, string $content): array
    {
        return [];
    }

    /**
     * @return array{response: array<mixed>, nextRequestOptions: array<string, mixed>}
     */
    public function getResearchRequestRequestOutline(ResearchRequest $researchRequest, string $prompt, string $content): array
    {
        return ['response' => [], 'nextRequestOptions' => []];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getResearchRequestRequestSection(ResearchRequest $researchRequest, string $prompt, string $content, array $options, Closure $nextRequestOptions): Generator
    {
        yield '';
    }

    public function afterResearchRequestSearchQueriesParsed(ResearchRequest $researchRequest): void {}

    /**
     * @param array<AiMessageFile> $files
     */
    protected function createMessage(string $threadId, string $content, array $files = []): ThreadMessageResponse
    {
        if (filled($files)) {
            $content .= <<<'EOT'

                ---

                Consider the content from the following files. These have already been converted by Canyon GBS' technology to Markdown for improved processing. When you reference these files, reference the file names as user uploaded files as noted below:

                EOT;

            foreach ($files as $file) {
                $content .= <<<EOT
                    ---

                    File Name: {$file->name}
                    Type: {$file->mime_type}
                    Source: User Uploaded
                    Contents: {$file->parsing_results}

                    EOT;
            }
        }

        return $this->client->threads()->messages()->create($threadId, [
            'role' => 'user',
            'content' => $content,
        ]);
    }

    protected function isThreadRunCompleted(ThreadRunResponse $threadRunResponse): bool
    {
        if ($threadRunResponse->requiredAction) {
            return true;
        }

        return in_array($threadRunResponse->status, ['completed', 'incomplete', 'failed', 'expired', 'requires_action']);
    }

    protected function isThreadRunRateLimited(ThreadRunResponse $threadRunResponse): bool
    {
        if ($threadRunResponse->status !== 'failed') {
            return false;
        }

        return $threadRunResponse->lastError?->code === 'rate_limit_exceeded';
    }

    protected function awaitPreviousThreadRunCompletion(ThreadRunResponse $threadRunResponse, bool $shouldCancelIfQueued = true): ThreadRunResponse
    {
        if ($this->isThreadRunCompleted($threadRunResponse)) {
            return $threadRunResponse;
        }

        $runId = $threadRunResponse->id;

        // 60 second total request timeout, with a 10-second buffer.
        $currentTime = time();
        $requestTime = app()->runningUnitTests() ? time() : $_SERVER['REQUEST_TIME'];
        $timeoutInSeconds = 60 - ($currentTime - $requestTime) - 10;
        $expiration = $currentTime + $timeoutInSeconds;

        while (! (
            in_array($threadRunResponse->status, ['completed', 'incomplete', 'cancelled']) ||
            $this->isThreadRunRateLimited($threadRunResponse)
        )) {
            if (time() >= $expiration) {
                return $threadRunResponse;
            }

            if (($threadRunResponse->status === 'queued') && $shouldCancelIfQueued) {
                $this->client->threads()->runs()->cancel($threadRunResponse->threadId, $runId);

                $threadRunResponse = $this->client->threads()->runs()->retrieve($threadRunResponse->threadId, $runId);

                continue;
            }

            if (
                ($threadRunResponse->status === 'requires_action') ||
                $threadRunResponse->requiredAction
            ) {
                report(new MessageResponseException('Awaited previous thread run not successful as an action was required: [' . json_encode($threadRunResponse->toArray()) . '].'));

                return $threadRunResponse;
            }

            if (in_array($threadRunResponse->status, ['failed', 'expired'])) {
                report(new MessageResponseException('Awaited previous thread run not successful: [' . json_encode($threadRunResponse->toArray()) . '].'));

                return $threadRunResponse;
            }

            if (! in_array($threadRunResponse->status, ['in_progress', 'cancelling', 'queued'])) {
                report(new MessageResponseException('An unexpected awaited previous thread run response status was encountered, which may be causing users to wait unnecessarily for the previous thread run to complete: [' . json_encode($threadRunResponse->toArray()) . '].'));
            }

            usleep(500000);

            $threadRunResponse = $this->client->threads()->runs()->retrieve($threadRunResponse->threadId, $runId);
        }

        return $threadRunResponse;
    }

    protected function generateAssistantInstructions(AiAssistant $assistant, bool $withDynamicContext = false): string
    {
        $assistantInstructions = rtrim($assistant->instructions, '. ');

        $maxAssistantInstructionsLength = $this->getMaxAssistantInstructionsLength();

        if (strlen($assistantInstructions) > $maxAssistantInstructionsLength) {
            $truncationEnd = '... [truncated]';

            $assistantInstructions = (string) str($assistantInstructions)
                ->limit($maxAssistantInstructionsLength - strlen($truncationEnd), $truncationEnd);
        }

        $formattingInstructions = static::FORMATTING_INSTRUCTIONS;

        if ($withDynamicContext) {
            $dynamicContext = rtrim(auth()->user()->getDynamicContext(), '. ');

            $instructions = "{$dynamicContext}.\n\n{$assistantInstructions}.\n\n{$formattingInstructions}";
        } else {
            $instructions = "{$assistantInstructions}.\n\n{$formattingInstructions}";
        }

        if (filled($files = $assistant->files()->whereNotNull('parsing_results')->get()->all())) {
            $instructions .= <<<'EOT'

                ---

                Consider the following additional knowledge, which has already been handled by Canyon GBS' technology to Markdown for improved processing. When you reference the information, describe that it is part of the assistant's knowledge:

                EOT;

            foreach ($files as $file) {
                $instructions .= <<<EOT
                    ---

                    Type: {$file->mime_type}
                    Source: Assistant Knowledge
                    Contents: {$file->parsing_results}

                    EOT;
            }
        }

        return $instructions;
    }

    protected function streamRun(AiMessage $message, string $instructions, Closure $saveResponse): Closure
    {
        $aiSettings = app(AiSettings::class);

        $runData = [
            'assistant_id' => $message->thread->assistant->assistant_id,
            'instructions' => $instructions,
            'max_completion_tokens' => $aiSettings->max_tokens->getTokens(),
            ...($this->hasTemperature() ? ['temperature' => $aiSettings->temperature] : []),
        ];

        if ($message->thread->messages()->whereHas('files')->exists()) {
            $runData['tools'] = [
                ['type' => 'file_search'],
            ];
        }

        $stream = $this->client->threads()->runs()->createStreamed($message->thread->thread_id, $runData);

        return function () use ($message, $saveResponse, $stream): Generator {
            try {
                // If the message was sent by the user, save the response to a new record.
                // If the message was sent by the assistant, and we are completing the response, save it to the existing record.
                $response = filled($message->user_id) ? (new AiMessage()) : $message;

                foreach ($stream as $streamResponse) {
                    if (in_array($streamResponse->event, [
                        'thread.run.expired',
                        'thread.run.step.expired',
                    ])) {
                        yield json_encode(['type' => 'timeout', 'message' => 'The AI took too long to respond to your message.']);

                        report(new MessageResponseTimeoutException());

                        return;
                    }

                    if (
                        ($streamResponse->event === 'thread.run.failed') &&
                        $this->isThreadRunRateLimited($streamResponse->response)
                    ) {
                        preg_match(
                            '/Try\sagain\sin\s([0-9]+)\sseconds/',
                            $streamResponse->response->lastError?->message ?? '',
                            $matches,
                        );

                        if (empty($matches[1])) {
                            yield json_encode(['type' => 'failed', 'message' => 'An error happened when sending your message.']);

                            report(new MessageResponseException('Thread run was rate limited, but the system was unable to extract the number of retry seconds: [' . json_encode($streamResponse->response->toArray()) . '].'));

                            return;
                        }

                        yield json_encode(['type' => 'rate_limited', 'message' => 'Heavy traffic, just a few more moments...', 'retry_after_seconds' => $matches[1]]);

                        return;
                    }

                    if (in_array($streamResponse->event, [
                        'thread.run.failed',
                        'thread.run.cancelling',
                        'thread.run.cancelled',
                        'thread.run.step.failed',
                        'thread.run.step.cancelled',
                    ])) {
                        yield json_encode(['type' => 'failed', 'message' => 'An error happened when sending your message.']);

                        report(new MessageResponseException('Thread run not successful: [' . json_encode($streamResponse->response->toArray()) . '].'));

                        return;
                    }

                    if ($streamResponse->event === 'thread.message.incomplete') {
                        yield json_encode(['type' => 'content', 'content' => base64_encode('...'), 'incomplete' => true]);
                        $response->content .= '...';

                        continue;
                    }

                    if (
                        (
                            (($streamResponse->event === 'thread.run.step.completed') && ($streamResponse->response->type === 'message_creation')) ||
                            ($streamResponse->event === 'thread.run.completed')
                        ) &&
                        blank($response->content)
                    ) {
                        yield json_encode(['type' => 'failed', 'message' => 'An error happened when sending your message.']);

                        report(new MessageResponseException('Thread run did not generate a reply: [' . json_encode($streamResponse->response->toArray()) . '].'));

                        return;
                    }

                    if ($streamResponse->event === 'thread.message.delta') {
                        foreach ($streamResponse->response->delta->content as $content) {
                            yield json_encode(['type' => 'content', 'content' => base64_encode($content->text->value)]);
                            $response->content .= $content->text->value;
                        }

                        $response->message_id = $streamResponse->response->id;

                        continue;
                    }

                    if (
                        ($streamResponse->event === 'thread.message.completed') &&
                        filled($response->content)
                    ) {
                        $saveResponse($response);

                        return;
                    }

                    if (
                        (
                            (($streamResponse->event === 'thread.run.step.completed') && ($streamResponse->response->type === 'message_creation')) ||
                            ($streamResponse->event === 'thread.run.completed')
                        ) &&
                        filled($response->content)
                    ) {
                        $saveResponse($response);

                        return;
                    }
                }

                yield json_encode(['type' => 'timeout', 'message' => 'The AI took too long to respond to your message.']);

                report(new AiStreamEndedUnexpectedlyException());
            } catch (Throwable $exception) {
                yield json_encode(['type' => 'failed', 'message' => 'An error happened when sending your message.']);

                report($exception);
            }
        };
    }
}
