<?php

namespace App\Services\Admin;

use App\Enums\EventOutcome;
use App\Models\DetectionResult;
use App\Models\Feedback;
use App\Models\Submission;
use App\Models\SubmissionProcessingEvent;
use App\Services\Pipeline\StaleSubmissionRecovery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PDOException;
use Throwable;

/**
 * Aggregated, read-only dashboard data (T3.2).
 *
 * Every method returns an array with an `available` flag instead of throwing:
 * the dashboard must render "Belum tersedia" rather than a stack trace when a
 * table is missing or the database is slow. Results are cached for 60 seconds
 * and keyed by the selected range so switching 7/30/90 never shows stale numbers.
 */
final class DashboardMetrics
{
    public const RANGES = [7 => '7 hari', 30 => '30 hari', 90 => '90 hari'];

    public const CACHE_SECONDS = 60;

    public function __construct(private readonly StaleSubmissionRecovery $stale) {}

    public function rangeDays(mixed $value): int
    {
        $days = (int) $value;

        return array_key_exists($days, self::RANGES) ? $days : 30;
    }

    /**
     * @return array{available: bool, range: int, start: ?string, end: ?string, timezone: string, rows: list<array{date: string, label: string, total: int, valid: int, hoax: int, meragukan: int}>}
     */
    public function trend(int $days): array
    {
        return $this->remember('trend:'.$days, function () use ($days): array {
            $end = CarbonImmutable::today(config('app.timezone'));
            $start = $end->subDays($days - 1);
            // Bound with timestamps, not DATE(created_at) predicates: a function on the
            // column disables the index. GROUP BY keeps DATE() because it only shapes
            // the already-filtered rows.
            $from = $start->startOfDay();
            $until = $end->endOfDay();

            $totals = Submission::query()
                ->whereBetween('created_at', [$from, $until])
                ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
                ->groupByRaw('DATE(created_at)')
                ->pluck('total', 'day');

            $labels = DetectionResult::query()
                ->join('submissions', 'submissions.id', '=', 'detection_results.submission_id')
                ->whereBetween('submissions.created_at', [$from, $until])
                ->selectRaw('DATE(submissions.created_at) as day, detection_results.label as label, COUNT(*) as total')
                ->groupByRaw('DATE(submissions.created_at), detection_results.label')
                ->get()
                ->groupBy('day');

            $rows = [];
            foreach (range(0, $days - 1) as $offset) {
                $date = $start->addDays($offset)->toDateString();
                $perLabel = $labels->get($date, collect())->pluck('total', 'label');
                $rows[] = [
                    'date' => $date,
                    'label' => $date,
                    'total' => (int) ($totals[$date] ?? 0),
                    'valid' => (int) ($perLabel['valid'] ?? 0),
                    'hoax' => (int) ($perLabel['hoax'] ?? 0),
                    'meragukan' => (int) ($perLabel['meragukan'] ?? 0),
                ];
            }

            return [
                'available' => true,
                'range' => $days,
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'timezone' => (string) config('app.timezone'),
                'rows' => $rows,
            ];
        }, ['available' => false, 'range' => $days, 'start' => null, 'end' => null, 'timezone' => (string) config('app.timezone'), 'rows' => []]);
    }

    /**
     * @return array{available: bool, today: ?int, week: ?int, completion_rate: ?float, failed: ?int, completion_base: ?int, sparkline: list<int>}
     */
    public function kpis(int $days): array
    {
        return $this->remember('kpis:'.$days, function () use ($days): array {
            $today = CarbonImmutable::today(config('app.timezone'));
            $weekStart = $today->subDays(6);
            $rangeStart = $today->subDays($days - 1);

            $rangeFrom = $rangeStart->startOfDay();
            $rangeUntil = $today->endOfDay();
            $weekFrom = $weekStart->startOfDay();

            $total = Submission::query()
                ->whereBetween('created_at', [$rangeFrom, $rangeUntil])
                ->count();
            $completed = Submission::query()
                ->whereBetween('created_at', [$rangeFrom, $rangeUntil])
                ->where('status', 'completed')
                ->count();

            $sparkline = [];
            $perDay = Submission::query()
                ->whereBetween('created_at', [$weekFrom, $rangeUntil])
                ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
                ->groupByRaw('DATE(created_at)')
                ->pluck('total', 'day');
            foreach (range(0, 6) as $offset) {
                $sparkline[] = (int) ($perDay[$weekStart->addDays($offset)->toDateString()] ?? 0);
            }

            return [
                'available' => true,
                'today' => Submission::query()->whereBetween('created_at', [$today->startOfDay(), $rangeUntil])->count(),
                'week' => Submission::query()->whereBetween('created_at', [$weekFrom, $rangeUntil])->count(),
                'completion_rate' => $total > 0 ? $completed / $total : null,
                'completion_base' => $total,
                'failed' => Submission::query()
                    ->whereBetween('created_at', [$rangeFrom, $rangeUntil])
                    ->where('status', 'failed')
                    ->count(),
                'sparkline' => $sparkline,
            ];
        }, ['available' => false, 'today' => null, 'week' => null, 'completion_rate' => null, 'completion_base' => null, 'failed' => null, 'sparkline' => []]);
    }

