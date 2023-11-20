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

namespace Assist\KnowledgeBase\Filament\Resources\KnowledgeBaseItemResource\Pages;

use Filament\Forms\Form;
use Assist\Division\Models\Division;
use Filament\Forms\Components\Radio;
use App\Filament\Fields\TiptapEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Assist\KnowledgeBase\Models\KnowledgeBaseStatus;
use Assist\KnowledgeBase\Models\KnowledgeBaseQuality;
use Assist\KnowledgeBase\Models\KnowledgeBaseCategory;
use Assist\KnowledgeBase\Filament\Resources\KnowledgeBaseItemResource;

class CreateKnowledgeBaseItem extends CreateRecord
{
    protected static string $resource = KnowledgeBaseItemResource::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('question')
                    ->label('Question/Issue/Feature')
                    ->translateLabel()
                    ->required()
                    ->string(),
                Select::make('quality_id')
                    ->label('Quality')
                    ->translateLabel()
                    ->relationship('quality', 'name')
                    ->searchable()
                    ->preload()
                    ->exists((new KnowledgeBaseQuality())->getTable(), (new KnowledgeBaseQuality())->getKeyName()),
                Select::make('status_id')
                    ->label('Status')
                    ->relationship('status', 'name')
                    ->searchable()
                    ->preload()
                    ->exists((new KnowledgeBaseStatus())->getTable(), (new KnowledgeBaseStatus())->getKeyName()),
                Select::make('category_id')
                    ->label('Category')
                    ->translateLabel()
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->exists((new KnowledgeBaseCategory())->getTable(), (new KnowledgeBaseCategory())->getKeyName()),
                Radio::make('public')
                    ->label('Public')
                    ->translateLabel()
                    ->boolean()
                    ->default(false)
                    ->rules(['boolean']),
                Select::make('division')
                    ->label('Division')
                    ->translateLabel()
                    ->relationship('division', 'name')
                    ->searchable(['name', 'code'])
                    ->preload()
                    ->exists((new Division())->getTable(), (new Division())->getKeyName()),
                TiptapEditor::make('solution')
                    ->label('Solution')
                    ->translateLabel()
                    ->columnSpanFull()
                    ->extraInputAttributes(['style' => 'min-height: 12rem;'])
                    ->string(),
                TiptapEditor::make('notes')
                    ->label('Notes')
                    ->translateLabel()
                    ->columnSpanFull()
                    ->extraInputAttributes(['style' => 'min-height: 12rem;'])
                    ->string(),
            ]);
    }
}
