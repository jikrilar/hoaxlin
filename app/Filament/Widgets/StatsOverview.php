<?php

namespace App\Filament\Widgets;

use App\Models\DetectionResult;
use App\Models\Submission;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $total = Submission::count();
        $today = Submission::whereDate('created_at', today())->count();
        $pending = Submission::whereIn('status', ['pending', 'processing'])->count();
        $failed = Submission::where('status', 'failed')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        $labels = DetectionResult::select('label', DB::raw('count(*) as c'))
            ->groupBy('label')
            ->pluck('c', 'label')
            ->all();
        $valid = $labels['valid'] ?? 0;
        $hoax = $labels['hoax'] ?? 0;
        $meragukan = $labels['meragukan'] ?? 0;

        // 7-day sparkline for submissions
        $spark = collect(range(6, 0))->map(
            fn ($d) => Submission::whereDate('created_at', today()->subDays($d))->count()
        )->all();

        return [
            Stat::make('Total Submission', number_format($total))
                ->description($today.' hari ini')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($spark)
                ->color('primary'),
            Stat::make('Valid / Hoax / Meragukan', "{$valid} / {$hoax} / {$meragukan}")
                ->description('Distribusi label')
                ->color($hoax > $valid ? 'danger' : 'success'),
            Stat::make('Pending / Gagal', "{$pending} / {$failed}")
                ->description($failedJobs.' failed jobs')
                ->descriptionIcon($failed > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($failed > 0 ? 'danger' : 'success'),
        ];
    }
}