    /**
     * @return array{available: bool, failed: int, stale: int, jobs: int, threshold_minutes: int, items: list<array{id: int, status: string, stage: ?string, updated_at: ?string, stale: bool}>}
     */
    public function needsAction(): array
    {
        return $this->remember('needs-action', function (): array {
            $threshold = $this->stale->staleAfterMinutes();
            $cutoff = now()->subMinutes($threshold);

            $failed = Submission::query()->where('status', 'failed')->count();
            $stale = Submission::query()->whereIn('status', ['pending', 'processing'])->where('updated_at', '<=', $cutoff)->count();

            $items = Submission::query()
                ->where(function ($query) use ($cutoff): void {
                    $query->where('status', 'failed')
                        ->orWhere(fn ($inner) => $inner->whereIn('status', ['pending', 'processing'])->where('updated_at', '<=', $cutoff));
                })
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get(['id', 'status', 'processing_stage', 'updated_at'])
                ->map(fn (Submission $submission): array => [
                    'id' => $submission->id,
                    'status' => (string) $submission->status,
                    'stage' => $submission->processing_stage,
                    'updated_at' => $submission->updated_at?->toIso8601String(),
                    'stale' => in_array($submission->status, ['pending', 'processing'], true),
                ])
                ->all();

            // Queue-level failures are a separate signal: one job may cover many
            // submissions, and a submission may fail without a failed job.
            $jobs = DB::table('failed_jobs')->count();

            return ['available' => true, 'failed' => $failed, 'stale' => $stale, 'jobs' => $jobs, 'threshold_minutes' => $threshold, 'items' => $items];
        }, ['available' => false, 'failed' => 0, 'stale' => 0, 'jobs' => 0, 'threshold_minutes' => $this->stale->staleAfterMinutes(), 'items' => []]);
    }

    /**
     * @return array{available: bool, labels: array<string, int>, inputs: array<string, int>, failures: array<string, int>, feedback: array{correct: int, incorrect: int}, latency: array{p50: ?int, p95: ?int, sample: int, minimum: int}}
     */
    public function breakdown(int $days): array
    {
        return $this->remember('breakdown:'.$days, function () use ($days): array {
            $start = CarbonImmutable::today(config('app.timezone'))->subDays($days - 1);
            $from = $start->startOfDay();
            $until = CarbonImmutable::today(config('app.timezone'))->endOfDay();

            $labels = DetectionResult::query()
                ->join('submissions', 'submissions.id', '=', 'detection_results.submission_id')
                ->whereBetween('submissions.created_at', [$from, $until])
                ->selectRaw('detection_results.label as label, COUNT(*) as total')
                ->groupBy('detection_results.label')
                ->pluck('total', 'label')
                ->map(fn ($value): int => (int) $value)
                ->all();

            $inputs = Submission::query()
                ->whereBetween('created_at', [$from, $until])
                ->selectRaw('input_type, COUNT(*) as total')
                ->groupBy('input_type')
                ->pluck('total', 'input_type')
                ->map(fn ($value): int => (int) $value)
                ->all();

            $failures = SubmissionProcessingEvent::query()
                ->where('outcome', EventOutcome::Failed->value)
                ->whereBetween('created_at', [$from, $until])
                ->selectRaw('stage, COUNT(*) as total')
                ->groupBy('stage')
                ->get()
                ->mapWithKeys(fn ($row): array => [(string) ($row->stage?->value ?? 'unknown') => (int) $row->total])
                ->all();

            $correct = Feedback::query()->where('is_correct', true)->whereBetween('created_at', [$from, $until])->count();
            $incorrect = Feedback::query()->where('is_correct', false)->whereBetween('created_at', [$from, $until])->count();

            $minimum = max(1, (int) config('pipeline.latency_minimum_sample', 20));
            $durations = DetectionResult::query()
                ->whereNotNull('inference_ms')
                ->where('inference_ms', '>=', 0)
                ->whereBetween('created_at', [$from, $until])
                ->orderBy('inference_ms')
                ->pluck('inference_ms')
                ->map(fn ($value): int => (int) $value)
                ->all();
            $sample = count($durations);
            $percentile = function (float $p) use ($durations): ?int {
                if ($durations === []) {
                    return null;
                }
                // Nearest-rank: p95 of n samples is ceil(0.95·n), not an interpolation.
                $rank = (int) ceil(($p / 100) * count($durations));

                return $durations[max(0, min(count($durations) - 1, $rank - 1))];
            };

            return [
                'available' => true,
                'labels' => $labels,
                'inputs' => $inputs,
                'failures' => $failures,
                'feedback' => ['correct' => $correct, 'incorrect' => $incorrect],
                'latency' => [
                    'p50' => $sample >= $minimum ? $percentile(50) : null,
                    'p95' => $sample >= $minimum ? $percentile(95) : null,
                    'sample' => $sample,
                    'minimum' => $minimum,
                ],
            ];
        }, [
            'available' => false, 'labels' => [], 'inputs' => [], 'failures' => [],
            'feedback' => ['correct' => 0, 'incorrect' => 0],
            'latency' => ['p50' => null, 'p95' => null, 'sample' => 0, 'minimum' => max(1, (int) config('pipeline.latency_minimum_sample', 20))],
        ]);
    }

