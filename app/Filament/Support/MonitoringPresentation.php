<?php

namespace App\Filament\Support;

use App\Enums\DetectionLabel;
use App\Enums\EventOutcome;
use App\Enums\InputType;
use App\Enums\ProcessingStage;
use App\Enums\SubmissionStatus;
use App\Models\AdminLog;
use App\Models\Dataset;
use App\Models\Submission;
use App\Models\SubmissionProcessingEvent;
use App\Models\User;
use App\Services\Pipeline\PipelineFailureReporter;

/** An explicit projection of operational data, never a dump of log payloads. */
final class MonitoringPresentation
{
    public const EVENTS_NOTICE = 'Catatan tahap, hasil operasi, dan waktu pipeline. Status operasi bukan penilaian benar atau palsu atas informasi.';

    public const LOGS_NOTICE = 'Catatan tindakan administrasi yang tersimpan, beserta target dan waktu operasi.';

    public const METADATA_NOTICE = 'Hanya metadata operasional yang dikenali ditampilkan. Nilai teks bebas, data sensitif, dan payload provider disembunyikan; data tersimpan tidak diubah.';

    private const HIDDEN = '[nilai disembunyikan]';

    public static function stage(?string $state): string
    {
        return ProcessingStage::tryFrom($state ?? '')?->label() ?? 'Tahap tidak dikenali';
    }

    public static function outcome(?string $state): string
    {
        return EventOutcome::tryFrom($state ?? '')?->label() ?? 'Hasil operasi tidak dikenali';
    }

    public static function action(?string $state): string
    {
        return match ($state) {
            'create' => 'Buat', 'update' => 'Ubah', 'delete' => 'Hapus',
            'login' => 'Masuk', 'dataset_updated' => 'Pembaruan dataset', 'user_updated' => 'Pembaruan pengguna',
            'system_configuration_updated' => 'Pembaruan konfigurasi',
            default => 'Tindakan tidak dikenali',
        };
    }

    public static function target(?string $state): string
    {
        return in_array($state, ['users', 'datasets', 'submissions', 'system'], true) ? $state : 'Target tidak dikenali';
    }

    public static function service(?string $state): string
    {
        return in_array($state, ['laravel', 'stored', 'article-parser', 'article', 'bert', 'openai', 'rag', 'watchdog', 'database', 'filesystem', 'pipeline', 'provider'], true)
            ? $state : 'Layanan tidak dikenali';
    }

    public static function errorCode(?string $state): string
    {
        return in_array($state, [
            PipelineFailureReporter::DEPENDENCY_UNAVAILABLE, PipelineFailureReporter::PROVIDER_REQUEST_REJECTED,
            PipelineFailureReporter::PERSISTENCE_ERROR, PipelineFailureReporter::MEDIA_IO_ERROR,
            PipelineFailureReporter::PIPELINE_CONFIGURATION_ERROR, PipelineFailureReporter::UNKNOWN_ERROR,
            'STALE_PROCESSING', 'STALE_RECOVERY_UNSAFE',
        ], true) ? $state : 'Kode tidak dikenali';
    }

    public static function duration(mixed $state): ?string
    {
        $value = self::unsignedInteger($state);

        return $value === null ? null : number_format($value, 0, ',', '.').' ms';
    }

    public static function eventMetadata(SubmissionProcessingEvent $record): string
    {
        $metadata = $record->metadata;
        $safe = [];
        foreach (['cached', 'translated', 'result_count', 'stale_after_minutes', 'error_reference', 'source_language', 'model', 'model_version', 'recovery_job', 'queue'] as $key) {
            if (! is_array($metadata) || ! array_key_exists($key, $metadata)) {
                continue;
            }
            $value = $metadata[$key];
            $safe[$key] = match ($key) {
                'cached', 'translated' => is_bool($value) ? $value : self::HIDDEN,
                'result_count', 'stale_after_minutes' => self::unsignedInteger($value) ?? self::HIDDEN,
                'error_reference' => is_string($value) && preg_match('/\A[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}\z/i', $value) ? $value : self::HIDDEN,
                'source_language' => is_string($value) && preg_match('/\A[a-z]{2,3}(?:-[A-Z]{2})?\z/', $value) ? $value : self::HIDDEN,
                'recovery_job' => in_array($value, ['ProcessSubmission', 'ClassifySubmission'], true) ? $value : self::HIDDEN,
                'queue' => in_array($value, ['default', 'extraction', 'inference', 'retrieval', 'explanation'], true) ? $value : self::HIDDEN,
                default => self::HIDDEN,
            };
        }

        return self::metadataText($metadata, $safe);
    }

