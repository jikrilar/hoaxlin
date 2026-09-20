<?php

namespace App\Filament\Widgets;

use App\Models\DetectionResult;
use App\Services\Bert\BertRuntimeMetadata;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ModelVersionWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $runtime = app(BertRuntimeMetadata::class)->fetch();
        $byVersion = DetectionResult::select('model_version', DB::raw('count(*) as c'))
            ->groupBy('model_version')
            ->orderByDesc('c')
            ->limit(3)
            ->pluck('c', 'model_version')
            ->all();

        $runtimeVersion = $runtime['available'] ? ($runtime['model_version'] ?? 'n/a') : 'n/a';
        $threshold = $runtime['available'] && is_numeric($runtime['threshold'] ?? null)
            ? number_format((float) $runtime['threshold'], 4, '.', '')
            : 'n/a';
        $evaluation = $runtime['evaluation'];
        $evaluationValue = is_numeric($evaluation['accuracy'] ?? null)
            ? number_format((float) $evaluation['accuracy'] * 100, 2, '.', '').'%'
            : 'n/a';

        return [
            Stat::make('Model Aktif', $runtimeVersion)
                ->description($runtime['available'] ? 'runtime BERT /version' : 'runtime BERT tidak tersedia')
                ->color('info'),
            Stat::make('Threshold Meragukan', $threshold)
                ->description($runtime['available'] ? 'runtime BERT /version' : 'tidak tersedia')
                ->color('warning'),
            Stat::make('Akurasi Evaluasi', $evaluationValue)
                ->description($this->evaluationDescription($evaluation, $runtimeVersion))
                ->color('success'),
            Stat::make('Versi pada Riwayat', count($byVersion) > 0 ? (string) count($byVersion).' versi' : 'n/a')
                ->description(implode(', ', array_keys($byVersion)) ?: 'belum ada riwayat')
                ->color('gray'),
        ];
    }

    /**
     * @param  array<string, mixed>  $evaluation
     */
    private function evaluationDescription(array $evaluation, string $runtimeVersion): string
    {
        if (! is_numeric($evaluation['accuracy'] ?? null)) {
            return 'artefak evaluasi model aktif tidak tersedia';
        }

        $parts = [
            filled($evaluation['split'] ?? null) ? (string) $evaluation['split'] : 'split n/a',
            'model '.$runtimeVersion,
        ];

        if (is_numeric($evaluation['sample_count'] ?? null)) {
            $parts[] = 'n='.(int) $evaluation['sample_count'];
        }

        if (filled($evaluation['dataset_name'] ?? null) || filled($evaluation['dataset_version'] ?? null)) {
            $parts[] = trim(implode(' ', array_filter([
                $evaluation['dataset_name'] ?? null,
                $evaluation['dataset_version'] ?? null,
            ])));
        }

        if (filled($evaluation['exported_at'] ?? null)) {
            $parts[] = 'export '.$evaluation['exported_at'];
        }

        return implode(' · ', $parts);
    }
}
