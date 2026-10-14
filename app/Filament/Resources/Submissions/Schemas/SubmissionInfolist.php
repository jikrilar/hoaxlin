<?php

namespace App\Filament\Resources\Submissions\Schemas;

use App\Filament\Resources\DetectionResults\DetectionResultResource;
use App\Filament\Support\ReadOnlyPresentation;
use App\Models\Submission;
use App\View\Presenters\DetectionPresentation;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Submission dan status pipeline')
                    ->description(ReadOnlyPresentation::RESULT_NOTICE)
                    ->schema([
                        TextEntry::make('id')->label('ID submission'),
                        TextEntry::make('user.name')->label('Pengguna')->placeholder('Tamu'),
                        TextEntry::make('input_type')->label('Jenis input')->formatStateUsing(fn (?string $state): string => DetectionPresentation::inputType($state)),
                        TextEntry::make('status')->label('Status submission')->formatStateUsing(ReadOnlyPresentation::status(...)),
                        TextEntry::make('processing_stage')->label('Tahap pipeline')->formatStateUsing(ReadOnlyPresentation::stage(...))->placeholder('Tahap belum tercatat'),
                        TextEntry::make('classifier_record')->label('Hasil classifier')
                            ->state(fn (Submission $record): string => ReadOnlyPresentation::submissionResult($record))
                            ->url(fn (Submission $record): ?string => $record->detectionResult === null ? null : DetectionResultResource::getUrl('view', ['record' => $record->detectionResult]))
                            ->columnSpanFull(),
                        TextEntry::make('failure_reason')->label('Alasan gagal')->placeholder('Tidak ada alasan gagal tercatat')->extraAttributes(['class' => 'whitespace-pre-line'])->columnSpanFull(),
                        TextEntry::make('created_at')->label('Dikirim')->dateTime('d/m/Y H:i'),
                    ])->columns(2)->columnSpanFull(),
                Section::make('Input sumber')->schema([
                    TextEntry::make('source_url')->label('URL sumber')->placeholder('URL sumber tidak tersedia'),
                    TextEntry::make('media_path')->label('Path media privat')->placeholder('Media tidak tercatat'),
                    TextEntry::make('raw_input')->label('Input asli')->placeholder('Input teks tidak tersedia')->extraAttributes(['class' => 'whitespace-pre-line']),
                    TextEntry::make('extracted_text')->label('Teks ekstraksi')->placeholder('Teks ekstraksi tidak tersedia')->extraAttributes(['class' => 'whitespace-pre-line']),
                ])->columnSpanFull(),
                Section::make('Terjemahan dan provenance')
                    ->description('Terjemahan merupakan teks untuk analisis IndoBERT. Metadata yang tidak tersimpan tidak diasumsikan tersedia.')
                    ->schema([
                        TextEntry::make('source_language')->label('Bahasa sumber')->placeholder('Belum tercatat'),
                        TextEntry::make('translation_provider')->label('Provider terjemahan')->placeholder('Belum tercatat'),
                        TextEntry::make('translation_model')->label('Model terjemahan')->placeholder('Belum tercatat'),
                        TextEntry::make('translated_text')->label('Teks terjemahan untuk IndoBERT')->placeholder('Teks terjemahan tidak tersedia')->extraAttributes(['class' => 'whitespace-pre-line'])->columnSpanFull(),
                    ])->columns(2)->columnSpanFull(),
            ]);
    }
}
