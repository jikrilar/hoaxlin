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
use UnitEnum;

class DatasetResource extends Resource
{
    public const CURATION_NOTICE = 'Kumpulan teks berlabel yang disusun admin. Menambah atau mengubah data di sini tidak melatih ulang dan tidak mengganti model aktif.';

    protected static ?string $model = Dataset::class;

    protected static ?string $navigationLabel = 'Data Kurasi';

    protected static ?string $modelLabel = 'data kurasi';

    protected static ?string $pluralModelLabel = 'data kurasi';

    protected static UnitEnum|string|null $navigationGroup = 'Model AI';

    protected static ?int $navigationSort = 50;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function labelOptions(): array
    {
        return ['valid' => 'Valid', 'hoax' => 'Hoaks', 'meragukan' => 'Meragukan'];
    }

    public static function labelText(string $state): string
    {
        return static::labelOptions()[$state] ?? $state;
    }

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
