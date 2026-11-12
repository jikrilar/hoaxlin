<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AdminGlossary;
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

    private const UNAVAILABLE = 'Belum tersedia';

    private ?array $history = null;

    protected function getStats(): array
    {
        $runtime = app(BertRuntimeMetadata::class)->fetch();
        $available = $runtime['available'];
        $runtimeVersion = $available && filled($runtime['model_version'] ?? null) ? (string) $runtime['model_version'] : self::UNAVAILABLE;
        $threshold = $available && is_numeric($runtime['threshold'] ?? null) ? number_format((float) $runtime['threshold'], 4, '.', '') : self::UNAVAILABLE;
        $evaluation = $runtime['evaluation'] ?? [];
        $evaluationValue = $available && is_numeric($evaluation['accuracy'] ?? null) ? number_format((float) $evaluation['accuracy'] * 100, 2, '.', '').'%' : self::UNAVAILABLE;
        $history = $this->getHistory();

        return [
            Stat::make('Model runtime BERT', $runtimeVersion)->description(
                ($available && filled($runtime['model_version'] ?? null) ? 'Sumber: layanan model aktif. ' : 'Layanan model tidak tersedia; versi historis tidak dipakai sebagai model aktif. ')
                .AdminGlossary::explain('active_inference_status'),
            ),
            Stat::make('Batas keyakinan (abstention)', $threshold)->description(AdminGlossary::explain('threshold')),
            Stat::make('Akurasi evaluasi artefak', $evaluationValue)->description(
                $this->evaluationDescription($evaluation, $runtimeVersion, $available).' '.AdminGlossary::explain('baseline_comparison'),
            ),
            Stat::make('Kelompok versi pada riwayat', $history['available'] ? count($history['rows']).' kelompok teratas' : self::UNAVAILABLE)
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
            'Dataset '.(filled($evaluation['dataset_name'] ?? null) ? $evaluation['dataset_name'] : self::UNAVAILABLE),
            'Versi dataset '.(filled($evaluation['dataset_version'] ?? null) ? $evaluation['dataset_version'] : self::UNAVAILABLE),
            'Split '.(filled($evaluation['split'] ?? null) ? $evaluation['split'] : self::UNAVAILABLE),
            'n='.(is_numeric($evaluation['sample_count'] ?? null) ? $evaluation['sample_count'] : self::UNAVAILABLE),
            'Ekspor '.(filled($evaluation['exported_at'] ?? null) ? $evaluation['exported_at'] : self::UNAVAILABLE),
        ]);
    }
}
