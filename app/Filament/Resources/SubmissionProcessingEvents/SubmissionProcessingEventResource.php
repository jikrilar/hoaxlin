<?php

namespace App\Filament\Resources\SubmissionProcessingEvents;

use App\Filament\Resources\SubmissionProcessingEvents\Pages\ListSubmissionProcessingEvents;
use App\Filament\Resources\SubmissionProcessingEvents\Pages\ViewSubmissionProcessingEvent;
use App\Models\SubmissionProcessingEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
                \Filament\Tables\Columns\TextColumn::make('id')->sortable(),
                \Filament\Tables\Columns\TextColumn::make('submission.id')->label('Submission')->sortable(),
                \Filament\Tables\Columns\TextColumn::make('stage')->badge()->sortable(),
                \Filament\Tables\Columns\TextColumn::make('outcome')->badge()->sortable(),
                \Filament\Tables\Columns\TextColumn::make('service')->badge(),
                \Filament\Tables\Columns\TextColumn::make('attempt')->sortable(),
                \Filament\Tables\Columns\TextColumn::make('duration_ms')->label('Durasi (ms)')->sortable(),
                \Filament\Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('stage')->options(\App\Enums\ProcessingStage::options()),
                \Filament\Tables\Filters\SelectFilter::make('outcome')->options(\App\Enums\EventOutcome::options()),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
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
