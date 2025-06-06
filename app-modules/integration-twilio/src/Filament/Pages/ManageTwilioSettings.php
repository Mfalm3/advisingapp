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

namespace AdvisingApp\IntegrationTwilio\Filament\Pages;

use AdvisingApp\IntegrationTwilio\Settings\TwilioSettings;
use App\Filament\Clusters\ProductIntegrations;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\SettingsPage;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class ManageTwilioSettings extends SettingsPage
{
    protected static string $settings = TwilioSettings::class;

    protected static ?string $title = 'Twilio Settings';

    protected static ?string $navigationLabel = 'Twilio';

    protected static ?int $navigationSort = 40;

    protected static ?string $cluster = ProductIntegrations::class;

    public static function canAccess(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->isSuperAdmin();
    }

    public function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Toggle::make('is_enabled')
                    ->label('Enabled')
                    ->live(),
                Toggle::make('is_demo_mode_enabled')
                    ->label('SMS Demo Mode')
                    ->helperText('When enabled, no messages will be sent.')
                    ->live(),
                Toggle::make('is_demo_auto_reply_mode_enabled')
                    ->label('SMS Demo Autoreply')
                    ->helperText('When enabled, SMS messages will receive an automatic reply.'),
                Section::make()
                    ->schema([
                        TextInput::make('account_sid')
                            ->label('Account SID')
                            ->string()
                            ->required()
                            ->password()
                            ->revealable(),
                        TextInput::make('auth_token')
                            ->string()
                            ->required()
                            ->password()
                            ->revealable(),
                        PhoneInput::make('from_number')
                            ->required(),
                    ])
                    ->visible(fn (Get $get) => $get('is_enabled') && ! $get('is_demo_mode_enabled')),
            ]);
    }
}
