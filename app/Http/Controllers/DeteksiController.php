<?php

namespace App\Http\Controllers;

use App\Enums\ProcessingStage;
use App\Models\Submission;
use App\Services\SubmissionAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeteksiController extends Controller
{
    public function __construct(private readonly SubmissionAccess $access) {}

    // ── Show Results Page ─────────────────────────────────────────────────────
    public function hasil(Request $request, string $id): View
    {
        $submission = Submission::with(['detectionResult', 'feedbacks'])->findOrFail($id);
        $this->access->authorize($request, $submission);

        $result = $submission->detectionResult;
        $feedback = auth()->check()
            ? $submission->feedbacks->firstWhere('user_id', auth()->id())
            : null;

        return view('hasil', compact('submission', 'result', 'feedback'));
    }

    // ── Polling endpoint for progress bar (Livewire fallback + JS) ───────────
    public function status(Request $request, string $id)
    {
        $submission = Submission::with('detectionResult')->findOrFail($id);
        $this->access->authorize($request, $submission);

        $stage = ProcessingStage::tryFrom($submission->processing_stage ?? '');
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

    // ── PDF Export (E1) ───────────────────────────────────────────────────────
    public function pdf(Request $request, string $id)
    {
        $submission = Submission::with('detectionResult')->findOrFail($id);
        $this->access->authorize($request, $submission);

        if (! $submission->detectionResult) {
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

        $submissions = Submission::with('detectionResult')
            ->forUser($user)
            ->latest()
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="hoaxlin-riwayat-'.now()->format('Ymd').'.csv"',
        ];

        $callback = function () use ($submissions) {
            $out = fopen('php://output', 'w');
            // BOM for Excel
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Tipe', 'Status', 'Label', 'Confidence', 'Model', 'Dibuat', 'Teks/URL']);
            foreach ($submissions as $s) {
                fputcsv($out, [
                    $s->id,
                    $s->input_type,
                    $s->status,
                    $s->detectionResult?->label ?? '-',
                    $s->detectionResult?->confidence_score ?? '-',
                    $s->detectionResult?->model_version ?? '-',
                    $s->created_at?->format('Y-m-d H:i'),
                    mb_substr($s->raw_input ?? $s->source_url ?? '', 0, 200),
                ]);
            }
            fclose($out);
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
