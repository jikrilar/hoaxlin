<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('is_admin')->label('Peran')->formatStateUsing(fn (bool $state): string => $state ? 'Administrator' : 'Pengguna'),
                TextColumn::make('email_verified_at')
                    ->label('Verifikasi email')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Terverifikasi' : 'Belum')
                    ->color(fn (?string $state): string => filled($state) ? 'success' : 'gray')
                    ->tooltip(fn (?string $state, User $record): string => $record->email_verified_at !== null
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
            ->recordActions([
                ViewAction::make()->label('Lihat'),
                EditAction::make()->label('Edit'),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->label('Hapus pengguna terpilih')
                    ->modalHeading('Hapus pengguna terpilih?')
                    ->modalDescription(fn (DeleteBulkAction $action): string => 'Anda memilih '.$action->getSelectedRecordsQuery()->count().' pengguna. Akun dan riwayat pemeriksaannya akan dihapus. Tindakan ini tidak dapat dibatalkan.')
                    ->modalSubmitActionLabel('Hapus pengguna terpilih'),
            ])
            ->emptyStateHeading('Tidak ada pengguna yang ditampilkan')
            ->emptyStateDescription('Tambahkan pengguna atau ubah pencarian dan filter untuk melihat akun lain.');
    }
}
