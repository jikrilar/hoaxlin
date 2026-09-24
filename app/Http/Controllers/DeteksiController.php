<?php

namespace App\Http\Controllers;

use App\Enums\ProcessingStage;
use App\Models\Submission;
use App\Services\Export\SubmissionCsvExporter;
use App\Services\Pipeline\PipelineFailureReporter;
use App\Services\SubmissionAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeteksiController extends Controller
{
    public function __construct(
        private readonly SubmissionAccess $access,
        private readonly PipelineFailureReporter $failures,
        private readonly SubmissionCsvExporter $csvExporter,
    ) {}

    // ── Show Results Page ─────────────────────────────────────────────────────
    public function hasil(Request $request, string $id): View
    {
        $submission = Submission::with(['detectionResult', 'feedbacks'])->findOrFail($id);
        $this->access->authorize($request, $submission);

        $result = $submission->isCompleted() ? $submission->detectionResult : null;
        if ($result !== null) {
            $submission->load(['evidenceReferences' => fn ($query) => $query
                ->orderBy('rank')
                ->orderBy('document_id')]);
        }
        $feedback = auth()->check()
            ? $submission->feedbacks->firstWhere('user_id', auth()->id())
            : null;

        $failureReason = $submission->isFailed()
            ? $this->failures->publicMessageForCode($submission->last_error_code)
            : null;

        return view('hasil', compact('submission', 'result', 'feedback', 'failureReason'));
    }

    // ── Polling endpoint for progress bar (Livewire fallback + JS) ───────────
    public function status(Request $request, string $id)
    {
        $submission = Submission::with('detectionResult')->findOrFail($id);
        $this->access->authorize($request, $submission);

        $stage = ProcessingStage::tryFrom($submission->processing_stage ?? '');
        $progress = match (true) {
            $submission->isCompleted() => 100,
            $stage !== null => $stage->progressPercentage(),
            $submission->status === 'pending' => 5,
            $submission->status === 'processing' => 30,
            default => 0,
        };
        $result = $submission->isCompleted() ? $submission->detectionResult : null;

        return response()->json([
            'id' => $submission->id,
            'status' => $submission->status,
            'processing_stage' => $submission->processing_stage,
            'stage_label' => match (true) {
                $submission->isCompleted() => 'Selesai',
                $submission->isFailed() => 'Gagal',
                default => $stage?->label() ?? ucfirst(str_replace('_', ' ', $submission->processing_stage ?? $submission->status)),
            },
            'progress' => $progress,
            'is_completed' => $submission->isCompleted(),
            'is_failed' => $submission->isFailed(),
            'failure_reason' => $submission->isFailed()
                ? $this->failures->publicMessageForCode($submission->last_error_code)
                : null,
            'label' => $result?->label,
            'confidence_score' => $result?->confidence_score,
        ]);
    }

    // ── PDF Export (E1) ───────────────────────────────────────────────────────
    public function pdf(Request $request, string $id)
    {
        $submission = Submission::with('detectionResult')->findOrFail($id);
        $this->access->authorize($request, $submission);

        if (! $submission->isCompleted() || ! $submission->detectionResult) {
            abort(404, 'Hasil belum tersedia.');
        }

        $pdf = Pdf::loadView('hasil-pdf', [
            'submission' => $submission,
            'result' => $submission->detectionResult,
        ])->setPaper('a4', 'portrait');

        $filename = 'hoaxlin-'.$submission->id.'-'.now()->format('Ymd').'.pdf';

        return $pdf->download($filename);
    }

    // ── CSV Export (E2) ───────────────────────────────────────────────────────
    public function csv(Request $request): StreamedResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="hoaxlin-riwayat-'.now()->format('Ymd').'.csv"',
        ];

        $callback = function () use ($user): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                throw new \RuntimeException('Unable to open the CSV output stream.');
            }

            try {
                $this->csvExporter->write($user, $out);
            } finally {
                fclose($out);
            }
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── Media Preview (E6) ────────────────────────────────────────────────────
    public function media(Request $request, string $id)
    {
        $submission = Submission::findOrFail($id);
        $this->access->authorize($request, $submission);

        if (! $submission->media_path) {
            abort(404);
        }

        $disk = config('filesystems.media_disk', config('filesystems.default', 'local'));
        $storage = Storage::disk($disk);

        if (! $storage->exists($submission->media_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        // For S3, use temporaryUrl; for local, use temporaryUrl if supported or download
        if (method_exists($storage, 'temporaryUrl')) {
            try {
                $url = $storage->temporaryUrl($submission->media_path, now()->addMinutes(5));

                return redirect()->away($url);
            } catch (\Throwable) {
                // Fallback to download for local
            }
        }

        return $storage->download($submission->media_path);
    }
}
