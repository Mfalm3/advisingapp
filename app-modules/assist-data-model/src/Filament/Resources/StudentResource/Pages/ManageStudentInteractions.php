<?php

/*
<COPYRIGHT>

Copyright © 2022-2023, Canyon GBS LLC

All rights reserved.

This file is part of a project developed using Laravel, which is an open-source framework for PHP.
Canyon GBS LLC acknowledges and respects the copyright of Laravel and other open-source
projects used in the development of this solution.

This project is licensed under the Affero General Public License (AGPL) 3.0.
For more details, see https://github.com/canyongbs/assistbycanyongbs/blob/main/LICENSE.

Notice:
- The copyright notice in this file and across all files and applications in this
 repository cannot be removed or altered without violating the terms of the AGPL 3.0 License.
- The software solution, including services, infrastructure, and code, is offered as a
 Software as a Service (SaaS) by Canyon GBS LLC.
- Use of this software implies agreement to the license terms and conditions as stated
 in the AGPL 3.0 License.

For more information or inquiries please visit our website at
https://www.canyongbs.com or contact us via email at legal@canyongbs.com.

</COPYRIGHT>
*/

namespace Assist\AssistDataModel\Filament\Resources\StudentResource\Pages;

use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Component;
use Assist\AssistDataModel\Models\Student;
use Filament\Forms\Components\MorphToSelect;
use Filament\Resources\Pages\ManageRelatedRecords;
use Assist\AssistDataModel\Filament\Resources\StudentResource;
use Assist\Interaction\Filament\Resources\InteractionResource\Pages\CreateInteraction;
use Assist\Interaction\Filament\Resources\InteractionResource\RelationManagers\HasManyMorphedInteractionsRelationManager;

class ManageStudentInteractions extends ManageRelatedRecords
{
    protected static string $resource = StudentResource::class;

    protected static string $relationship = 'interactions';

    // TODO: Automatically set from Filament based on relationship name
    protected static ?string $breadcrumb = 'Interactions';

    // TODO: Automatically set from Filament based on relationship name
    protected static ?string $navigationLabel = 'Interactions';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    public function form(Form $form): Form
    {
        $createInteractionForm = (resolve(CreateInteraction::class))->form($form);

        $formComponents = collect($createInteractionForm->getComponents())->filter(function (Component $component) {
            if (! $component instanceof MorphToSelect) {
                return true;
            }
        })->toArray();

        return $createInteractionForm
            ->schema([
                Hidden::make('interactable_id')
                    ->default($this->getOwnerRecord()->identifier()),
                Hidden::make('interactable_type')
                    ->default(resolve(Student::class)->getMorphClass()),
                ...$formComponents,
            ]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return (resolve(HasManyMorphedInteractionsRelationManager::class))->infolist($infolist);
    }

    public function table(Table $table): Table
    {
        return (resolve(HasManyMorphedInteractionsRelationManager::class))->table($table);
    }
}
