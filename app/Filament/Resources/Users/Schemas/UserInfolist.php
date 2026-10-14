<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas akun')->schema([
                    TextEntry::make('name')->label('Nama'),
                    TextEntry::make('email')->label('Email'),
                ])->columns(2)->columnSpanFull(),
                Section::make('Akses dan verifikasi')
                    ->description('Peran administrator dan verifikasi email adalah dua status yang berbeda.')
                    ->schema([
                        TextEntry::make('is_admin')->label('Peran')->formatStateUsing(fn (bool $state): string => $state ? 'Administrator' : 'Pengguna'),
                        TextEntry::make('email_verified_at')->label('Email diverifikasi')->dateTime('d/m/Y H:i')->placeholder('Belum terverifikasi'),
                        TextEntry::make('created_at')->label('Terdaftar')->dateTime('d/m/Y H:i'),
                    ])->columns(2)->columnSpanFull(),
            ]);
    }
}
