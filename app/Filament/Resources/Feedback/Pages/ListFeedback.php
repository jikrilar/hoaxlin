<?php

namespace App\Filament\Resources\Feedback\Pages;

use App\Filament\Resources\Feedback\FeedbackResource;
use App\Filament\Support\ReadOnlyPresentation;
use App\Models\Feedback;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * Feedback is reviewed from the list (T3.7).
 *
 * "Perlu ditinjau" leads because it is the actual work queue; "Tidak sesuai"
 * keeps every negative assessment regardless of review state, so reviewed
 * disagreements do not disappear from the record.
 */
class ListFeedback extends ListRecords
{
    protected static string $resource = FeedbackResource::class;

    protected ?string $subheading = ReadOnlyPresentation::FEEDBACK_NOTICE;

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Perlu ditinjau')
                ->badge(fn (): int => Feedback::query()->pendingReview()->count())
                ->query(fn ($query) => $query->whereNull('reviewed_at')),

            'incorrect' => Tab::make('Tidak sesuai')
                ->badge(fn (): int => Feedback::query()->where('is_correct', false)->count())
                ->query(fn ($query) => $query->where('is_correct', false)),

            'all' => Tab::make('Semua')
                ->badge(fn (): int => Feedback::query()->count()),
        ];
    }
}
