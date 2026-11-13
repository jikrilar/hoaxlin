<?php

namespace App\Filament\Widgets;

use App\Enums\ProcessingStage;
use App\Filament\Widgets\Concerns\RequiresAdminPanel;
use App\Services\Admin\DashboardMetrics;
use App\View\Presenters\DetectionPresentation;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Range-scoped breakdowns (T3.2).
 *
 * Chart.js draws one series type per canvas, so the widget exposes a small view
 * switch (trend, labels, inputs, failures, feedback) instead of forcing five
 * charts onto one screen. Every view is paired with a "Lihat data tabel"
 * disclosure that renders the same numbers as a real table.
 *
 * Series colours are the primitive values behind design-tokens.css; Chart.js
 * cannot read CSS variables, and AdminDashboardWidgetsTest fails if these hex
 * values drift from the token file.
 */
class DashboardCharts extends ChartWidget
{
    use InteractsWithPageFilters;
    use RequiresAdminPanel;

    /** @var array<string, string> */
    public const SERIES_COLORS = [
        'primary' => '#235c50', // --p-evergreen-600
        'danger' => '#a23d35',  // --p-danger
        'warning' => '#795713', // --p-warning
        'info' => '#335c77',    // --p-info
        'muted' => '#647069',   // --p-muted
    ];

    public const VIEWS = [
        'trend' => 'Tren',
        'labels' => 'Label',
        'inputs' => 'Jenis input',
        'failures' => 'Kegagalan',
        'feedback' => 'Umpan balik',
    ];

    protected static ?int $sort = 20;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = '60s';

    protected string $view = 'filament.admin.widgets.dashboard-charts';

    public string $viewMode = 'trend';

    /** A tampered/legacy viewMode falls back to the trend view instead of a blank chart. */
    public function mode(): string
    {
        return array_key_exists($this->viewMode, self::VIEWS) ? $this->viewMode : 'trend';
    }

    public function getHeading(): ?string
    {
        $window = $this->days().' hari';

        return match ($this->mode()) {
            'labels' => 'Distribusi label · '.$window,
            'inputs' => 'Jenis input · '.$window,
            'failures' => 'Kegagalan per tahap · '.$window,
            'feedback' => 'Umpan balik pengguna · '.$window,
            default => 'Tren pemeriksaan per hari · '.$window,
        };
    }

    public function getDescription(): ?string
    {
        return match ($this->mode()) {
            'labels' => 'Label hasil tersimpan pada pemeriksaan di jendela ini. Bukan prevalensi hoaks, bukan akurasi model.',
            'inputs' => 'Jumlah pemeriksaan menurut jenis input. Bukan ukuran kualitas analisis.',
            'failures' => 'Event tahap yang berakhir gagal. Satu pemeriksaan dapat menyumbang lebih dari satu kegagalan.',
            'feedback' => 'Penilaian pengguna terhadap prediksi, bukan akurasi model.',
            default => 'Total mencakup semua status; seri label memakai hasil tersimpan, termasuk hasil pada pemeriksaan nonfinal.',
        };
    }

    public function days(): int
    {
        return app(DashboardMetrics::class)->rangeDays($this->pageFilters['range'] ?? null);
    }

    public function getViewModes(): array
    {
        return self::VIEWS;
    }

    /**
     * A window with no activity is an empty state, not a flat line at zero
     * (T3.2 acceptance: no widget without an empty state).
     */
    public function isEmpty(): bool
    {
        $data = $this->getCachedData();
        foreach ($data['datasets'] ?? [] as $dataset) {
            if (array_sum($dataset['data'] ?? []) > 0) {
                return false;
            }
        }

        return true;
    }

    public function getEmptyStateHeading(): string
    {
        return match ($this->mode()) {
            'labels' => 'Belum ada hasil berlabel dalam jendela ini',
            'inputs' => 'Belum ada pemeriksaan dalam jendela ini',
            'failures' => 'Tidak ada event gagal dalam jendela ini',
            'feedback' => 'Belum ada umpan balik dalam jendela ini',
            default => 'Belum ada pemeriksaan dalam jendela ini',
        };
    }

    public function getEmptyStateDescription(): ?string
    {
        return 'Pilih rentang waktu lain pada filter di atas, atau tunggu pemeriksaan berikutnya.';
    }

    public function getType(): string
    {
        return match ($this->mode()) {
            'labels' => 'doughnut',
            'inputs', 'failures', 'feedback' => 'bar',
            default => 'line',
        };
    }

