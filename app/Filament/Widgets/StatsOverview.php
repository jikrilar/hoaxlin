<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\RequiresAdminPanel;
use App\Models\DetectionResult;
use App\Models\Submission;
use App\View\Presenters\DetectionPresentation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use PDOException;

class StatsOverview extends BaseWidget
{
    use RequiresAdminPanel;

    protected static ?int $sort = 10;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    protected string $view = 'filament.admin.widgets.operations';

    private bool $operationsAvailable = true;

    protected function getStats(): array
    {
        $today = today(config('app.timezone'));

        try {
            return [
                Stat::make('Seluruh submission', number_format(Submission::count(), 0, ',', '.'))->description('Semua status, seluruh riwayat'),
                Stat::make('Dikirim hari ini', number_format(Submission::whereDate('created_at', $today)->count(), 0, ',', '.'))
                    ->description($today->format('d/m/Y').' · '.config('app.timezone')),
                Stat::make('Menunggu atau diproses', number_format(Submission::whereIn('status', ['pending', 'processing'])->count(), 0, ',', '.'))
                    ->description('Status pending dan processing, seluruh riwayat'),
                Stat::make('Submission gagal', number_format(Submission::where('status', 'failed')->count(), 0, ',', '.'))->description('Status failed, seluruh riwayat'),
                Stat::make('Entri failed jobs', number_format(DB::table('failed_jobs')->count(), 0, ',', '.'))
                    ->description('Entri antrean gagal; bukan jumlah submission unik'),
            ];
        } catch (PDOException $exception) {
            report($exception);
            $this->operationsAvailable = false;

            return [];
        }
    }

    protected function getViewData(): array
    {
        try {
            $stats = $this->getCachedStats();
            $available = $this->operationsAvailable;
            $counts = $available ? DetectionResult::select('label', DB::raw('count(*) as c'))->groupBy('label')->pluck('c', 'label')->all() : [];
        } catch (PDOException $exception) {
            report($exception);
            $available = false;
            $stats = [];
            $counts = [];
        }

        return [
            'stats' => $stats,
            'available' => $available,
            'labels' => collect(['valid', 'hoax', 'meragukan'])->map(fn (string $label): array => [
                'code' => $label, 'label' => DetectionPresentation::label($label)['text'],
                'count' => $available ? (int) ($counts[$label] ?? 0) : null,
            ])->all(),
            'pollingInterval' => $this->getPollingInterval(),
        ];
    }
}
