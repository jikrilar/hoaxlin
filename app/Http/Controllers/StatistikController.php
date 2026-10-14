<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StatistikController extends Controller
{
    public function index(): View
    {
        $timezone = config('app.timezone');
        $generatedAt = now($timezone)->toImmutable();
        $monthStart = $generatedAt->startOfMonth();

        $trend = collect(range(11, 0))->map(function ($m) use ($monthStart) {
            $date = $monthStart->subMonths($m);
            $next = $date->addMonth();

            return [
                'label' => $date->format('M Y'),
                'month' => $date->format('Y-m'),
                'display_label' => $date->locale('id')->translatedFormat('F Y'),
                'total' => $this->submissionsInMonth($date, $next)->count(),
                'hoax' => $this->submissionsInMonth($date, $next)
                    ->whereHas('detectionResult', fn ($q) => $q->where('label', 'hoax'))
                    ->count(),
                'valid' => $this->submissionsInMonth($date, $next)
                    ->whereHas('detectionResult', fn ($q) => $q->where('label', 'valid'))
                    ->count(),
            ];
        });

        $byTopic = DB::table('submissions')
            ->join('detection_results', 'submissions.id', '=', 'detection_results.submission_id')
            ->select('detection_results.label', DB::raw('count(*) as c'))
            ->groupBy('detection_results.label')
            ->pluck('c', 'label')
            ->all();

        $period = [
            'start' => $monthStart->subMonths(11),
            'end_exclusive' => $monthStart->addMonth(),
            'generated_at' => $generatedAt,
            'timezone' => $timezone,
        ];

        return view('statistik', compact('trend', 'byTopic', 'period'));
    }

    /**
     * Build a half-open monthly range so the next month's first instant is
     * never counted in both buckets. Boundaries are calculated in the
     * application timezone configured for the deployment.
     */
    private function submissionsInMonth(CarbonImmutable $monthStart, CarbonImmutable $nextMonthStart): Builder
    {
        return Submission::query()
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<', $nextMonthStart);
    }
}
