<?php

namespace App\Http\Controllers;

use App\Models\DetectionResult;
use App\Services\Bert\BertRuntimeMetadata;
use App\Services\Security\CaptchaChallenge;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    private const LATENCY_SAMPLE_LIMIT = 1000;

    private const LATENCY_MINIMUM_SAMPLE = 3;

    public function __invoke(
        Request $request,
        CaptchaChallenge $captcha,
        BertRuntimeMetadata $runtimeMetadata,
    ): View {
        $challenge = $captcha->issue($request);
        $runtime = $runtimeMetadata->fetch();
        $evaluation = $runtime['evaluation'];
        $modelVersion = $runtime['available'] ? ($runtime['model_version'] ?? null) : null;

        $latency = $this->latencyStats($modelVersion);
        $modelStatistics = [
            [
                'value' => $modelVersion ?? 'n/a',
                'label' => 'Model aktif',
                'description' => $modelVersion !== null ? 'runtime BERT /version' : 'runtime tidak tersedia',
            ],
            [
                'value' => is_numeric($evaluation['accuracy'] ?? null)
                    ? number_format((float) $evaluation['accuracy'] * 100, 2, '.', '').'%'
                    : 'n/a',
                'label' => 'Akurasi evaluasi held-out test',
                'description' => $this->evaluationDescription($evaluation, $modelVersion),
            ],
            [
                'value' => $latency['value'],
                'label' => 'Median BERT inference',
                'description' => $latency['description'],
            ],
            [
                'value' => '4',
                'label' => 'Jenis input tersedia',
                'description' => 'teks, URL, gambar, video untuk user login',
            ],
        ];

        return view('welcome', [
            'captchaQuestion' => $challenge['question'],
            'modelStatistics' => $modelStatistics,
        ]);
    }

    /**
     * @param  array<string, mixed>  $evaluation
     */
    private function evaluationDescription(array $evaluation, ?string $modelVersion): string
    {
        if (! is_numeric($evaluation['accuracy'] ?? null)) {
            return 'artefak evaluasi model aktif belum tersedia';
        }

        $parts = [filled($evaluation['split'] ?? null) ? (string) $evaluation['split'] : 'split n/a'];
        if ($modelVersion !== null) {
            $parts[] = 'model '.$modelVersion;
        }
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

        return implode(' - ', $parts);
    }

    /**
     * @return array{value: string, description: string}
     */
    private function latencyStats(?string $modelVersion): array
    {
        if ($modelVersion === null) {
            return [
                'value' => 'n/a',
                'description' => 'telemetry runtime tidak tersedia',
            ];
        }

        $values = DetectionResult::query()
            ->where('model_version', $modelVersion)
            ->whereNotNull('inference_ms')
            ->where('inference_ms', '>=', 0)
            ->whereHas('submission', fn ($query) => $query->where('status', 'completed'))
            ->latest('id')
            ->limit(self::LATENCY_SAMPLE_LIMIT)
            ->pluck('inference_ms')
            ->map(static fn (mixed $value): float => (float) $value)
            ->sort()
            ->values()
            ->all();

        $count = count($values);
        $population = 'completed classifications, recent '.self::LATENCY_SAMPLE_LIMIT.'; inference_ms; n='.$count;
        if ($count < self::LATENCY_MINIMUM_SAMPLE) {
            return [
                'value' => 'n/a',
                'description' => $population.' (minimum '.self::LATENCY_MINIMUM_SAMPLE.')',
            ];
        }

        $middle = intdiv($count, 2);
        $median = $count % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : $values[$middle];

        return [
            'value' => number_format($median, 1, '.', '').' ms',
            'description' => $population.'; median',
        ];
    }
}
