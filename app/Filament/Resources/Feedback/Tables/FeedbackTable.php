<?php

namespace App\Filament\Resources\Feedback\Tables;

use App\Filament\Support\ReadOnlyPresentation;
use App\Filament\Support\TableCsvExport;
use App\Models\Feedback;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FeedbackTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('submission_id')->label('ID pemeriksaan')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.name')->label('Pengguna')->searchable()->placeholder('Pengguna tidak tersedia')->wrap(),
                TextColumn::make('is_correct')->label('Penilaian pengguna')->badge()->icon(fn (bool $state) => ReadOnlyPresentation::assessmentIcon($state))->color(fn (bool $state): string => ReadOnlyPresentation::assessmentColor($state))->formatStateUsing(ReadOnlyPresentation::assessment(...))->wrap(),
                TextColumn::make('comment')->label('Cuplikan komentar')->limit(60)->searchable()->placeholder('Tidak ada komentar')->wrap(),
                TextColumn::make('created_at')->label('Dikirim')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_correct')->label('Penilaian pengguna')->trueLabel('Dinilai sesuai')->falseLabel('Dinilai tidak sesuai'),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->persistFiltersInSession()
            ->recordActions([
                ViewAction::make()->label('Lihat detail'),
            ])
            ->headerActions([
                // The comment is user text: it is exported only because the table already
                // shows it to admins, and it goes through the shared formula sanitizer.
                TableCsvExport::action(
                    name: 'exportCsv',
                    label: 'Ekspor CSV',
                    fileName: 'umpan-balik-'.now()->format('Y-m-d').'.csv',
                    header: ['ID', 'ID pemeriksaan', 'Penilaian pengguna', 'Komentar', 'Dikirim'],
                    query: static fn ($query) => $query->select(['id', 'submission_id', 'is_correct', 'comment', 'created_at']),
                    row: static fn (Feedback $record): array => [
                        $record->id,
                        $record->submission_id,
                        ReadOnlyPresentation::assessment($record->is_correct),
                        $record->comment ?? '',
                        $record->created_at?->format('Y-m-d H:i') ?? '',
                    ],
                ),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Tidak ada umpan balik yang ditampilkan')
            ->emptyStateDescription('Ubah pencarian atau filter untuk melihat penilaian pengguna lainnya.');
    }
}
