<?php

namespace App\Filament\Resources\Feedback\Schemas;

use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Support\ReadOnlyPresentation;
use App\Models\Feedback;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FeedbackInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Penilaian pengguna')->schema([
                    TextEntry::make('submission_id')->label('ID submission')
                        ->url(fn (Feedback $record): ?string => $record->submission === null ? null : SubmissionResource::getUrl('view', ['record' => $record->submission])),
                    TextEntry::make('user.name')->label('Pengguna')->placeholder('Pengguna tidak tersedia'),
                    TextEntry::make('is_correct')->label('Penilaian terhadap prediksi')->formatStateUsing(ReadOnlyPresentation::assessment(...)),
                    TextEntry::make('created_at')->label('Dikirim')->dateTime('d/m/Y H:i'),
                ])->columns(2)->columnSpanFull(),
                Section::make('Komentar')->schema([
                    TextEntry::make('comment')->label('Komentar pengguna')->placeholder('Tidak ada komentar')->extraAttributes(['class' => 'whitespace-pre-line'])->columnSpanFull(),
                ])->columnSpanFull(),
            ]);
    }
}
