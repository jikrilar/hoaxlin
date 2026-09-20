<?php

namespace App\Services\Bert;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads the metadata of the model that is actually serving inference.
 *
 * The database contains historical classification records, but it cannot
 * establish which artifact is currently loaded by the BERT service.  This
 * adapter therefore treats /version as the only authority for active model
 * and threshold metadata.  An unavailable or malformed service is exposed as
 * an unavailable result so admin pages and the homepage remain usable.
 */
class BertRuntimeMetadata
{
    /**
     * @return array{
     *     available: bool,
     *     model_status: string,
     *     model_version: ?string,
     *     threshold: ?float,
     *     temperature: ?float,
     *     labels: ?list<string>,
     *     evaluation: array{
     *         model_version: ?string,
     *         accuracy: ?float,
     *         macro_f1: ?float,
     *         sample_count: ?int,
     *         dataset_name: ?string,
     *         dataset_version: ?string,
     *         split: ?string,
     *         exported_at: ?string,
     *     }
     * }
     */
    public function fetch(): array
    {
        $unavailable = $this->unavailable();
        $config = config('services.bert', []);

        try {
            $request = Http::baseUrl(rtrim((string) ($config['url'] ?? ''), '/'))
                ->connectTimeout(max(1, (int) ($config['connect_timeout'] ?? 3)))
                ->timeout(max(1, (int) ($config['metadata_timeout'] ?? 3)))
                ->acceptJson();

            if (filled($config['internal_token'] ?? null)) {
                $request = $request->withToken((string) $config['internal_token']);
            }

            /** @var Response $response */
            $response = $request->get('/version');
        } catch (Throwable) {
            return $unavailable;
        }

        if (! $response->successful()) {
            return $unavailable;
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return $unavailable;
        }

        return $this->normalize($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(array $payload): array
    {
        $status = is_string($payload['model_status'] ?? null)
            ? trim($payload['model_status'])
            : '';
        $version = is_string($payload['model_version'] ?? null)
            ? trim($payload['model_version'])
            : '';

        if ($status !== 'ready' || $version === '') {
            return $this->unavailable($status !== '' ? $status : 'unavailable');
        }

        $labels = null;
        if (is_array($payload['model_labels'] ?? null)) {
            $candidateLabels = array_values(array_filter(
                $payload['model_labels'],
                static fn (mixed $label): bool => is_string($label) && trim($label) !== '',
            ));

            if (count($candidateLabels) === count($payload['model_labels'])) {
                $labels = array_map(static fn (string $label): string => trim($label), $candidateLabels);
            }
        }

        $evaluationPayload = is_array($payload['evaluation'] ?? null)
            ? $payload['evaluation']
            : [];
        $evaluationVersion = $this->stringValue(
            $evaluationPayload['model_version'] ?? $payload['evaluation_model_version'] ?? null,
        );

        // Never present metrics for an artifact that explicitly identifies a
        // different model version than the runtime serving version.
        $evaluationMatches = $evaluationVersion === null
            || $this->sameVersion($evaluationVersion, $version);

        $accuracy = $evaluationMatches
            ? $this->metric($evaluationPayload['accuracy'] ?? $payload['evaluation_accuracy'] ?? null)
            : null;
        $macroF1 = $evaluationMatches
            ? $this->metric($evaluationPayload['macro_f1'] ?? $payload['evaluation_macro_f1'] ?? null)
            : null;
        $sampleCount = $evaluationMatches
            ? $this->positiveInt($evaluationPayload['sample_count']
                ?? $evaluationPayload['n']
                ?? $payload['evaluation_sample_count']
                ?? null)
            : null;
        $datasetName = $evaluationMatches
            ? $this->safeString($evaluationPayload['dataset_name']
                ?? $payload['evaluation_dataset_name']
                ?? null)
            : null;
        $datasetVersion = $evaluationMatches
            ? $this->safeString($evaluationPayload['dataset_version']
                ?? $payload['evaluation_dataset_version']
                ?? null)
            : null;
        $split = $evaluationMatches
            ? $this->safeString($evaluationPayload['split']
                ?? $payload['evaluation_split']
                ?? null)
            : null;
        $exportedAt = $evaluationMatches
            ? $this->safeString($evaluationPayload['exported_at']
                ?? $payload['exported_at']
                ?? null)
            : null;

        $hasEvaluation = $accuracy !== null
            || $macroF1 !== null
            || $sampleCount !== null
            || $datasetName !== null
            || $datasetVersion !== null
            || $split !== null
            || $exportedAt !== null;

        return [
            'available' => true,
            'model_status' => $status,
            'model_version' => $version,
            'threshold' => $this->threshold($payload['threshold'] ?? null),
            'temperature' => $this->positiveMetric($payload['temperature'] ?? null),
            'labels' => $labels,
            'evaluation' => [
                'model_version' => $hasEvaluation && $evaluationMatches ? $version : null,
                'accuracy' => $accuracy,
                'macro_f1' => $macroF1,
                'sample_count' => $sampleCount,
                'dataset_name' => $datasetName,
                'dataset_version' => $datasetVersion,
                'split' => $split,
                'exported_at' => $exportedAt,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(string $status = 'unavailable'): array
    {
        return [
            'available' => false,
            'model_status' => $status,
            'model_version' => null,
            'threshold' => null,
            'temperature' => null,
            'labels' => null,
            'evaluation' => [
                'model_version' => null,
                'accuracy' => null,
                'macro_f1' => null,
                'sample_count' => null,
                'dataset_name' => null,
                'dataset_version' => null,
                'split' => null,
                'exported_at' => null,
            ],
        ];
    }

    private function sameVersion(string $left, string $right): bool
    {
        return ltrim($left, 'vV') === ltrim($right, 'vV');
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== ''
            ? trim(mb_substr($value, 0, 160))
            : null;
    }

    private function safeString(mixed $value): ?string
    {
        return $this->stringValue($value);
    }

    private function metric(mixed $value): ?float
    {
        if ((! is_int($value) && ! is_float($value)) || is_bool($value) || ! is_finite((float) $value)) {
            return null;
        }

        $number = (float) $value;

        return $number >= 0.0 && $number <= 1.0 ? $number : null;
    }

    private function positiveMetric(mixed $value): ?float
    {
        if ((! is_int($value) && ! is_float($value)) || is_bool($value) || ! is_finite((float) $value)) {
            return null;
        }

        $number = (float) $value;

        return $number > 0.0 ? $number : null;
    }

    private function threshold(mixed $value): ?float
    {
        if ((! is_int($value) && ! is_float($value)) || is_bool($value) || ! is_finite((float) $value)) {
            return null;
        }

        $number = (float) $value;

        return $number > 0.0 && $number < 1.0 ? $number : null;
    }

    private function positiveInt(mixed $value): ?int
    {
        if ((! is_int($value) && ! is_float($value)) || is_bool($value) || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }
}
