<?php

namespace App\Filament\Resources\AdminLogs;

use App\Filament\Resources\AdminLogs\Pages\ListAdminLogs;
use App\Filament\Resources\AdminLogs\Pages\ViewAdminLog;
use App\Models\AdminLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AdminLogResource extends Resource
{
    protected static ?string $model = AdminLog::class;

    protected static ?string $navigationLabel = 'Admin Logs';
    protected static ?string $modelLabel = 'log';
    protected static ?string $pluralModelLabel = 'admin logs';
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedShieldCheck;
    protected static ?int $navigationSort = 91;
    protected static UnitEnum|string|null $navigationGroup = 'Monitoring';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('id')->sortable(),
                \Filament\Tables\Columns\TextColumn::make('admin.name')->label('Admin')->sortable(),
                \Filament\Tables\Columns\TextColumn::make('action')->badge()->sortable(),
                \Filament\Tables\Columns\TextColumn::make('target_table')->label('Target')->sortable(),
                \Filament\Tables\Columns\TextColumn::make('target_id')->label('ID')->sortable(),
                \Filament\Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('action')->options([
                    'create' => 'Create', 'update' => 'Update', 'delete' => 'Delete',
                ]),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminLogs::route('/'),
            'view' => ViewAdminLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
