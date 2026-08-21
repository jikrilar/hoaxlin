<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Text::make('name')->label('Nama'),
                Text::make('email')->label('Email'),
                Text::make('is_admin')->label('Administrator')->formatStateUsing(fn (bool $state): string => $state ? 'Ya' : 'Tidak'),
                Text::make('email_verified_at')->label('Email diverifikasi')->dateTime(),
                Text::make('created_at')->label('Terdaftar')->dateTime(),
            ]);
    }
}
