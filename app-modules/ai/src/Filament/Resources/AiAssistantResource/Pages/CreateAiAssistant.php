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

namespace AdvisingApp\Ai\Filament\Resources\AiAssistantResource\Pages;

use AdvisingApp\Ai\Filament\Resources\AiAssistantResource;
use AdvisingApp\Ai\Filament\Resources\AiAssistantResource\Concerns\HandlesFileUploads;
use AdvisingApp\Ai\Filament\Resources\AiAssistantResource\Forms\AiAssistantForm;
use AdvisingApp\Ai\Models\AiAssistant;
use AdvisingApp\Ai\Settings\AiCustomAdvisorSettings;
use Exception;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CreateAiAssistant extends CreateRecord
{
    use HandlesFileUploads;

    protected static string $resource = AiAssistantResource::class;

    public function form(Form $form): Form
    {
        return resolve(AiAssistantForm::class)->form($form);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $record = new ($this->getModel())($data);

        throw_if(
            ! $record instanceof AiAssistant,
            new Exception('The model must be an instance of AiAssistant.')
        );

        $settings = app(AiCustomAdvisorSettings::class);

        if (! $settings->allow_selection_of_model) {
            $record->model = $settings->preselected_model ?? $record->model;
        }

        $aiService = $record->model->getService();

        try {
            $aiService->createAssistant($record);
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Could not create assistant')
                ->body('We failed to connect to the AI service. Support has been notified about this problem. Please try again later.')
                ->danger()
                ->send();

            $this->halt();
        }

        $record->save();

        if (filled($data['uploaded_files'] ?? null)) {
            $this->uploadFilesToAssistant(
                assistant: $record,
                files: $data['uploaded_files']
            );
        }

        return $record;
    }
}
