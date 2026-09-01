<?php

namespace App\Filament\Resources\Submissions\Schemas;

use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class SubmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Text::make('id')->label('ID'),
                Text::make('user.name')->label('Pengguna')->placeholder('Tamu'),
                Text::make('input_type')->label('Jenis'),
                Text::make('status')->label('Status'),
                Text::make('source_url')->label('URL sumber')->placeholder('-')->columnSpanFull(),
                Text::make('media_path')->label('Path media')->placeholder('-')->columnSpanFull(),
                Text::make('raw_input')->label('Input asli')->placeholder('-')->columnSpanFull(),
                Text::make('extracted_text')->label('Teks ekstraksi')->placeholder('-')->columnSpanFull(),
                Text::make('source_language')->label('Bahasa sumber')->placeholder('-'),
                Text::make('translation_model')->label('Model terjemahan')->placeholder('-'),
                Text::make('translated_text')->label('Teks terjemahan untuk IndoBERT')->placeholder('-')->columnSpanFull(),
                Text::make('failure_reason')->label('Alasan gagal')->placeholder('-')->columnSpanFull(),
                Text::make('created_at')->label('Dibuat')->dateTime(),
            ]);
    }
}
