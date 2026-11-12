<?php

namespace App\Filament\Resources\Submissions\Schemas;

use App\Filament\Resources\DetectionResults\DetectionResultResource;
use App\Filament\Support\AdminGlossary;
use App\Filament\Support\ReadOnlyPresentation;
use App\Models\Submission;
use App\View\Presenters\DetectionPresentation;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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
                        TextEntry::make('processing_stage')->label('Tahap pipeline')->hintIcon(Heroicon::OutlinedQuestionMarkCircle)->hintIconTooltip(AdminGlossary::explain('pipeline_stage'))->formatStateUsing(ReadOnlyPresentation::stage(...))->placeholder('Tahap belum tercatat'),
                        TextEntry::make('classifier_record')->label('Hasil classifier')
                            ->state(fn (Submission $record): string => ReadOnlyPresentation::submissionResult($record))
                            ->url(fn (Submission $record): ?string => $record->detectionResult === null ? null : DetectionResultResource::getUrl('view', ['record' => $record->detectionResult]))
                            ->columnSpanFull(),
                        TextEntry::make('failure_reason')->label('Alasan gagal')->placeholder('Tidak ada alasan gagal tercatat')->extraAttributes(['class' => 'whitespace-pre-line'])->columnSpanFull(),
                        TextEntry::make('created_at')->label('Dikirim')->dateTime('d/m/Y H:i'),
                    ])->columns(2)->columnSpanFull(),
                Section::make('Hasil classifier dan provenance teknis')
                    ->description('Data internal untuk audit. Angka dan versi ini tidak ditampilkan pada halaman hasil publik.')
                    ->schema([
                        TextEntry::make('classifier_record')->label('Prediksi tersimpan')
                            ->state(fn (Submission $record): string => ReadOnlyPresentation::submissionResult($record))
                            ->placeholder('Belum tersedia'),
                        TextEntry::make('detectionResult.confidence_score')->label('Skor keyakinan model')
                            ->formatStateUsing(fn (mixed $state): string => ReadOnlyPresentation::confidence($state) ?? 'Belum tersedia')
                            ->placeholder('Belum tersedia'),
                        TextEntry::make('detectionResult.model_version')->label('Versi model classifier')->placeholder('Versi model tidak tersedia'),
                        TextEntry::make('detectionResult.inference_ms')->label('Waktu inferensi')->suffix(' ms')->placeholder('Tidak tercatat'),
                        TextEntry::make('detectionResult.classifier_cached')->label('Cache classifier')
                            ->formatStateUsing(fn (?bool $state): string => $state === null ? 'Tidak tercatat' : ($state ? 'Digunakan' : 'Tidak digunakan')),
                        TextEntry::make('detectionResult.explanation_model')->label('Model penjelasan')->placeholder('Tidak tercatat'),
                        TextEntry::make('detectionResult.explanation_cached')->label('Cache penjelasan')
                            ->formatStateUsing(fn (?bool $state): string => $state === null ? 'Tidak tercatat' : ($state ? 'Digunakan' : 'Tidak digunakan')),
                        TextEntry::make('pipeline_version')->label('Versi pipeline')->placeholder('Tidak tercatat'),
                        TextEntry::make('processing_completed_at')->label('Pemrosesan selesai')->dateTime('d/m/Y H:i')->placeholder('Tidak tercatat'),
                    ])->columns(3)->columnSpanFull(),
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
                        TextEntry::make('translation_cached')->label('Cache terjemahan')
                            ->formatStateUsing(fn (?bool $state): string => $state === null ? 'Tidak tercatat' : ($state ? 'Digunakan' : 'Tidak digunakan')),
                        TextEntry::make('translated_text')->label('Teks terjemahan untuk IndoBERT')->placeholder('Teks terjemahan tidak tersedia')->extraAttributes(['class' => 'whitespace-pre-line'])->columnSpanFull(),
                    ])->columns(2)->columnSpanFull(),
            ]);
    }
}