    /**
     * @return array{available: bool, period: string, spent_usd: ?float, quota_usd: float, ratio: ?float}
     */
    public function openAiBudget(): array
    {
        return $this->remember('openai-budget', function (): array {
            $period = now(config('app.timezone'))->format('Y-m');
            $row = DB::table('openai_usage_periods')->where('period', $period)->first();
            $quota = (float) config('services.openai.monthly_quota_usd', 25.0);
            $spent = $row === null ? 0.0 : ((int) $row->spent_microusd + (int) $row->reserved_microusd) / 1_000_000;

            return [
                'available' => true,
                'period' => $period,
                'spent_usd' => round($spent, 4),
                'quota_usd' => $quota,
                'ratio' => $quota > 0 ? min(1.0, $spent / $quota) : null,
            ];
        }, ['available' => false, 'period' => now(config('app.timezone'))->format('Y-m'), 'spent_usd' => null, 'quota_usd' => (float) config('services.openai.monthly_quota_usd', 25.0), 'ratio' => null]);
    }

    /**
     * @return array{available: bool, curated: array<string, int>, curated_total: int}
     */
    public function curation(): array
    {
        return $this->remember('curation', function (): array {
            $rows = DB::table('datasets')
                ->whereNotNull('verified_by')
                ->selectRaw('label, COUNT(*) as total')
                ->groupBy('label')
                ->pluck('total', 'label')
                ->map(fn ($value): int => (int) $value)
                ->all();

            return ['available' => true, 'curated' => $rows, 'curated_total' => array_sum($rows)];
        }, ['available' => false, 'curated' => [], 'curated_total' => 0]);
    }

    /**
     * Drop the cached aggregates a mutation invalidates.
     *
     * Callers that change submission or feedback state (retry, review) use this so
     * the dashboard reflects the change on the next request instead of waiting for
     * the 60-second window. Range-keyed entries are removed by pattern because the
     * caller does not know which ranges are cached.
     *
     * @param  list<string>  $keys  Logical keys: 'needs-action', 'kpis', 'breakdown'.
     */
    public static function forget(array $keys): void
    {
        foreach ($keys as $key) {
            if (in_array($key, ['needs-action'], true)) {
                Cache::forget('admin:dashboard:'.$key);

                continue;
            }

            // kpis:7 / kpis:30 / breakdown:90 — every range must go.
            foreach (array_keys(self::RANGES) as $days) {
                Cache::forget('admin:dashboard:'.$key.':'.$days);
            }
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @param  T  $fallback
     * @return T
     */
    private function remember(string $key, callable $callback, mixed $fallback): mixed
    {
        try {
            return Cache::remember('admin:dashboard:'.$key, self::CACHE_SECONDS, $callback);
        } catch (PDOException $exception) {
            report($exception);

            return $fallback;
        } catch (Throwable $exception) {
            // A missing cache store must not take the dashboard down either.
            report($exception);

            try {
                return $callback();
            } catch (PDOException $inner) {
                report($inner);

                return $fallback;
            }
        }
    }
}
