<?php

namespace App\Filament\Resources\AdminLogs;

use App\Filament\Resources\AdminLogs\Pages\ListAdminLogs;
use App\Filament\Resources\AdminLogs\Pages\ViewAdminLog;
use App\Filament\Support\MonitoringPresentation;
use App\Models\AdminLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                TextColumn::make('id')->label('ID catatan')->sortable(),
                TextColumn::make('admin.name')->label('Admin')->sortable()->placeholder('Admin tidak tersedia')->wrap(),
                TextColumn::make('action')->label('Tindakan')->formatStateUsing(MonitoringPresentation::action(...))->sortable()->wrap(),
                TextColumn::make('target_table')->label('Tabel target')->formatStateUsing(MonitoringPresentation::target(...))->sortable(),
                TextColumn::make('target_id')->label('ID target')->placeholder('ID target tidak tercatat')->sortable(),
                TextColumn::make('created_at')->label('Dicatat')->dateTime('d/m/Y H:i:s')->sortable(),
            ])
            ->filters([
                SelectFilter::make('action')->label('Tindakan')->options([
                    'create' => 'Buat', 'update' => 'Ubah', 'delete' => 'Hapus',
                ]),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat detail'),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Tidak ada log admin yang ditampilkan')
            ->emptyStateDescription('Ubah filter untuk melihat catatan tindakan lainnya.')
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tindakan administrasi')->schema([
                TextEntry::make('id')->label('ID catatan'),
                TextEntry::make('admin.name')->label('Admin')->placeholder('Admin tidak tersedia'),
                TextEntry::make('action')->label('Tindakan')->formatStateUsing(MonitoringPresentation::action(...)),
                TextEntry::make('target_table')->label('Tabel target')->formatStateUsing(MonitoringPresentation::target(...)),
                TextEntry::make('target_id')->label('ID target')->placeholder('ID target tidak tercatat'),
                TextEntry::make('created_at')->label('Dicatat')->dateTime('d/m/Y H:i:s'),
            ])->columns(2)->columnSpanFull(),
            Section::make('Metadata operasional')->description(MonitoringPresentation::METADATA_NOTICE)->schema([
                ViewEntry::make('metadata_summary')->label('Metadata yang dapat ditampilkan')
                    ->state(MonitoringPresentation::logMetadata(...))->view('filament.admin.resources.monitoring-metadata')->columnSpanFull(),
            ])->columnSpanFull(),
        ]);
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
