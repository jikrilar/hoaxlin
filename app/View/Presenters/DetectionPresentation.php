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

    /** @return array{text: string, tone: string, abstained: bool} */
    public static function label(?string $label): array
    {
        return match (strtolower($label ?? '')) {
            'valid' => ['text' => 'Prediksi model: Valid', 'tone' => 'neutral', 'abstained' => false],
            'hoax' => ['text' => 'Prediksi model: Hoaks', 'tone' => 'danger', 'abstained' => false],
            'meragukan' => ['text' => 'Informasi belum terverifikasi oleh sumber terpercaya', 'tone' => 'warning', 'abstained' => true],
            default => ['text' => 'Label prediksi belum tersedia', 'tone' => 'neutral', 'abstained' => false],
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
