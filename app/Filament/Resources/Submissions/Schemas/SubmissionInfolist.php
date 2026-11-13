<?php

namespace App\Filament\Resources\Submissions\Schemas;

use App\Enums\ExplanationStatus;
use App\Filament\Support\AdminGlossary;
use App\Filament\Support\MonitoringPresentation;
use App\Filament\Support\ReadOnlyPresentation;
use App\Models\Submission;
use App\View\Presenters\DetectionPresentation;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Read-only detail for one submission (T3.5).
 *
 * This page replaces the standalone Hasil Deteksi and Processing Events screens,
 * so every field those screens exposed must be reachable here. Nothing new is
 * exposed: input text and private media are shown exactly as they already were,
 * the guest capability hash is never selected, and timeline metadata goes through
 * the same allowlist projector the old events screen used.
 */
class SubmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ringkasan')
                    ->description(ReadOnlyPresentation::RESULT_NOTICE)
                    ->schema([
                        TextEntry::make('id')->label('ID pemeriksaan'),
                        TextEntry::make('user.name')->label('Pengguna')->placeholder('Tamu'),
                        TextEntry::make('input_type')->label('Jenis input')->formatStateUsing(fn (?string $state): string => DetectionPresentation::inputType($state)),
                        TextEntry::make('status')->label('Status')->badge()
                            ->icon(fn (?string $state) => ReadOnlyPresentation::statusIcon($state))
                            ->color(fn (?string $state): string => ReadOnlyPresentation::statusColor($state))
                            ->formatStateUsing(ReadOnlyPresentation::status(...)),
                        TextEntry::make('processing_stage')->label('Tahap pipeline')
                            ->hintIcon(Heroicon::OutlinedQuestionMarkCircle)
                            ->hintIconTooltip(AdminGlossary::explain('pipeline_stage'))
                            ->formatStateUsing(ReadOnlyPresentation::stage(...))
                            ->placeholder('Tahap belum tercatat'),
                        TextEntry::make('created_at')->label('Dikirim')->dateTime('d/m/Y H:i'),
                        TextEntry::make('processing_completed_at')->label('Pemrosesan selesai')->dateTime('d/m/Y H:i')->placeholder('Tidak tercatat'),
                        TextEntry::make('pipeline_version')->label('Versi pipeline')->placeholder('Tidak tercatat'),
                        TextEntry::make('failure_reason')->label('Alasan gagal')->placeholder('Tidak ada alasan gagal tercatat')
                            ->extraAttributes(['class' => 'whitespace-pre-line'])->columnSpanFull(),
                        ViewEntry::make('user_view')->label('Tampilan pengguna')
                            ->view('filament.admin.resources.user-view-link')
                            ->columnSpanFull(),
                    ])->columns(2)->columnSpanFull(),

                Section::make('Hasil')
                    ->description('Prediksi model, bukan verifikasi kebenaran. Skor keyakinan bukan persentase kebenaran atau akurasi produk; meragukan adalah abstention.')
                    ->schema([
                        TextEntry::make('result_context')->label('Konteks record')
                            ->state(fn (Submission $record): string => $record->detectionResult === null
                                ? 'Record classifier belum tersedia.'
                                : ReadOnlyPresentation::resultContext($record->detectionResult))
                            ->columnSpanFull(),
                        TextEntry::make('detectionResult.label')->label('Prediksi tersimpan')
                            ->badge()
                            ->icon(fn (Submission $record) => ReadOnlyPresentation::submissionResultIcon($record))
                            ->color(fn (Submission $record): string => ReadOnlyPresentation::submissionResultColor($record))
                            ->state(fn (Submission $record): string => ReadOnlyPresentation::submissionResult($record)),
                        TextEntry::make('detectionResult.confidence_score')->label('Skor keyakinan model')
                            ->formatStateUsing(fn (mixed $state): string => ReadOnlyPresentation::confidence($state) ?? 'Belum tersedia')
                            ->placeholder('Belum tersedia'),
                        TextEntry::make('detectionResult.model_version')->label('Versi model classifier')->placeholder('Versi model tidak tersedia'),
                        TextEntry::make('detectionResult.inference_ms')->label('Waktu inferensi')->suffix(' ms')->placeholder('Tidak tercatat'),
                        TextEntry::make('detectionResult.classifier_cached')->label('Cache classifier')
                            ->formatStateUsing(fn (?bool $state): string => $state === null ? 'Tidak tercatat' : ($state ? 'Digunakan' : 'Tidak digunakan')),
                        TextEntry::make('detectionResult.explanation_status')->label('Status penjelasan')
                            ->formatStateUsing(fn (?string $state): string => ExplanationStatus::tryFrom($state ?? '')?->label() ?? 'Status belum tersedia')
                            ->placeholder('Status belum tersedia'),
                        TextEntry::make('detectionResult.explanation_model')->label('Model penjelasan')->placeholder('Tidak tercatat'),
                        TextEntry::make('detectionResult.explanation_cached')->label('Cache penjelasan')
                            ->formatStateUsing(fn (?bool $state): string => $state === null ? 'Tidak tercatat' : ($state ? 'Digunakan' : 'Tidak digunakan')),
                        TextEntry::make('explanation_content')->label('Isi penjelasan')
                            ->state(fn (Submission $record): string => $record->detectionResult === null
                                ? 'Belum ada penjelasan.'
                                : ReadOnlyPresentation::explanation($record->detectionResult))
                            ->extraAttributes(['class' => 'whitespace-pre-line'])->columnSpanFull(),
                    ])->columns(3)->columnSpanFull(),

                Section::make('Rujukan')
                    ->description('Referensi dari basis pengetahuan yang dipakai saat analisis, urut peringkat. Keterkaitan sumber tidak otomatis mendukung prediksi model; kemiripan bukan kebenaran.')
                    ->schema([
                        ViewEntry::make('evidence_list')->label('Daftar rujukan')
                            ->view('filament.admin.resources.submission-evidence')
                            ->columnSpanFull(),
                    ])->columnSpanFull(),

                Section::make('Timeline pemrosesan')
                    ->description(MonitoringPresentation::EVENTS_NOTICE)
                    ->schema([
                        ViewEntry::make('timeline')->label('Catatan tahap')
                            ->view('filament.admin.resources.submission-timeline')
                            ->columnSpanFull(),
                    ])->columnSpanFull(),

                Section::make('Terjemahan')
                    ->description('Terjemahan merupakan teks untuk analisis IndoBERT. Metadata yang tidak tersimpan tidak diasumsikan tersedia.')
                    ->schema([
                        TextEntry::make('source_language')->label('Bahasa sumber')->placeholder('Belum tercatat'),
                        TextEntry::make('translation_provider')->label('Provider terjemahan')->placeholder('Belum tercatat'),
                        TextEntry::make('translation_model')->label('Model terjemahan')->placeholder('Belum tercatat'),
                        TextEntry::make('translation_cached')->label('Cache terjemahan')
                            ->formatStateUsing(fn (?bool $state): string => $state === null ? 'Tidak tercatat' : ($state ? 'Digunakan' : 'Tidak digunakan')),
                        TextEntry::make('translated_text')->label('Teks terjemahan untuk IndoBERT')
                            ->placeholder('Teks terjemahan tidak tersedia')
                            ->extraAttributes(['class' => 'whitespace-pre-line'])->columnSpanFull(),
                    ])->columns(2)->columnSpanFull(),

                Section::make('Input sumber')->schema([
                    TextEntry::make('source_url')->label('URL sumber')->placeholder('URL sumber tidak tersedia'),
                    TextEntry::make('media_path')->label('Path media privat')->placeholder('Media tidak tercatat'),
                    TextEntry::make('raw_input')->label('Input asli')->placeholder('Input teks tidak tersedia')->extraAttributes(['class' => 'whitespace-pre-line']),
                    TextEntry::make('extracted_text')->label('Teks ekstraksi')->placeholder('Teks ekstraksi tidak tersedia')->extraAttributes(['class' => 'whitespace-pre-line']),
                ])->columnSpanFull(),
            ]);
    }
}
