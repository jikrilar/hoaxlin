<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\RequiresAdminPanel;
use App\Models\DetectionResult;
use App\Services\Bert\BertRuntimeMetadata;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use PDOException;

class ModelVersionWidget extends BaseWidget
{
    use RequiresAdminPanel;

    protected static ?int $sort = 30;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected string $view = 'filament.admin.widgets.model-version';

    private ?array $history = null;

    protected function getStats(): array
    {
        $runtime = app(BertRuntimeMetadata::class)->fetch();
        $available = $runtime['available'];
        $runtimeVersion = $available ? ($runtime['model_version'] ?? 'n/a') : 'n/a';
        $threshold = $available && is_numeric($runtime['threshold'] ?? null) ? number_format((float) $runtime['threshold'], 4, '.', '') : 'n/a';
        $evaluation = $runtime['evaluation'] ?? [];
        $evaluationValue = $available && is_numeric($evaluation['accuracy'] ?? null) ? number_format((float) $evaluation['accuracy'] * 100, 2, '.', '').'%' : 'n/a';
        $history = $this->getHistory();

        return [
            Stat::make('Model runtime BERT', $runtimeVersion)->description($available ? 'Sumber: runtime BERT /version' : 'Runtime BERT tidak tersedia; versi historis tidak dipakai sebagai model aktif.'),
            Stat::make('Threshold abstention', $threshold)->description('Batas skor keyakinan classifier; bukan persentase kebenaran informasi.'),
            Stat::make('Akurasi evaluasi artefak', $evaluationValue)->description($this->evaluationDescription($evaluation, $runtimeVersion, $available)),
            Stat::make('Kelompok versi pada riwayat', $history['available'] ? count($history['rows']).' kelompok teratas' : 'n/a')
                ->description($history['available'] ? (implode(', ', array_column($history['rows'], 'version')) ?: 'Belum ada riwayat versi.') : 'Riwayat versi belum dapat dibaca.'),
        ];
    }

    private function getHistory(): array
    {
        if ($this->history !== null) {
            return $this->history;
        }
        try {
            $versions = DetectionResult::select('model_version', DB::raw('count(*) as c'))->groupBy('model_version')->orderByDesc('c')->limit(3)->pluck('c', 'model_version');
            $rows = [];
            foreach ($versions as $version => $count) {
                $rows[] = ['version' => filled($version) ? (string) $version : 'Versi tidak tercatat', 'count' => (int) $count];
            }

            return $this->history = ['available' => true, 'rows' => $rows];
        } catch (PDOException $exception) {
            report($exception);

            return $this->history = ['available' => false, 'rows' => []];
        }
    }

    protected function getViewData(): array
    {
        return ['stats' => $this->getCachedStats(), 'history' => $this->getHistory(), 'pollingInterval' => $this->getPollingInterval()];
    }

    private function evaluationDescription(array $evaluation, string $runtimeVersion, bool $available): string
    {
        if (! $available || ! is_numeric($evaluation['accuracy'] ?? null)) {
            return 'Artefak evaluasi model aktif tidak tersedia.';
        }

        return implode(' · ', [
            'Model '.$runtimeVersion,
            'Dataset '.(filled($evaluation['dataset_name'] ?? null) ? $evaluation['dataset_name'] : 'n/a'),
            'Versi dataset '.(filled($evaluation['dataset_version'] ?? null) ? $evaluation['dataset_version'] : 'n/a'),
            'Split '.(filled($evaluation['split'] ?? null) ? $evaluation['split'] : 'n/a'),
            'n='.(is_numeric($evaluation['sample_count'] ?? null) ? $evaluation['sample_count'] : 'n/a'),
            'Ekspor '.(filled($evaluation['exported_at'] ?? null) ? $evaluation['exported_at'] : 'n/a'),
        ]);
    }
}
