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

namespace AdvisingApp\Prospect\Filament\Resources\ProspectResource\Pages;

use AdvisingApp\Prospect\Filament\Resources\ProspectResource;
use AdvisingApp\Prospect\Models\Prospect;
use AdvisingApp\Prospect\Models\ProspectSource;
use AdvisingApp\Prospect\Models\ProspectStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Builder;

class CreateProspect extends CreateRecord
{
    protected static string $resource = ProspectResource::class;

    public function form(Form $form): Form
    {
        $generateFullName = function (Get $get, Set $set) {
            $firstName = trim($get('first_name'));

            if (blank($firstName)) {
                return;
            }

            $lastName = trim($get('last_name'));

            if (blank($lastName)) {
                return;
            }

            $set(Prospect::displayNameKey(), "{$firstName} {$lastName}");
        };

        return $form
            ->schema([
                Section::make('Demographics')
                    ->schema([
                        TextInput::make('first_name')
                            ->label('First Name')
                            ->required()
                            ->string()
                            ->live(onBlur: true)
                            ->afterStateUpdated($generateFullName),
                        TextInput::make('last_name')
                            ->label('Last Name')
                            ->required()
                            ->string()
                            ->live(onBlur: true)
                            ->afterStateUpdated($generateFullName),
                        TextInput::make(Prospect::displayNameKey())
                            ->label('Full Name')
                            ->required()
                            ->string()
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255),
                        TextInput::make('preferred')
                            ->label('Preferred Name')
                            ->string()
                            ->maxLength(255),
                        // TODO: Display this based on system configurable data format
                        DatePicker::make('birthdate')
                            ->label('Birthdate')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->format('Y-m-d')
                            ->displayFormat('Y-m-d')
                            ->maxDate(now()),
                        TextInput::make('hsgrad')
                            ->label('High School Graduation Date')
                            ->nullable()
                            ->numeric()
                            ->minValue(1920)
                            ->maxValue(now()->addYears(25)->year),
                    ])
                    ->columns(2),
                Section::make('Contact Information')
                    ->schema([
                        TextInput::make('email')
                            ->label('Primary Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('email_2')
                            ->label('Other Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('mobile')
                            ->label('Mobile')
                            ->string()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Other Phone')
                            ->string()
                            ->maxLength(255),
                        TextInput::make('address')
                            ->label('Address')
                            ->string()
                            ->maxLength(255),
                        TextInput::make('address_2')
                            ->label('Apartment/Unit Number')
                            ->string()
                            ->maxLength(255),
                        TextInput::make('address_3')
                            ->label('Additional Address')
                            ->string()
                            ->maxLength(255),
                        TextInput::make('city')
                            ->label('City')
                            ->string()
                            ->maxLength(255),
                        TextInput::make('state')
                            ->label('State')
                            ->string()
                            ->maxLength(255),
                        TextInput::make('postal')
                            ->label('Postal')
                            ->string()
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('Classification')
                    ->schema([
                        Select::make('status_id')
                            ->label('Status')
                            ->required()
                            ->relationship('status', 'name', fn (Builder $query) => $query->orderBy('sort'))
                            ->default(fn () => ProspectStatus::query()
                                ->orderBy('sort')
                                ->first()
                                ?->getKey())
                            ->exists(
                                table: (new ProspectStatus())->getTable(),
                                column: (new ProspectStatus())->getKeyName()
                            ),
                        Select::make('source_id')
                            ->label('Source')
                            ->required()
                            ->relationship('source', 'name')
                            ->default(fn () => ProspectSource::query()->orderBy('name')->first()?->getKey())
                            ->exists(
                                table: (new ProspectSource())->getTable(),
                                column: (new ProspectSource())->getKeyName()
                            ),
                        Textarea::make('description')
                            ->label('Description')
                            ->string()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Engagement Restrictions')
                    ->schema([
                        Radio::make('sms_opt_out')
                            ->label('SMS Opt Out')
                            ->default(false)
                            ->boolean(),
                        Radio::make('email_bounce')
                            ->label('Email Bounce')
                            ->default(false)
                            ->boolean(),
                    ])
                    ->columns(2),
            ]);
    }
}
