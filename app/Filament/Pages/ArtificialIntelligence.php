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

namespace App\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

/**
 * @property Form $form
 */
class ArtificialIntelligence extends ProfilePage
{
    protected static ?string $slug = 'artificial-intelligence';

    protected static ?string $title = 'Artifical Intelligence';

    protected static ?int $navigationSort = 40;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Artificial Intelligence')
                    ->description('Select options for how you work with AI.')
                    ->schema([
                        Select::make('is_submit_ai_chat_on_enter_enabled')
                            ->label('Enter Key')
                            ->selectablePlaceholder(false)
                            ->helperText('Decide below if you would prefer the enter key to create a new line or submit the prompt you typed in the AI chat interface.')
                            ->options([
                                false => 'New Line',
                                true => 'Enter',
                            ]),
                    ]),
                Section::make('Action Center Update')
                    ->description('Opt-in to a daily update by your AI Institutional Advisor on activity in the last 24 hours.')
                    ->schema([
                        Toggle::make('is_action_center_update_enabled')
                            ->label('Action Center Update Enabled'),
                    ]),
            ]);
    }
}
