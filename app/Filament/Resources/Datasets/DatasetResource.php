<?php

namespace App\Filament\Resources\Datasets;

use App\Filament\Resources\Datasets\Pages\CreateDataset;
use App\Filament\Resources\Datasets\Pages\EditDataset;
use App\Filament\Resources\Datasets\Pages\ListDatasets;
use App\Filament\Resources\Datasets\Pages\ViewDataset;
use App\Filament\Resources\Datasets\Schemas\DatasetForm;
use App\Filament\Resources\Datasets\Schemas\DatasetInfolist;
use App\Filament\Resources\Datasets\Tables\DatasetsTable;
use App\Models\Dataset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DatasetResource extends Resource
{
    protected static ?string $model = Dataset::class;

    protected static ?string $navigationLabel = 'Dataset';

    protected static ?string $modelLabel = 'dataset';

    protected static ?string $pluralModelLabel = 'dataset';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DatasetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DatasetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DatasetsTable::configure($table);
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
            'index' => ListDatasets::route('/'),
            'create' => CreateDataset::route('/create'),
            'view' => ViewDataset::route('/{record}'),
            'edit' => EditDataset::route('/{record}/edit'),
        ];
    }
}