    public static function logMetadata(AdminLog $record): string
    {
        $metadata = $record->metadata;
        $safe = [];
        if (is_array($metadata) && array_key_exists('model', $metadata)) {
            $safe['model'] = in_array($metadata['model'], [User::class, Dataset::class, Submission::class], true) ? $metadata['model'] : self::HIDDEN;
        }
        if (is_array($metadata) && array_key_exists('source', $metadata)) {
            $safe['source'] = $metadata['source'] === 'seeder' ? 'seeder' : self::HIDDEN;
        }
        if (is_array($metadata) && array_key_exists('changes', $metadata)) {
            $safe['changes'] = self::changes($record->target_table, $metadata['changes']);
        }

        return self::metadataText($metadata, $safe);
    }

    private static function changes(?string $table, mixed $changes): mixed
    {
        if ($changes === null) {
            return null;
        }
        if (! is_array($changes)) {
            return self::HIDDEN;
        }
        $fields = match ($table) {
            'users' => ['id', 'name', 'email', 'is_admin', 'email_verified_at', 'created_at', 'updated_at'],
            'datasets' => ['id', 'text', 'label', 'source', 'verified_by', 'created_at', 'updated_at'],
            'submissions' => ['id', 'user_id', 'input_type', 'raw_input', 'extracted_text', 'source_language', 'translated_text', 'translation_provider', 'translation_model', 'translation_cached', 'translation_input_tokens', 'translation_output_tokens', 'translation_estimated_cost_usd', 'media_path', 'source_url', 'status', 'failure_reason', 'processing_stage', 'processing_started_at', 'processing_completed_at', 'last_error_service', 'last_error_code', 'attempt_count', 'pipeline_version', 'created_at', 'updated_at'],
            default => [],
        };
        $safe = [];
        foreach ($fields as $field) {
            if (! array_key_exists($field, $changes)) {
                continue;
            }
            $value = $changes[$field];
            $safe[$field] = match ($field) {
                'id', 'user_id', 'verified_by', 'attempt_count', 'translation_input_tokens', 'translation_output_tokens' => $value === null ? null : (self::unsignedInteger($value) ?? self::HIDDEN),
                'is_admin', 'translation_cached' => in_array($value, [true, false, 0, 1, '0', '1'], true) ? (bool) $value : self::HIDDEN,
                'label' => is_string($value) ? (DetectionLabel::tryFrom($value)?->value ?? self::HIDDEN) : self::HIDDEN,
                'input_type' => is_string($value) ? (InputType::tryFrom($value)?->value ?? self::HIDDEN) : self::HIDDEN,
                'status' => is_string($value) ? (SubmissionStatus::tryFrom($value)?->value ?? self::HIDDEN) : self::HIDDEN,
                'processing_stage' => is_string($value) ? (ProcessingStage::tryFrom($value)?->value ?? self::HIDDEN) : self::HIDDEN,
                default => self::HIDDEN,
            };
        }

        return $safe ?: self::HIDDEN;
    }

    private static function unsignedInteger(mixed $value): ?int
    {
        if (! is_int($value) && ! is_string($value)) {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer !== false && $integer >= 0 ? $integer : null;
    }

    private static function metadataText(mixed $metadata, array $safe): string
    {
        if ($metadata === null || $metadata === []) {
            return 'Metadata tidak tersedia.';
        }

        return $safe === [] ? 'Tidak ada metadata operasional yang dapat ditampilkan.'
            : json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
