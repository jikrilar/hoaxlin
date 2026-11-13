<?php

namespace App\Filament\Resources\Submissions\Tables;

use App\Enums\SubmissionStatus;
use App\Filament\Support\ReadOnlyPresentation;
use App\Filament\Support\TableCsvExport;
use App\Models\Submission;
use App\View\Presenters\DetectionPresentation;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.name')->label('Pengguna')->placeholder('Tamu')->searchable()->wrap()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('input_type')->label('Jenis input')->formatStateUsing(fn (?string $state): string => DetectionPresentation::inputType($state))->sortable()->wrap(),
                TextColumn::make('content')->label('Cuplikan input')->limit(60)->searchable(['raw_input', 'extracted_text', 'source_url'])->placeholder('Teks belum tersedia')->wrap(),
                TextColumn::make('status')->label('Status pemeriksaan')->badge()->icon(fn (?string $state) => ReadOnlyPresentation::statusIcon($state))->color(fn (?string $state): string => ReadOnlyPresentation::statusColor($state))->formatStateUsing(ReadOnlyPresentation::status(...))->sortable(),
                TextColumn::make('detectionResult.label')->label('Hasil classifier')->badge()->icon(fn (Submission $record) => ReadOnlyPresentation::submissionResultIcon($record))->color(fn (Submission $record): string => ReadOnlyPresentation::submissionResultColor($record))->state(fn (Submission $record): string => ReadOnlyPresentation::submissionResult($record))->wrap(),
                TextColumn::make('processing_stage')->label('Tahap akhir')->formatStateUsing(ReadOnlyPresentation::stage(...))->placeholder('Tahap belum tercatat')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_error_code')->label('Kode kendala')->placeholder('Tidak tercatat')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Dikirim')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('input_type')->label('Jenis input')->options(['text' => 'Teks', 'image' => 'Gambar', 'video' => 'Video', 'url' => 'Tautan artikel']),
                SelectFilter::make('status')->label('Status pemeriksaan')->options(SubmissionStatus::options()),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->persistFiltersInSession()
            ->recordActions([
                ViewAction::make()->label('Lihat detail'),
            ])
            ->headerActions([
                // Metadata only: the raw input, extracted text and media stay out of the file.
                TableCsvExport::action(
                    name: 'exportCsv',
                    label: 'Ekspor CSV',
                    fileName: 'pemeriksaan-'.now()->format('Y-m-d').'.csv',
                    header: ['ID', 'Jenis input', 'Status', 'Dikirim', 'Tahap akhir', 'Kode kendala'],
                    query: static fn ($query) => $query->select(['id', 'input_type', 'status', 'processing_stage', 'last_error_code', 'created_at']),
                    row: static fn (Submission $record): array => [
                        $record->id,
                        DetectionPresentation::inputType($record->input_type),
                        ReadOnlyPresentation::status($record->status),
                        $record->created_at?->format('Y-m-d H:i') ?? '',
                        ReadOnlyPresentation::stage($record->processing_stage),
                        $record->last_error_code ?? '',
                    ],
                ),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Tidak ada pemeriksaan yang ditampilkan')
            ->emptyStateDescription('Ubah pencarian atau filter untuk melihat catatan input lainnya.');
    }
}
