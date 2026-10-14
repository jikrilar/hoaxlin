<?php

namespace App\Filament\Resources\SubmissionProcessingEvents;

use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Filament\Resources\SubmissionProcessingEvents\Pages\ListSubmissionProcessingEvents;
use App\Filament\Resources\SubmissionProcessingEvents\Pages\ViewSubmissionProcessingEvent;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Support\MonitoringPresentation;
use App\Models\SubmissionProcessingEvent;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SubmissionProcessingEventResource extends Resource
{
    protected static ?string $model = SubmissionProcessingEvent::class;

    protected static ?string $navigationLabel = 'Processing Events';

    protected static ?string $modelLabel = 'event';

    protected static ?string $pluralModelLabel = 'processing events';

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 90;

    protected static UnitEnum|string|null $navigationGroup = 'Monitoring';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID catatan')->sortable(),
                TextColumn::make('submission.id')->label('Submission')->sortable(),
                TextColumn::make('stage')->label('Tahap')->state(fn (SubmissionProcessingEvent $record): ?string => $record->getRawOriginal('stage'))->formatStateUsing(MonitoringPresentation::stage(...))->sortable()->wrap(),
                TextColumn::make('outcome')->label('Hasil operasi')->state(fn (SubmissionProcessingEvent $record): ?string => $record->getRawOriginal('outcome'))->formatStateUsing(MonitoringPresentation::outcome(...))->sortable()->wrap(),
                TextColumn::make('service')->label('Layanan')->formatStateUsing(MonitoringPresentation::service(...))->placeholder('Layanan tidak tercatat'),
                TextColumn::make('attempt')->label('Percobaan')->sortable(),
                TextColumn::make('duration_ms')->label('Durasi')->formatStateUsing(MonitoringPresentation::duration(...))->placeholder('Durasi tidak tercatat')->sortable(),
                TextColumn::make('created_at')->label('Dicatat')->dateTime('d/m/Y H:i:s')->sortable(),
            ])
            ->filters([
                SelectFilter::make('stage')->label('Tahap')->options(ProcessingStage::options()),
                SelectFilter::make('outcome')->label('Hasil operasi')->options(EventOutcome::options()),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat detail'),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Tidak ada catatan pipeline yang ditampilkan')
            ->emptyStateDescription('Ubah filter untuk melihat catatan operasi lainnya.')
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Operasi pipeline')->schema([
                TextEntry::make('id')->label('ID catatan'),
                TextEntry::make('submission_id')->label('ID submission')
                    ->url(fn (SubmissionProcessingEvent $record): ?string => $record->submission === null ? null : SubmissionResource::getUrl('view', ['record' => $record->submission])),
                TextEntry::make('stage')->label('Tahap')->state(fn (SubmissionProcessingEvent $record): ?string => $record->getRawOriginal('stage'))->formatStateUsing(MonitoringPresentation::stage(...)),
                TextEntry::make('outcome')->label('Hasil operasi')->state(fn (SubmissionProcessingEvent $record): ?string => $record->getRawOriginal('outcome'))->formatStateUsing(MonitoringPresentation::outcome(...)),
                TextEntry::make('service')->label('Layanan')->formatStateUsing(MonitoringPresentation::service(...))->placeholder('Layanan tidak tercatat'),
                TextEntry::make('attempt')->label('Percobaan'),
                TextEntry::make('duration_ms')->label('Durasi')->formatStateUsing(MonitoringPresentation::duration(...))->placeholder('Durasi tidak tercatat'),
                TextEntry::make('error_code')->label('Kode kendala')->formatStateUsing(MonitoringPresentation::errorCode(...))->placeholder('Kode kendala tidak tercatat'),
                TextEntry::make('created_at')->label('Dicatat')->dateTime('d/m/Y H:i:s'),
            ])->columns(2)->columnSpanFull(),
            Section::make('Metadata operasional')->description(MonitoringPresentation::METADATA_NOTICE)->schema([
                ViewEntry::make('metadata_summary')->label('Metadata yang dapat ditampilkan')
                    ->state(MonitoringPresentation::eventMetadata(...))->view('filament.admin.resources.monitoring-metadata')->columnSpanFull(),
            ])->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubmissionProcessingEvents::route('/'),
            'view' => ViewSubmissionProcessingEvent::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
