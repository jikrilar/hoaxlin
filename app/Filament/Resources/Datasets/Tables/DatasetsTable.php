<?php

namespace App\Filament\Resources\Datasets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DatasetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('text')->label('Teks')->limit(70)->searchable(),
                TextColumn::make('label')->label('Label')->badge()->sortable(),
                TextColumn::make('source')->label('Sumber')->searchable()->sortable(),
                TextColumn::make('verifier.name')->label('Diverifikasi oleh')->placeholder('Belum diverifikasi'),
                TextColumn::make('created_at')->label('Dibuat')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('label')->options(['valid' => 'Valid', 'hoax' => 'Hoax', 'meragukan' => 'Meragukan']),
                SelectFilter::make('verified_by')->label('Verifikasi')->relationship('verifier', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
