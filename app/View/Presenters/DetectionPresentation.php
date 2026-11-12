<?php

namespace App\View\Presenters;

use App\Models\DetectionResult;
use App\Models\Submission;
use Illuminate\Support\Collection;

/** Pure presentation mapping of authorized, persisted data; never queries. */
final class DetectionPresentation
{
    public static function finalResult(Submission $submission, ?DetectionResult $result): ?DetectionResult
    {
        return $submission->isCompleted() && $submission->getKey() !== null && $result !== null
            && (string) $result->submission_id === (string) $submission->getKey() ? $result : null;
    }

    /**
     * Canonical label text plus the plain-language sentence shown under it (T2.7).
     *
     * `text`/`tone` are the product contract and must not change; `description`
     * only restates the same meaning for readers and never claims verification.
     *
     * @return array{text: string, tone: string, abstained: bool, description: string}
     */
    public static function label(?string $label): array
    {
        return match (strtolower($label ?? '')) {
            'valid' => [
                'text' => 'Prediksi model: Valid',
                'tone' => 'neutral',
                'abstained' => false,
                'description' => 'Pola teks ini mirip dengan informasi yang valid. Ini belum menjamin isinya benar — tetap cek sumbernya.',
            ],
            'hoax' => [
                'text' => 'Prediksi model: Hoaks',
                'tone' => 'danger',
                'abstained' => false,
                'description' => 'Pola teks ini mirip dengan hoaks. Cek di sumber terpercaya sebelum membagikannya.',
            ],
            'meragukan' => [
                'text' => 'Informasi belum terverifikasi oleh sumber terpercaya',
                'tone' => 'warning',
                'abstained' => true,
                'description' => 'Model belum cukup yakin. Ini bukan bukti bahwa informasi benar atau palsu. Cari konfirmasi dari sumber resmi.',
            ],
            default => [
                'text' => 'Label prediksi belum tersedia',
                'tone' => 'neutral',
                'abstained' => false,
                'description' => 'Model belum dapat memilih label untuk input ini. Anggap hasil ini belum tersedia dan cek ulang nanti.',
            ],
        };
    }

    /**
     * Static next steps for a label (T2.7). No external links: the owner has not
     * approved a curated fact-check list (Appendix E-4).
     *
     * @return list<string>
     */
    public static function nextSteps(?string $label): array
    {
        $common = ['Periksa sumber asli informasi ini, bukan hanya kutipannya.', 'Cek tanggal dan konteks: informasi lama bisa beredar ulang dengan konteks berbeda.'];

        return match (strtolower($label ?? '')) {
            'valid' => [...$common, 'Bandingkan dengan satu atau dua sumber lain yang kredibel.', 'Tetap sebutkan sumber ketika membagikannya.'],
            'hoax' => [...$common, 'Bandingkan dengan sumber resmi atau media yang kredibel.', 'Jangan bagikan sebelum menemukan sumber yang dapat dipercaya.'],
            'meragukan' => [...$common, 'Cari konfirmasi dari sumber resmi sebelum mengambil kesimpulan.', 'Bila tetap ragu, jangan bagikan.'],
            default => [...$common, 'Bila tetap ragu, jangan bagikan.', 'Kirim ulang pemeriksaan setelah informasi lengkap.'],
        };
    }

    public static function confidence(mixed $score): ?string
    {
        if (! is_numeric($score) || ! is_finite((float) $score) || (float) $score < 0 || (float) $score > 1) {
            return null;
        }

        return round((float) $score * 100).'%';
    }

    public static function inputType(?string $type): string
    {
        return match ($type) {
            'text' => 'Teks', 'image' => 'Gambar', 'video' => 'Video',
            'video_url' => 'Tautan video', 'url' => 'Tautan artikel',
            default => 'Jenis input belum tersedia',
        };
    }

    public static function sourceUrl(?string $url): ?string
    {
        if ($url === null || preg_match('/[\\x00-\\x20\\x7f\\\\\\\\]|\\s/u', $url)) {
            return null;
        }
        $parts = parse_url($url);

        return is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && filled($parts['host'] ?? null) && ! isset($parts['user']) && ! isset($parts['pass']) ? $url : null;
    }

    public static function evidence(Collection $references): Collection
    {
        return $references->sort(fn ($a, $b) => ($a->rank <=> $b->rank)
            ?: strcmp((string) $a->document_id, (string) $b->document_id))->values();
    }
}
