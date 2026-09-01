<?php

namespace App\Filament\Resources\Submissions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Submission')
                    ->schema([
                        Select::make('user_id')->label('Pengguna')->relationship('user', 'name')->searchable()->preload()->nullable(),
                        Select::make('input_type')->label('Jenis')->options(['text' => 'Teks', 'image' => 'Gambar', 'video' => 'Video', 'url' => 'URL'])->required(),
                        Select::make('status')->label('Status')->options(['pending' => 'Menunggu', 'processing' => 'Diproses', 'completed' => 'Selesai', 'failed' => 'Gagal'])->required(),
                        TextInput::make('source_url')->label('URL sumber')->url()->maxLength(2048)->columnSpanFull(),
                        TextInput::make('media_path')->label('Path media')->maxLength(255)->columnSpanFull(),
                        Textarea::make('raw_input')->label('Input asli')->columnSpanFull(),
                        Textarea::make('extracted_text')->label('Teks ekstraksi')->columnSpanFull(),
                        Textarea::make('translated_text')->label('Teks terjemahan untuk IndoBERT')->disabled()->columnSpanFull(),
                        Textarea::make('failure_reason')->label('Alasan gagal')->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
