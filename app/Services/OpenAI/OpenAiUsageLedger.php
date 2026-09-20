<?php

namespace App\Services\OpenAI;

use App\Exceptions\AiServiceException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OpenAiUsageLedger
{
    /** @return array{id: string, period: string, reserved_microusd: int} */
    public function reserve(string $operation, int $amountMicrousd, int $budgetMicrousd): array
    {
        $period = now(config('app.timezone'))->format('Y-m');

        return DB::transaction(function () use ($operation, $amountMicrousd, $budgetMicrousd, $period): array {
            DB::table('openai_usage_periods')->insertOrIgnore([
                'period' => $period,
                'spent_microusd' => 0,
                'reserved_microusd' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $usage = DB::table('openai_usage_periods')->where('period', $period)->lockForUpdate()->first();
            if ($usage === null || (int) $usage->spent_microusd + (int) $usage->reserved_microusd + $amountMicrousd > $budgetMicrousd) {
                throw AiServiceException::permanent('openai', 'Kuota bulanan OpenAI telah tercapai.', 429);
            }

            $id = (string) Str::uuid();
            DB::table('openai_usage_periods')->where('period', $period)->update([
                'reserved_microusd' => (int) $usage->reserved_microusd + $amountMicrousd,
                'updated_at' => now(),
            ]);
            DB::table('openai_usage_reservations')->insert([
                'id' => $id,
                'period' => $period,
                'operation' => $operation,
                'reserved_microusd' => $amountMicrousd,
                'status' => 'reserved',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['id' => $id, 'period' => $period, 'reserved_microusd' => $amountMicrousd];
        }, 3);
    }

    public function finalize(string $id, int $inputTokens, int $outputTokens, int $actualMicrousd): void
    {
        DB::transaction(function () use ($id, $inputTokens, $outputTokens, $actualMicrousd): void {
            $reservation = DB::table('openai_usage_reservations')->where('id', $id)->lockForUpdate()->first();
            if ($reservation === null || $reservation->status !== 'reserved') {
                return;
            }

            if ($actualMicrousd > (int) $reservation->reserved_microusd) {
                throw new \LogicException('Actual OpenAI usage exceeded its reserved maximum.');
            }

            $usage = DB::table('openai_usage_periods')->where('period', $reservation->period)->lockForUpdate()->first();
            if ($usage === null) {
                throw new \LogicException('OpenAI usage period is missing.');
            }

            DB::table('openai_usage_periods')->where('period', $reservation->period)->update([
                'spent_microusd' => (int) $usage->spent_microusd + $actualMicrousd,
                'reserved_microusd' => max(0, (int) $usage->reserved_microusd - (int) $reservation->reserved_microusd),
                'updated_at' => now(),
            ]);
            DB::table('openai_usage_reservations')->where('id', $id)->update([
                'actual_microusd' => $actualMicrousd,
                'input_tokens' => max(0, $inputTokens),
                'output_tokens' => max(0, $outputTokens),
                'status' => 'finalized',
                'finalized_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);
    }

    public function release(string $id): void
    {
        DB::transaction(function () use ($id): void {
            $reservation = DB::table('openai_usage_reservations')->where('id', $id)->lockForUpdate()->first();
            if ($reservation === null || $reservation->status !== 'reserved') {
                return;
            }

            $usage = DB::table('openai_usage_periods')->where('period', $reservation->period)->lockForUpdate()->first();
            if ($usage !== null) {
                DB::table('openai_usage_periods')->where('period', $reservation->period)->update([
                    'reserved_microusd' => max(0, (int) $usage->reserved_microusd - (int) $reservation->reserved_microusd),
                    'updated_at' => now(),
                ]);
            }

            DB::table('openai_usage_reservations')->where('id', $id)->update([
                'status' => 'released',
                'finalized_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);
    }
}
