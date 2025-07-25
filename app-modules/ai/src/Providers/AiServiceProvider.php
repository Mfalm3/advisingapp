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

namespace AdvisingApp\Ai\Providers;

use AdvisingApp\Ai\AiPlugin;
use AdvisingApp\Ai\Events\AiMessageCreated;
use AdvisingApp\Ai\Events\AiMessageFileForceDeleting;
use AdvisingApp\Ai\Events\AiMessageTrashed;
use AdvisingApp\Ai\Events\AiThreadForceDeleting;
use AdvisingApp\Ai\Events\AiThreadTrashed;
use AdvisingApp\Ai\Events\AssistantFilesFinishedUploading;
use AdvisingApp\Ai\Listeners\ClearQnaAdvisorInstructionsCacheOnGlobalSettingsUpdate;
use AdvisingApp\Ai\Listeners\HandleAssistantFilesFinishedUploading;
use AdvisingApp\Ai\Models\AiAssistant;
use AdvisingApp\Ai\Models\AiAssistantFile;
use AdvisingApp\Ai\Models\AiMessage;
use AdvisingApp\Ai\Models\AiMessageFile;
use AdvisingApp\Ai\Models\AiThread;
use AdvisingApp\Ai\Models\AiThreadFolder;
use AdvisingApp\Ai\Models\Prompt;
use AdvisingApp\Ai\Models\PromptType;
use AdvisingApp\Ai\Models\QnaAdvisor;
use AdvisingApp\Ai\Models\QnaAdvisorCategory;
use AdvisingApp\Ai\Models\QnaAdvisorFile;
use AdvisingApp\Ai\Models\QnaAdvisorQuestion;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelSettings\Events\SettingsSaved;

class AiServiceProvider extends ServiceProvider
{
    protected $listen = [
        AssistantFilesFinishedUploading::class => [
            HandleAssistantFilesFinishedUploading::class,
        ],
        AiThreadTrashed::class => AiThreadTrashed::LISTENERS,
        AiThreadForceDeleting::class => AiThreadForceDeleting::LISTENERS,
        AiMessageCreated::class => AiMessageCreated::LISTENERS,
        AiMessageTrashed::class => AiMessageTrashed::LISTENERS,
        AiMessageFileForceDeleting::class => AiMessageFileForceDeleting::LISTENERS,
        SettingsSaved::class => [ClearQnaAdvisorInstructionsCacheOnGlobalSettingsUpdate::class],
    ];

    public function register(): void
    {
        Panel::configureUsing(fn (Panel $panel) => $panel->getId() !== 'admin' || $panel->plugin(new AiPlugin()));

        $this->booting(function () {
            foreach ($this->listen as $event => $listeners) {
                foreach (array_unique($listeners, SORT_REGULAR) as $listener) {
                    Event::listen($event, $listener);
                }
            }
        });
    }

    public function boot(): void
    {
        Relation::morphMap([
            'ai_assistant_file' => AiAssistantFile::class,
            'ai_assistant' => AiAssistant::class,
            'ai_message_file' => AiMessageFile::class,
            'ai_message' => AiMessage::class,
            'ai_thread_folder' => AiThreadFolder::class,
            'ai_thread' => AiThread::class,
            'prompt_type' => PromptType::class,
            'prompt' => Prompt::class,
            'qna_advisor' => QnaAdvisor::class,
            'qna_advisor_category' => QnaAdvisorCategory::class,
            'qna_advisor_question' => QnaAdvisorQuestion::class,
            'qna_advisor_file' => QnaAdvisorFile::class,
        ]);

        $this->mergeConfigFrom(__DIR__ . '/../../config/ai.php', 'ai');
    }
}