    protected function getData(): array
    {
        $metrics = app(DashboardMetrics::class);

        if ($this->mode() === 'trend') {
            return $this->trendData($metrics->trend($this->days()));
        }

        $breakdown = $metrics->breakdown($this->days());

        if ($this->mode() === 'feedback') {
            // Stacked bar: one bar per judgement, so the ratio reads without a legend lookup.
            return [
                'labels' => ['Umpan balik'],
                'datasets' => [
                    [
                        'label' => 'Sesuai',
                        'data' => [$breakdown['feedback']['correct']],
                        'backgroundColor' => self::SERIES_COLORS['primary'],
                        'borderColor' => self::SERIES_COLORS['primary'],
                        'borderWidth' => 1,
                        'stack' => 'feedback',
                    ],
                    [
                        'label' => 'Tidak sesuai',
                        'data' => [$breakdown['feedback']['incorrect']],
                        'backgroundColor' => self::SERIES_COLORS['danger'],
                        'borderColor' => self::SERIES_COLORS['danger'],
                        'borderWidth' => 1,
                        'stack' => 'feedback',
                    ],
                ],
            ];
        }

        [$labels, $values, $colors] = match ($this->mode()) {
            'labels' => $this->mapped($breakdown['labels'], fn (string $key): string => DetectionPresentation::label($key)['text'], [
                'valid' => 'muted', 'hoax' => 'danger', 'meragukan' => 'warning',
            ]),
            'inputs' => $this->mapped($breakdown['inputs'], fn (string $key): string => DetectionPresentation::inputType($key)),
            // Failures are an operational warning, so they never borrow the neutral series.
            default => $this->mapped($breakdown['failures'], fn (string $key): string => ProcessingStage::tryFrom($key)?->label() ?? 'Tahap tidak dikenali', [], 'danger'),
        };

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => (string) $this->getHeading(),
                'data' => $values,
                'backgroundColor' => $colors,
                'borderColor' => $colors,
                'borderWidth' => 1,
            ]],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'animation' => ['duration' => 0],
            'plugins' => ['legend' => ['display' => $this->mode() !== 'trend', 'labels' => ['usePointStyle' => true]]],
            'scales' => $this->mode() === 'feedback'
                ? ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]]]
                : ($this->mode() === 'trend'
                // 30/90 daily labels never fit a card; Chart.js thins them instead of
                // rendering rotated text that overlaps the plot.
                ? ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]], 'x' => ['ticks' => ['maxRotation' => 0, 'autoSkip' => true, 'maxTicksLimit' => 6]]]
                : ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]], 'x' => ['ticks' => ['autoSkip' => false]]]),
        ];
    }

    /**
     * Four series with distinct shapes as well as colours (WCAG 1.4.1).
     */
    private function trendData(array $trend): array
    {
        $series = [
            ['Semua pemeriksaan', 'total', 'primary', 'circle', null],
            ['Valid', 'valid', 'muted', 'rect', null],
            ['Hoaks', 'hoax', 'danger', 'triangle', [6, 4]],
            ['Belum terverifikasi', 'meragukan', 'warning', 'crossRot', [2, 3]],
        ];

        $datasets = [];
        foreach ($series as [$label, $column, $tone, $pointStyle, $dash]) {
            $datasets[] = array_filter([
                'label' => $label,
                'data' => array_column($trend['rows'], $column),
                'borderColor' => self::SERIES_COLORS[$tone],
                'backgroundColor' => self::SERIES_COLORS[$tone],
                'fill' => false,
                'tension' => 0,
                'pointRadius' => 2,
                'borderWidth' => 2,
                'pointStyle' => $pointStyle,
                'borderDash' => $dash,
            ], fn ($value): bool => $value !== null);
        }

        return ['labels' => array_column($trend['rows'], 'label'), 'datasets' => $datasets];
    }

    /**
     * @param  array<string, int>  $map
     * @param  array<string, string>  $tones  key => SERIES_COLORS key
     * @return array{0: list<string>, 1: list<int>, 2: list<string>}
     */
    private function mapped(array $map, callable $label, array $tones = [], ?string $fallback = null): array
    {
        $palette = array_values(self::SERIES_COLORS);
        $fallbackColor = $fallback === null ? null : self::SERIES_COLORS[$fallback];

        $labels = [];
        $values = [];
        $colors = [];
        foreach ($map as $key => $count) {
            $labels[] = $label((string) $key);
            $values[] = (int) $count;
            $colors[] = $fallbackColor ?? self::SERIES_COLORS[$tones[$key] ?? 'info'] ?? $palette[count($colors) % count($palette)];
        }

        return [$labels, $values, $colors];
    }
}
