<?php

namespace App\Filament\Resources\Datasets\Schemas;

use App\Filament\Resources\Datasets\DatasetResource;
use App\Rules\AdminUser;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class DatasetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Teks kurasi')
                    ->schema([
                        Textarea::make('text')
                            ->label('Teks informasi')
                            ->required()
                            ->minLength(10)
                            ->rows(8)
                            ->helperText('Minimal 10 karakter. Simpan teks lengkap; daftar hanya menampilkan cuplikan.')
                            ->validationMessages(['required' => 'Masukkan teks yang akan dikurasi.', 'min' => 'Teks kurasi minimal 10 karakter.'])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Label, sumber, dan verifier')
                    ->description('Label katalog adalah catatan kurasi. Meragukan pada katalog bukan kelas training aktif BERT.')
                    ->schema([
                        Select::make('label')
                            ->label('Label katalog')
                            ->options(DatasetResource::labelOptions())
                            ->required()
                            ->validationMessages(['required' => 'Pilih label katalog.', 'in' => 'Pilih label katalog yang tersedia.']),
                        TextInput::make('source')
                            ->label('Sumber')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Nama publikasi atau rujukan asal, maksimal 255 karakter.')
                            ->validationMessages(['required' => 'Masukkan sumber data kurasi.', 'max' => 'Sumber maksimal 255 karakter.']),
                        Select::make('verified_by')
                            ->label('Diverifikasi oleh')
                            ->relationship(
                                'verifier',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->admins(),
                            )
                            ->rule(new AdminUser)
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Opsional. Hanya akun administrator yang dapat dipilih sebagai verifier katalog.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
