<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class StatistikController extends Controller
{
    public function index(): View
    {
        $trend = collect(range(11, 0))->map(function ($m) {
            $date = now()->subMonths($m)->startOfMonth();
            $next = $date->copy()->addMonth();
            return [
                'label' => $date->format('M Y'),
                'total' => Submission::whereBetween('created_at', [$date, $next])->count(),
                'hoax' => Submission::whereBetween('created_at', [$date, $next])->whereHas('detectionResult', fn ($q) => $q->where('label', 'hoax'))->count(),
                'valid' => Submission::whereBetween('created_at', [$date, $next])->whereHas('detectionResult', fn ($q) => $q->where('label', 'valid'))->count(),
            ];
        });

        $byTopic = DB::table('submissions')
            ->join('detection_results', 'submissions.id', '=', 'detection_results.submission_id')
            ->select('detection_results.label', DB::raw('count(*) as c'))
            ->groupBy('detection_results.label')
            ->pluck('c', 'label')
            ->all();

        return view('statistik', compact('trend', 'byTopic'));
    }
}
