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

use AdvisingApp\Ai\Models\AiAssistant;
use AdvisingApp\Ai\Models\AiMessageFile;
use AdvisingApp\Ai\Services\Contracts\AiServiceLifecycleHooks;
use AdvisingApp\Ai\Settings\AiIntegrationsSettings;
use AdvisingApp\IntegrationOpenAi\Services\Concerns\UploadsFiles;
use OpenAI;

class OpenAiGptO1MiniService extends BaseOpenAiService implements AiServiceLifecycleHooks
{
    use UploadsFiles;

    public function __construct(
        protected AiIntegrationsSettings $settings,
    ) {
        $this->client = OpenAI::factory()
            ->withBaseUri($this->getDeployment())
            ->withHttpHeader('api-key', $this->settings->open_ai_gpt_o1_mini_api_key ?? config('integration-open-ai.gpt_o1_mini_api_key'))
            ->withQueryParam('api-version', $this->getApiVersion())
            ->withHttpHeader('OpenAI-Beta', 'assistants=v2')
            ->withHttpHeader('Accept', '*/*')
            ->make();
    }

    public function enableAssistantFileUploads(AiAssistant $assistant): void
    {
        $this->client->assistants()->modify($assistant->assistant_id, [
            'tools' => [
                ['type' => 'file_search'],
            ],
        ]);
    }

    public function disableAssistantFileUploads(AiAssistant $assistant): void
    {
        $this->client->assistants()->modify($assistant->assistant_id, [
            'tools' => [],
        ]);
    }

    public function getApiKey(): string
    {
        return $this->settings->open_ai_gpt_o1_mini_api_key ?? config('integration-open-ai.gpt_o1_mini_api_key');
    }

    public function getApiVersion(): string
    {
        return '2024-05-01-preview';
    }

    public function getModel(): string
    {
        return $this->settings->open_ai_gpt_o1_mini_model ?? config('integration-open-ai.gpt_o1_mini_model');
    }

    public function getDeployment(): ?string
    {
        return $this->settings->open_ai_gpt_o1_mini_base_uri ?? config('integration-open-ai.gpt_o1_mini_base_uri');
    }

    public function supportsMessageFileUploads(): bool
    {
        return true;
    }

    public function beforeMessageFileForceDeleted(AiMessageFile $file): void
    {
        $this->deleteFile($file);
    }
}
