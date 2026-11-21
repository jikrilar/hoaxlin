<?php

namespace App\Filament\Resources\Submissions\Pages;

use App\Filament\Resources\Submissions\SubmissionResource;
use App\Services\Pipeline\SubmissionRecovery;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * The list is split by what an admin can act on (T3.5).
 *
 * "Perlu tindakan" reuses `SubmissionRecovery::actionableQuery()`, so the tab, the
 * retry action and the dashboard widget all describe the same rows. "Diproses"
 * deliberately includes stalled work as well: it answers "what is in flight",
 * while "Perlu tindakan" answers "what is stuck".
 */
class ListSubmissions extends ListRecords
{
    protected static string $resource = SubmissionResource::class;

    protected ?string $subheading = 'Catatan input dan status pipeline. Record classifier parsial tidak ditampilkan sebagai hasil final.';

    public function getTabs(): array
    {
        $recovery = app(SubmissionRecovery::class);

        return [
            'action' => Tab::make('Perlu tindakan')
                ->badge(fn (): int => $recovery->actionableQuery()->count())
                ->query(fn ($query) => $recovery->actionableQuery()),

            'processing' => Tab::make('Diproses')
                ->badge(fn (): int => $this->countFor(['pending', 'processing']))
                ->query(fn ($query) => $query->whereIn('status', ['pending', 'processing'])),

            'completed' => Tab::make('Selesai')
                ->badge(fn (): int => $this->countFor(['completed']))
                ->query(fn ($query) => $query->where('status', 'completed')),

            'all' => Tab::make('Semua')
                ->badge(fn (): int => $this->countFor(['pending', 'processing', 'completed', 'failed'])),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'all';
    }

    /**
     * @param  list<string>  $statuses
     */
    private function countFor(array $statuses): int
    {
        return SubmissionResource::getModel()::query()->whereIn('status', $statuses)->count();
    }
}
