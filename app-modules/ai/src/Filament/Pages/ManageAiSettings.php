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

namespace AdvisingApp\Ai\Filament\Pages;

use AdvisingApp\Ai\Actions\ReInitializeAiServiceAssistant;
use AdvisingApp\Ai\Actions\ResetAiServiceIdsForAssistant;
use AdvisingApp\Ai\Enums\AiAssistantApplication;
use AdvisingApp\Ai\Enums\AiMaxTokens;
use AdvisingApp\Ai\Enums\AiModel;
use AdvisingApp\Ai\Enums\AiModelApplicabilityFeature;
use AdvisingApp\Ai\Enums\AiReasoningEffort;
use AdvisingApp\Ai\Models\AiAssistant;
use AdvisingApp\Ai\Settings\AiSettings;
use AdvisingApp\Authorization\Enums\LicenseType;
use App\Filament\Clusters\GlobalArtificialIntelligence;
use App\Models\User;
use CanyonGBS\Common\Filament\Forms\Components\Slider;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Throwable;

/**
 * @property-read ?AiAssistant $defaultAssistant

 */
class ManageAiSettings extends SettingsPage
{
    protected static string $settings = AiSettings::class;

    protected static ?string $title = 'Institutional Advisor';

    protected static ?string $cluster = GlobalArtificialIntelligence::class;

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->hasLicense(LicenseType::ConversationalAi)) {
            return false;
        }

        return $user->isSuperAdmin();
    }

    #[Computed]
    public function defaultAssistant(): ?AiAssistant
    {
        return AiAssistant::query()
            ->where('application', AiAssistantApplication::PersonalAssistant)
            ->where('is_default', true)
            ->first();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Institutional Assistant')
                    ->statePath('defaultAssistant')
                    ->columns()
                    ->visible($this->defaultAssistant !== null)
                    ->model($this->defaultAssistant)
                    ->schema([
                        Select::make('model')
                            ->options(fn (AiModel|string|null $state) => array_unique([
                                ...AiModelApplicabilityFeature::InstitutionalAdvisor->getModelsAsSelectOptions(),
                                ...match (true) {
                                    $state instanceof AiModel => [$state->value => $state->getLabel()],
                                    is_string($state) => [$state => AiModel::parse($state)->getLabel()],
                                    default => [],
                                },
                            ]))
                            ->rule(Rule::enum(AiModel::class)->only(AiModelApplicabilityFeature::InstitutionalAdvisor->getModels()))
                            ->searchable()
                            ->required()
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => filled($get('application'))),
                        Textarea::make('description')
                            ->columnSpanFull()
                            ->required(),
                        Section::make('Knowledge')
                            ->description('In the configuration below, we can further tailor the behavior of the Institutional Assistant for the specific needs at your college or university.')
                            ->schema([
                                Textarea::make('instructions')
                                    ->helperText('Instructions are used to provide context to the AI Assistant on how to respond to user queries.')
                                    ->required()
                                    ->rows(8)
                                    ->maxLength(fn (?AiAssistant $record): int => ($record?->model ?? AiModel::OpenAiGpt4o)->getService()->getMaxAssistantInstructionsLength()),
                            ]),
                    ]),
                Select::make('max_tokens')
                    ->label('Response Length')
                    ->options(AiMaxTokens::class)
                    ->enum(AiMaxTokens::class)
                    ->required(),
                Select::make('reasoning_effort')
                    ->label('Reasoning Effort')
                    ->options(AiReasoningEffort::class)
                    ->enum(AiReasoningEffort::class)
                    ->required(),
                Slider::make('temperature')
                    ->label('Creativity')
                    ->required()
                    ->step(0.1)
                    ->minValue(0)
                    ->maxValue(1),
                Select::make('default_model')
                    ->options(fn (AiModel|string|null $state) => array_unique([
                        ...AiModelApplicabilityFeature::InstitutionalAdvisor->getModelsAsSelectOptions(),
                        ...match (true) {
                            $state instanceof AiModel => [$state->value => $state->getLabel()],
                            is_string($state) => [$state => AiModel::parse($state)->getLabel()],
                            default => [],
                        },
                    ]))
                    ->rule(Rule::enum(AiModel::class)->only(AiModelApplicabilityFeature::InstitutionalAdvisor->getModels()))
                    ->searchable()
                    ->required(),
            ])
            ->disabled(! auth()->user()->isSuperAdmin());
    }

    public function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->submit(null)
            ->requiresConfirmation()
            ->modalHeading('Sync all chats to this new service?')
            ->modalDescription('If you are moving to a new account, you will need to sync all the data to the new service to minimize disruption. Advising App can do this for you, but if you just want to save the settings and do it yourself, you can choose to do so.')
            ->modalWidth(MaxWidth::ThreeExtraLarge)
            ->modalSubmitActionLabel('Save and sync all chats')
            ->modalHidden(function () {
                $newModelValue = $this->form->getRawState()['defaultAssistant']['model'] ?? null;

                if (blank($newModelValue)) {
                    return true;
                }

                $newModel = AiModel::parse($newModelValue);

                return $this->defaultAssistant->model->isSharedDeployment($newModel);
            })
            ->extraModalFooterActions([
                Action::make('justSave')
                    ->label('Just save the settings')
                    ->color('gray')
                    ->action(fn () => $this->save())
                    ->cancelParentActions(),
            ])
            ->action(function (ResetAiServiceIdsForAssistant $resetAiServiceIds, ReInitializeAiServiceAssistant $reInitializeAiServiceAssistant) {
                $newModelValue = $this->form->getRawState()['defaultAssistant']['model'] ?? null;
                $newModel = filled($newModelValue) ? AiModel::parse($newModelValue) : null;

                $modelDeploymentIsShared = $newModel ? $this->defaultAssistant->model->isSharedDeployment($newModel) : true;

                if (! $modelDeploymentIsShared) {
                    $resetAiServiceIds($this->defaultAssistant);

                    $state = $this->form->getRawState();
                    $state['defaultAssistant']['assistant_id'] = null;
                    $this->form->fill($state, false, false);
                }

                $this->save();

                if (! $modelDeploymentIsShared) {
                    $reInitializeAiServiceAssistant($this->defaultAssistant);
                }
            });
    }

    public function save(): void
    {
        if (! auth()->user()->isSuperAdmin()) {
            return;
        }

        parent::save();
    }

    /**
     * @return array<Action | ActionGroup>
     */
    public function getFormActions(): array
    {
        if (! auth()->user()->isSuperAdmin()) {
            return [];
        }

        return parent::getFormActions();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['defaultAssistant'] = $this->defaultAssistant?->attributesToArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('defaultAssistant', $data)) {
            $this->defaultAssistant->fill($data['defaultAssistant']);

            $aiService = $this->defaultAssistant->model->getService();

            try {
                $aiService->isAssistantExisting($this->defaultAssistant) ?
                    $aiService->updateAssistant($this->defaultAssistant) :
                    $aiService->createAssistant($this->defaultAssistant);
            } catch (Throwable $exception) {
                report($exception);

                Notification::make()
                    ->title('Could not save assistant')
                    ->body('We failed to connect to the AI service. Support has been notified about this problem. Please try again later.')
                    ->danger()
                    ->send();

                $this->halt();
            }

            $this->defaultAssistant->save();

            unset($data['defaultAssistant']);
        }

        if (is_string($data['default_model'])) {
            $data['default_model'] = AiModel::parse($data['default_model']);
        }

        return parent::mutateFormDataBeforeSave($data);
    }
}
