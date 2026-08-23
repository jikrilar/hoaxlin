<?php

namespace App\Filament\Widgets;

use App\Models\DetectionResult;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ModelVersionWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $byVersion = DetectionResult::select('model_version', DB::raw('count(*) as c'))
            ->groupBy('model_version')
            ->orderByDesc('c')
            ->limit(3)
            ->pluck('c', 'model_version')
            ->all();

        $topVersion = array_key_first($byVersion) ?? 'belum ada';
        $topCount = $byVersion[$topVersion] ?? 0;

        // Read threshold sidecar if available
        $threshold = 'n/a';
        $manifest = base_path('models/indobert-hoax/manifest.json');
        if (is_file($manifest)) {
            $data = json_decode(file_get_contents($manifest), true);
            $entry = is_array($data) && isset($data[0]) ? end($data) : $data;
            $threshold = $entry['threshold'] ?? $threshold;
        }

        return [
            Stat::make('Model Aktif', $topVersion)
                ->description("{$topCount} klasifikasi")
                ->color('info'),
            Stat::make('Threshold Meragukan', is_numeric($threshold) ? (string) $threshold : $threshold)
                ->description('dari threshold.json')
                ->color('warning'),
            Stat::make('Versi Lain', count($byVersion) > 1 ? (string) (count($byVersion) - 1).' versi' : '—')
                ->description(implode(', ', array_keys($byVersion)) ?: 'hanya 1 versi')
                ->color('gray'),
        ];
    }
}
