<?php

namespace App\Filament\Resources\DetectionResults;

use App\Filament\Resources\DetectionResults\Pages\ListDetectionResults;
use App\Filament\Resources\DetectionResults\Pages\ViewDetectionResult;
use App\Filament\Resources\DetectionResults\Schemas\DetectionResultForm;
use App\Filament\Resources\DetectionResults\Schemas\DetectionResultInfolist;
use App\Filament\Resources\DetectionResults\Tables\DetectionResultsTable;
use App\Models\DetectionResult;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DetectionResultResource extends Resource
{
    protected static ?string $model = DetectionResult::class;

    protected static ?string $navigationLabel = 'Hasil Deteksi';

    protected static ?string $modelLabel = 'hasil deteksi';

    protected static ?string $pluralModelLabel = 'hasil deteksi';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DetectionResultForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DetectionResultInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DetectionResultsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDetectionResults::route('/'),
            'view' => ViewDetectionResult::route('/{record}'),
        ];
    }
}
