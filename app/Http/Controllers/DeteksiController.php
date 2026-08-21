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
}
