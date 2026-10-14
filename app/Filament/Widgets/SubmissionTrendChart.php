<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\RequiresAdminPanel;
use App\Models\Submission;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use PDOException;

class SubmissionTrendChart extends ChartWidget
{
    use RequiresAdminPanel;

    protected static ?int $sort = 20;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Tren submission · 30 hari';

    protected ?string $maxHeight = '240px';

    protected ?string $pollingInterval = '60s';

    protected string $view = 'filament.admin.widgets.submission-trend';

    protected ?array $options = ['animation' => ['duration' => 0], 'plugins' => ['legend' => ['labels' => ['usePointStyle' => true]]], 'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]];

    private ?array $trend = null;

    public function getTrend(): array
    {
        if ($this->trend !== null) {
            return $this->trend;
        }
        $today = CarbonImmutable::today(config('app.timezone'));
        $start = $today->subDays(29);
        $rows = [];
        $available = true;
        try {
            // Preserve the existing DATE/whereHas populations, with two grouped
            // queries instead of querying each day separately.
            $query = Submission::whereDate('created_at', '>=', $start->toDateString())->whereDate('created_at', '<=', $today->toDateString());
            $totals = (clone $query)->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupByRaw('DATE(created_at)')->pluck('total', 'day');
            $hoaxes = (clone $query)->whereHas('detectionResult', fn ($query) => $query->where('label', 'hoax'))
                ->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupByRaw('DATE(created_at)')->pluck('total', 'day');
            foreach (range(0, 29) as $offset) {
                $date = $start->addDays($offset);
                $rows[] = ['date' => $date->toDateString(), 'label' => $date->format('d/m/Y'), 'total' => (int) ($totals[$date->toDateString()] ?? 0), 'hoax' => (int) ($hoaxes[$date->toDateString()] ?? 0)];
            }
        } catch (PDOException $exception) {
            report($exception);
            $available = false;
        }

        return $this->trend = ['available' => $available, 'start' => $start->format('d/m/Y'), 'end' => $today->format('d/m/Y'), 'timezone' => config('app.timezone'), 'rows' => $rows];
    }

    public function getDescription(): ?string
    {
        $trend = $this->getTrend();

        return $trend['start'].'–'.$trend['end'].' · '.$trend['timezone'].'. Total mencakup semua status; seri prediksi hoaks memakai label hasil tersimpan, termasuk hasil pada submission nonfinal.';
    }

    protected function getData(): array
    {
        $trend = $this->getTrend();
        if (! $trend['available'] || array_sum(array_column($trend['rows'], 'total')) === 0) {
            return [];
        }

        return [
            'datasets' => [
                ['label' => 'Semua submission', 'data' => array_column($trend['rows'], 'total'), 'fill' => false, 'tension' => 0, 'pointRadius' => 2, 'borderWidth' => 2, 'pointStyle' => 'circle'],
                ['label' => 'Dengan prediksi hoaks tersimpan', 'data' => array_column($trend['rows'], 'hoax'), 'fill' => false, 'tension' => 0, 'pointRadius' => 2, 'borderWidth' => 2, 'pointStyle' => 'triangle', 'borderDash' => [6, 4]],
            ],
            'labels' => array_column($trend['rows'], 'label'),
        ];
    }

    public function getEmptyStateHeading(): string
    {
        return $this->getTrend()['available'] ? 'Belum ada submission dalam periode ini' : 'Data tren belum tersedia';
    }

    public function getEmptyStateDescription(): ?string
    {
        return $this->getTrend()['available'] ? 'Tabel tetap memuat 30 hari dengan jumlah nol.' : 'Muat ulang halaman untuk mencoba membaca data kembali.';
    }

    protected function getType(): string
    {
        return 'line';
    }
}
