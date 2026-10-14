<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas akun')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255)
                            ->validationMessages(['required' => 'Masukkan nama pengguna.', 'max' => 'Nama maksimal 255 karakter.']),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->validationMessages(['required' => 'Masukkan alamat email.', 'email' => 'Masukkan alamat email yang valid.', 'unique' => 'Email ini sudah digunakan oleh akun lain.', 'max' => 'Email maksimal 255 karakter.']),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Akses dan verifikasi')
                    ->description('Peran administrator memberi akses ke panel admin. Verifikasi email dicatat terpisah dan tidak diubah oleh form ini.')
                    ->schema([
                        Toggle::make('is_admin')
                            ->label('Administrator')
                            ->helperText('Peran tidak dapat diturunkan selama akun masih menjadi verifier katalog dataset.'),
                        TextEntry::make('email_verified_at')
                            ->label('Email diverifikasi')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Belum terverifikasi'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Kata sandi')
                    ->description(fn (string $operation): string => $operation === 'create' ? 'Tetapkan kata sandi untuk akun baru, minimal 8 karakter.' : 'Isi hanya untuk mengganti kata sandi. Biarkan kosong untuk mempertahankan kata sandi saat ini.')
                    ->schema([
                        TextInput::make('password')
                            ->label('Kata sandi')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->minLength(8)
                            ->validationMessages(['required' => 'Tetapkan kata sandi untuk akun baru.', 'min' => 'Kata sandi minimal 8 karakter.']),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
