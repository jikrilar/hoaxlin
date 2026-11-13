<?php

namespace App\Filament\Support;

use App\Enums\ProcessingStage;
use App\Enums\SubmissionStatus;
use App\Models\DetectionResult;
use App\Models\Submission;
use App\View\Presenters\DetectionPresentation;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/** Presentation of persisted admin records; never changes pipeline state. */
final class ReadOnlyPresentation
{
    public const RESULT_NOTICE = 'Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif. Record classifier dapat tersedia sebelum submission selesai.';

    public const FEEDBACK_NOTICE = 'Penilaian diberikan oleh pengguna terhadap prediksi model. Feedback bukan verifikasi fakta dan tidak otomatis mengubah label classifier.';

    public static function status(?string $state): string
    {
        return SubmissionStatus::tryFrom($state ?? '')?->label() ?? 'Status belum tersedia';
    }

    public static function stage(?string $state): string
    {
        return ProcessingStage::tryFrom($state ?? '')?->label() ?? 'Tahap belum tercatat';
    }

    public static function submissionResult(Submission $record): string
    {
        $result = $record->detectionResult;

        if (DetectionPresentation::finalResult($record, $result) !== null) {
            return DetectionPresentation::label($result->label)['text'];
        }

        return $result === null ? 'Hasil belum tersedia' : 'Record classifier parsial; bukan hasil final';
    }

    public static function resultContext(DetectionResult $record): string
    {
        return $record->submission !== null && DetectionPresentation::finalResult($record->submission, $record) !== null
            ? 'Record classifier pada submission selesai'
            : 'Record classifier parsial; bukan hasil final';
    }

    public static function resultLabel(DetectionResult $record): string
    {
        return DetectionPresentation::label($record->label)['text'];
    }

    public static function confidence(mixed $state): ?string
    {
        if (! is_numeric($state) || ! is_finite((float) $state) || (float) $state < 0 || (float) $state > 1) {
            return null;
        }

        return rtrim(rtrim(number_format((float) $state * 100, 2, ',', ''), '0'), ',').'%';
    }

    public static function assessment(bool $state): string
    {
        return $state ? 'Dinilai sesuai' : 'Dinilai tidak sesuai';
    }

    /**
     * Operational status tone. Never the prediction tones: a failed run is not a hoax.
     */
    public static function statusColor(?string $state): string
    {
        return match (SubmissionStatus::tryFrom($state ?? '')) {
            SubmissionStatus::Completed => 'success',
            SubmissionStatus::Processing => 'warning',
            SubmissionStatus::Failed => 'danger',
            default => 'gray',
        };
    }

    public static function statusIcon(?string $state): BackedEnum
    {
        return match (SubmissionStatus::tryFrom($state ?? '')) {
            SubmissionStatus::Completed => Heroicon::OutlinedCheckCircle,
            SubmissionStatus::Processing => Heroicon::OutlinedArrowPath,
            SubmissionStatus::Failed => Heroicon::OutlinedXCircle,
            default => Heroicon::OutlinedClock,
        };
    }

    /**
     * `valid` stays neutral, mirroring `DetectionPresentation::label()`: a neutral
     * badge must not read as verification of the content.
     */
    public static function labelColor(?string $label): string
    {
        return match (strtolower($label ?? '')) {
            'hoax' => 'danger',
            'meragukan' => 'warning',
            default => 'gray',
        };
    }

    public static function labelIcon(?string $label): BackedEnum
    {
        return match (strtolower($label ?? '')) {
            'hoax' => Heroicon::OutlinedXCircle,
            'meragukan' => Heroicon::OutlinedQuestionMarkCircle,
            'valid' => Heroicon::OutlinedCheckCircle,
            default => Heroicon::OutlinedClock,
        };
    }

    public static function submissionResultColor(Submission $record): string
    {
        return DetectionPresentation::finalResult($record, $record->detectionResult) === null
            ? 'gray'
            : self::labelColor($record->detectionResult?->label);
    }

    public static function submissionResultIcon(Submission $record): BackedEnum
    {
        return DetectionPresentation::finalResult($record, $record->detectionResult) === null
            ? Heroicon::OutlinedClock
            : self::labelIcon($record->detectionResult?->label);
    }

    public static function assessmentColor(bool $state): string
    {
        return $state ? 'success' : 'danger';
    }

    public static function assessmentIcon(bool $state): BackedEnum
    {
        return $state ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle;
    }

    public static function explanation(DetectionResult $record): string
    {
        return match ($record->explanation_status) {
            'ready' => filled($record->explanation) ? $record->explanation : 'Penjelasan tidak tersedia.',
            'pending' => 'Penjelasan sedang disusun.',
            'unavailable' => 'Penjelasan tidak tersedia.',
            default => 'Status penjelasan belum tersedia.',
        };
    }
}
