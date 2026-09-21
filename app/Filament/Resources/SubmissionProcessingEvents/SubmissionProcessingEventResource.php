<?php

namespace App\Filament\Resources\SubmissionProcessingEvents;

use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Filament\Resources\SubmissionProcessingEvents\Pages\ListSubmissionProcessingEvents;
use App\Filament\Resources\SubmissionProcessingEvents\Pages\ViewSubmissionProcessingEvent;
use App\Models\SubmissionProcessingEvent;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
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
                TextColumn::make('id')->sortable(),
                TextColumn::make('submission.id')->label('Submission')->sortable(),
                TextColumn::make('stage')->badge()->sortable(),
                TextColumn::make('outcome')->badge()->sortable(),
                TextColumn::make('service')->badge(),
                TextColumn::make('attempt')->sortable(),
                TextColumn::make('duration_ms')->label('Durasi (ms)')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('stage')->options(ProcessingStage::options()),
                SelectFilter::make('outcome')->options(EventOutcome::options()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
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
