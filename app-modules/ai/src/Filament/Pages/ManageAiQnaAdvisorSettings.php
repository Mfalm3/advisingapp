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

use AdvisingApp\Ai\Enums\AiModel;
use AdvisingApp\Ai\Enums\AiModelApplicabilityFeature;
use AdvisingApp\Ai\Settings\AiQnaAdvisorSettings;
use App\Filament\Clusters\GlobalArtificialIntelligence;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Illuminate\Validation\Rule;

class ManageAiQnaAdvisorSettings extends ManageAiICustomAdvisorSettings
{
    protected static string $settings = AiQnaAdvisorSettings::class;

    protected static ?string $title = 'QnA Advisor';

    protected static ?int $navigationSort = 40;

    protected static ?string $cluster = GlobalArtificialIntelligence::class;

    public static function canAccess(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->isSuperAdmin();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Toggle::make('allow_selection_of_model')
                    ->label('Allow selection of model?')
                    ->helperText('If enabled, admin can select a model when creating or editing QnA advisors.')
                    ->columnSpanFull()
                    ->live(),
                Select::make('preselected_model')
                    ->label('Select Model')
                    ->options(fn (AiModel|string|null $state) => array_unique([
                        ...AiModelApplicabilityFeature::QuestionAndAnswerAdvisor->getModelsAsSelectOptions(),
                        ...match (true) {
                            $state instanceof AiModel => [$state->value => $state->getLabel()],
                            is_string($state) => [$state => AiModel::parse($state)->getLabel()],
                            default => [],
                        },
                    ]))
                    ->searchable()
                    ->helperText('This model will be the model used for QnA advisors.')
                    ->columnSpanFull()
                    ->required()
                    ->visible(fn (Get $get): bool => ! $get('allow_selection_of_model'))
                    ->rule(Rule::enum(AiModel::class)->only(AiModelApplicabilityFeature::QuestionAndAnswerAdvisor->getModels())),
                Textarea::make('instructions')
                    ->label('Instructions')
                    ->columnSpanFull()
                    ->rows(10)
                    ->maxLength(65535)
                    ->required(),
                Textarea::make('background_information')
                    ->label('Background Information')
                    ->columnSpanFull()
                    ->rows(10)
                    ->maxLength(65535)
                    ->required(),
                Textarea::make('restrictions')
                    ->label('Restrictions')
                    ->columnSpanFull()
                    ->rows(10)
                    ->maxLength(65535)
                    ->helperText('These restrictions will be applied to the QnA advisor. Use this field to specify any limitations or guidelines for the AI model.')
                    ->required(),
            ]);
    }
}
