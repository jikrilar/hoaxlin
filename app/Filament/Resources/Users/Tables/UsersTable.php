<?php

namespace App\Filament\Resources\Users\Tables;

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
                TextColumn::make('email_verified_at')->label('Verifikasi email')->formatStateUsing(fn (): string => 'Terverifikasi')->placeholder('Belum terverifikasi')->wrap(),
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
