<?php

namespace App\Filament\Resources\DetectionResults\Schemas;

use App\Enums\ExplanationStatus;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Support\ReadOnlyPresentation;
use App\Models\DetectionResult;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DetectionResultInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Record classifier')
                    ->description('Skor keyakinan model berasal dari classifier, bukan persentase kebenaran, akurasi produk, atau similarity RAG. Meragukan adalah abstention; bukan bukti informasi benar atau palsu.')
                    ->schema([
                        TextEntry::make('record_context')->label('Konteks record')->state(ReadOnlyPresentation::resultContext(...))->columnSpanFull(),
                        TextEntry::make('submission_id')->label('ID submission')
                            ->url(fn (DetectionResult $record): ?string => $record->submission === null ? null : SubmissionResource::getUrl('view', ['record' => $record->submission])),
                        TextEntry::make('submission.status')->label('Status submission')->formatStateUsing(ReadOnlyPresentation::status(...))->placeholder('Submission tidak tersedia'),
                        TextEntry::make('label')->label('Prediksi tersimpan')->formatStateUsing(fn (DetectionResult $record): string => ReadOnlyPresentation::resultLabel($record))->columnSpanFull(),
                        TextEntry::make('confidence_score')->label('Skor keyakinan model')->formatStateUsing(ReadOnlyPresentation::confidence(...))->placeholder('Skor tidak tersedia'),
                    ])->columns(2)->columnSpanFull(),
                Section::make('Penjelasan')->schema([
                    TextEntry::make('explanation_status')->label('Status penjelasan')->formatStateUsing(fn (?string $state): string => ExplanationStatus::tryFrom($state ?? '')?->label() ?? 'Status belum tersedia')->placeholder('Status belum tersedia'),
                    TextEntry::make('explanation_content')->label('Isi penjelasan')->state(ReadOnlyPresentation::explanation(...))->extraAttributes(['class' => 'whitespace-pre-line'])->columnSpanFull(),
                ])->columnSpanFull(),
                Section::make('Metadata model')->schema([
                    TextEntry::make('model_version')->label('Versi model classifier')->placeholder('Versi model tidak tersedia'),
                    TextEntry::make('created_at')->label('Dibuat')->dateTime('d/m/Y H:i'),
                ])->columns(2)->columnSpanFull(),
            ]);
    }
}
