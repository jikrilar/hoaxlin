<?php

namespace App\Filament\Resources\Submissions\Tables;

use App\Enums\SubmissionStatus;
use App\Filament\Support\ReadOnlyPresentation;
use App\Models\Submission;
use App\View\Presenters\DetectionPresentation;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('user.name')->label('Pengguna')->placeholder('Tamu')->searchable()->wrap(),
                TextColumn::make('input_type')->label('Jenis input')->formatStateUsing(fn (?string $state): string => DetectionPresentation::inputType($state))->sortable()->wrap(),
                TextColumn::make('content')->label('Cuplikan input')->limit(60)->searchable(['raw_input', 'extracted_text', 'source_url'])->placeholder('Teks belum tersedia')->wrap(),
                TextColumn::make('status')->label('Status submission')->formatStateUsing(ReadOnlyPresentation::status(...))->sortable(),
                TextColumn::make('detectionResult.label')->label('Hasil classifier')->state(fn (Submission $record): string => ReadOnlyPresentation::submissionResult($record))->wrap(),
                TextColumn::make('created_at')->label('Dikirim')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('input_type')->label('Jenis input')->options(['text' => 'Teks', 'image' => 'Gambar', 'video' => 'Video', 'url' => 'Tautan artikel']),
                SelectFilter::make('status')->label('Status submission')->options(SubmissionStatus::options()),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat detail'),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Tidak ada submission yang ditampilkan')
            ->emptyStateDescription('Ubah pencarian atau filter untuk melihat catatan input lainnya.');
    }
}
