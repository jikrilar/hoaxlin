<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use Illuminate\View\View;

class DeteksiController extends Controller
{
    // ── Show Results Page ─────────────────────────────────────────────────────
    public function hasil(string $id): View
    {
        $submission = Submission::with(['detectionResult', 'feedbacks'])->findOrFail($id);

        // Ownership protection (C8): user-owned submissions are only viewable
        // by their owner or an admin. Guest submissions (user_id null) remain
        // shareable via the link, but enumeration is rate-limited at the route.
        if ($submission->user_id !== null) {
            $user = auth()->user();
            if (! $user || ($user->getKey() !== $submission->user_id && ! $user->is_admin)) {
                abort(403, 'Akses ditolak. Hasil ini bukan milik Anda.');
            }
        }

        $result = $submission->detectionResult;
        $feedback = auth()->check()
            ? $submission->feedbacks->firstWhere('user_id', auth()->id())
            : null;

        return view('hasil', compact('submission', 'result', 'feedback'));
    }

    // ── Polling endpoint for progress bar (Livewire fallback + JS) ───────────
    public function status(string $id)
    {
        $submission = Submission::with('detectionResult')->findOrFail($id);

        if ($submission->user_id !== null) {
            $user = auth()->user();
            if (! $user || ($user->getKey() !== $submission->user_id && ! $user->is_admin)) {
                abort(403);
            }
        }

        $stage = \App\Enums\ProcessingStage::tryFrom($submission->processing_stage ?? '');
        $progress = $stage?->progressPercentage() ?? match ($submission->status) {
            'pending' => 5,
            'processing' => 30,
            'completed' => 100,
            'failed' => $stage?->progressPercentage() ?? 0,
            default => 0,
        };

        return response()->json([
            'id' => $submission->id,
            'status' => $submission->status,
            'processing_stage' => $submission->processing_stage,
            'stage_label' => $stage?->label() ?? ucfirst(str_replace('_', ' ', $submission->processing_stage ?? $submission->status)),
            'progress' => $progress,
            'has_result' => $submission->detectionResult !== null,
            'is_completed' => $submission->status === 'completed',
            'is_failed' => $submission->status === 'failed',
            'failure_reason' => $submission->failure_reason,
            'label' => $submission->detectionResult?->label,
            'confidence_score' => $submission->detectionResult?->confidence_score,
        ]);
    }
}
