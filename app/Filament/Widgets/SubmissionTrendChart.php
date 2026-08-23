<?php

namespace App\Filament\Widgets;

use App\Models\Submission;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SubmissionTrendChart extends ChartWidget
{
    protected ?string $heading = 'Tren Submission (30 hari)';

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(function ($d) {
            $date = Carbon::today()->subDays($d);
            return [
                'label' => $date->format('d M'),
                'total' => Submission::whereDate('created_at', $date)->count(),
                'hoax' => Submission::whereDate('created_at', $date)->whereHas('detectionResult', fn ($q) => $q->where('label', 'hoax'))->count(),
            ];
        });

        return [
            'datasets' => [
                ['label' => 'Total', 'data' => $days->pluck('total')->all(), 'borderColor' => '#818cf8', 'backgroundColor' => 'rgba(129,140,248,0.15)'],
                ['label' => 'Hoax', 'data' => $days->pluck('hoax')->all(), 'borderColor' => '#f87171', 'backgroundColor' => 'rgba(248,113,113,0.1)'],
            ],
            'labels' => $days->pluck('label')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
