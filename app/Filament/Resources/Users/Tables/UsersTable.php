<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Support\TableCsvExport;
use App\Models\User;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable()->limit(60)->wrap(),
                TextColumn::make('email')->label('Email')->searchable()->sortable()->limit(80)->wrap(),
                TextColumn::make('is_admin')->label('Peran')->badge()->icon(fn (bool $state) => $state ? Heroicon::OutlinedShieldCheck : Heroicon::OutlinedUsers)->color(fn (bool $state): string => $state ? 'warning' : 'gray')->formatStateUsing(fn (bool $state): string => $state ? 'Administrator' : 'Pengguna'),
                // Filament skips formatting for a null state, which left the "Belum" badge
                // empty. Derive an explicit state from the record instead of the timestamp.
                TextColumn::make('email_verified_at')
                    ->label('Verifikasi email')
                    ->badge()
                    ->state(fn (User $record): string => $record->email_verified_at !== null ? 'Terverifikasi' : 'Belum')
                    ->color(fn (string $state): string => $state === 'Terverifikasi' ? 'success' : 'gray')
                    ->icon(fn (string $state) => $state === 'Terverifikasi' ? Heroicon::OutlinedCheckBadge : Heroicon::OutlinedClock)
                    ->tooltip(fn (User $record): string => $record->email_verified_at !== null
                        ? 'Terverifikasi pada '.$record->email_verified_at->format('d/m/Y H:i')
                        : 'Belum terverifikasi')
                    ->sortable()
                    ->wrap(),
                TextColumn::make('created_at')->label('Terdaftar')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_admin')->label('Administrator'),
                TernaryFilter::make('email_verified_at')->label('Email terverifikasi')
                    ->nullable(),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->persistFiltersInSession()
            ->recordActions([
                ViewAction::make()->label('Lihat'),
                EditAction::make()->label('Edit'),
            ])
            ->headerActions([
                // Names and emails only: password hashes and remember tokens are never selected.
                TableCsvExport::action(
                    name: 'exportCsv',
                    label: 'Ekspor CSV',
                    fileName: 'pengguna-'.now()->format('Y-m-d').'.csv',
                    header: ['ID', 'Nama', 'Email', 'Peran', 'Verifikasi email', 'Terdaftar'],
                    query: static fn ($query) => $query->select(['id', 'name', 'email', 'is_admin', 'email_verified_at', 'created_at']),
                    row: static fn (User $record): array => [
                        $record->id,
                        $record->name,
                        $record->email,
                        $record->is_admin ? 'Administrator' : 'Pengguna',
                        $record->email_verified_at === null ? 'Belum' : 'Terverifikasi '.$record->email_verified_at->format('Y-m-d H:i'),
                        $record->created_at?->format('Y-m-d H:i') ?? '',
                    ],
                ),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->label('Hapus pengguna terpilih')
                    ->modalHeading('Hapus pengguna terpilih?')
                    ->modalDescription(fn (DeleteBulkAction $action): string => 'Anda memilih '.$action->getSelectedRecordsQuery()->count().' pengguna. Akun dan riwayat pemeriksaannya akan dihapus. Tindakan ini tidak dapat dibatalkan.')
                    ->modalSubmitActionLabel('Hapus pengguna terpilih'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Tidak ada pengguna yang ditampilkan')
            ->emptyStateDescription('Tambahkan pengguna atau ubah pencarian dan filter untuk melihat akun lain.');
    }
}
