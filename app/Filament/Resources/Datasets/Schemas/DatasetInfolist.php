<?php

namespace App\Filament\Resources\Datasets\Schemas;

use App\Filament\Resources\Datasets\DatasetResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DatasetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Teks kurasi')->schema([
                    TextEntry::make('text')->label('Teks lengkap')->extraAttributes(['class' => 'whitespace-pre-line'])->columnSpanFull(),
                ])->columnSpanFull(),
                Section::make('Label, sumber, dan verifier')
                    ->description('Label katalog adalah catatan kurasi. Meragukan pada katalog bukan kelas training aktif BERT.')
                    ->schema([
                        TextEntry::make('label')->label('Label katalog')->formatStateUsing(DatasetResource::labelText(...)),
                        TextEntry::make('source')->label('Sumber')->placeholder('Sumber belum tercatat'),
                        TextEntry::make('verifier.name')->label('Diverifikasi oleh')->placeholder('Belum diverifikasi'),
                        TextEntry::make('created_at')->label('Dibuat')->dateTime('d/m/Y H:i'),
                        TextEntry::make('updated_at')->label('Diperbarui')->dateTime('d/m/Y H:i'),
                    ])->columns(2)->columnSpanFull(),
            ]);
    }
}
