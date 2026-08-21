<?php

namespace App\Http\Controllers;

use App\Http\Requests\HistoryRequest;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class RiwayatController extends Controller
{
    public function index(HistoryRequest $request): View
    {
        $filters = $request->validated();
        $query = Submission::query()
            ->with('detectionResult')
            ->forUser($request->user());

        $query
            ->when($filters['label'] ?? null, fn (Builder $query, string $label) => $query->withLabel($label))
            ->when($filters['input_type'] ?? null, fn (Builder $query, string $type) => $query->ofType($type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->withStatus($status))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('raw_input', 'like', "%{$search}%")
                        ->orWhere('extracted_text', 'like', "%{$search}%")
                        ->orWhere('source_url', 'like', "%{$search}%");
                });
            });

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest(),
            'confidence' => $query
                ->orderByDesc(Submission::query()
                    ->select('confidence_score')
                    ->from('detection_results')
                    ->whereColumn('detection_results.submission_id', 'submissions.id'))
                ->latest('submissions.id'),
            default => $query->latest(),
        };

        $submissions = $query->paginate(15)->withQueryString();
        $statsQuery = Submission::query()->forUser($request->user());

        return view('riwayat', [
            'submissions' => $submissions,
            'stats' => [
                'valid' => (clone $statsQuery)->withLabel('valid')->count(),
                'hoax' => (clone $statsQuery)->withLabel('hoax')->count(),
                'meragukan' => (clone $statsQuery)->withLabel('meragukan')->count(),
            ],
        ]);
    }

    public function show(string $id): View
    {
        $submission = Submission::with(['detectionResult', 'feedbacks'])
            ->forUser(request()->user())
            ->findOrFail($id);

        return view('hasil', [
            'submission' => $submission,
            'result' => $submission->detectionResult,
            'feedback' => $submission->feedbacks->firstWhere('user_id', request()->user()->getKey()),
        ]);
    }
}
