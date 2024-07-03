<?php

namespace AdvisingApp\StudentDataModel\Filament\Resources\BasicNeedsProgramResource\RelationManagers;

use Filament\Tables\Table;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DetachBulkAction;
use App\Filament\Tables\Columns\OpenSearch\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;

class BasicNeedsProgramsRelationManager extends RelationManager
{
    protected static string $relationship = 'basicNeedsPrograms';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('contact_email')
                    ->label('Email'),
                TextColumn::make('contact_phone')
                    ->label('Phone'),
            ])
            ->headerActions([
                AttachAction::make(),
            ])
            ->actions([
                DetachAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
