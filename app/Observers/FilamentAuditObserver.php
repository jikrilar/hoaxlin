<?php

namespace App\Observers;

use App\Enums\DetectionLabel;
use App\Enums\InputType;
use App\Enums\ProcessingStage;
use App\Enums\SubmissionStatus;
use App\Models\AdminLog;
use App\Models\Dataset;
use App\Models\ModelVersion;
use App\Models\Submission;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class FilamentAuditObserver
{
    private const REDACTED = '[nilai disembunyikan]';

    public function created(Model $model): void
    {
        $this->log('create', $model);
    }

    public function updated(Model $model): void
    {
        $this->log('update', $model);
    }

    public function deleted(Model $model): void
    {
        $this->log('delete', $model);
    }

    private function log(string $action, Model $model): void
    {
        $user = Auth::user();
        if (! $user || ! $user->is_admin) {
            return;
        }

        // Only log from Filament (admin panel) — check if request is from /admin
        if (! request()->is('admin*')) {
            return;
        }

        AdminLog::create([
            'admin_id' => $user->getKey(),
            'action' => $action,
            'target_table' => $model->getTable(),
            'target_id' => $model->getKey(),
            'metadata' => [
                'model' => $model::class,
                // Deletion needs identity, not a snapshot or a previous update.
                'changes' => $action === 'delete' ? null : $this->safeChanges($model),
            ],
        ]);
    }

    /** Project raw attributes explicitly: Eloquent's $hidden does not filter getDirty(). */
    private function safeChanges(Model $model): ?array
    {
        $fields = match ($model::class) {
            User::class => ['id', 'name', 'email', 'password', 'remember_token', 'is_admin', 'email_verified_at', 'created_at', 'updated_at'],
            Dataset::class => ['id', 'text', 'label', 'source', 'verified_by', 'created_at', 'updated_at'],
            Submission::class => ['id', 'user_id', 'guest_access_token_hash', 'input_type', 'raw_input', 'extracted_text', 'source_language', 'translated_text', 'translation_provider', 'translation_model', 'translation_cached', 'translation_input_tokens', 'translation_output_tokens', 'translation_estimated_cost_usd', 'media_path', 'source_url', 'status', 'failure_reason', 'processing_stage', 'content_hash', 'processing_started_at', 'processing_completed_at', 'last_error_service', 'last_error_code', 'attempt_count', 'pipeline_version', 'created_at', 'updated_at'],
            ModelVersion::class => ['id', 'version_name', 'dataset_path', 'total_rows', 'status', 'accuracy', 'f1_score', 'baseline_accuracy', 'baseline_f1_score', 'training_duration', 'error_message', 'is_active', 'created_at', 'updated_at'],
            default => [],
        };
        $safe = [];
        foreach (array_intersect_key($model->getDirty(), array_flip($fields)) as $field => $value) {
            $safe[$field] = match ($field) {
                'id', 'user_id', 'verified_by', 'attempt_count', 'translation_input_tokens', 'translation_output_tokens', 'total_rows', 'training_duration' => $this->unsignedInteger($value),
                'is_admin', 'translation_cached', 'is_active' => in_array($value, [true, false, 0, 1, '0', '1'], true) ? (bool) $value : self::REDACTED,
                'label' => is_string($value) ? (DetectionLabel::tryFrom($value)?->value ?? self::REDACTED) : self::REDACTED,
                'input_type' => is_string($value) ? (InputType::tryFrom($value)?->value ?? self::REDACTED) : self::REDACTED,
                'status' => $model instanceof ModelVersion
                    ? (is_string($value) && in_array($value, ModelVersion::STATUSES, true) ? $value : self::REDACTED)
                    : (is_string($value) ? (SubmissionStatus::tryFrom($value)?->value ?? self::REDACTED) : self::REDACTED),
                'processing_stage' => is_string($value) ? (ProcessingStage::tryFrom($value)?->value ?? self::REDACTED) : self::REDACTED,
                'version_name' => $this->safeVersionName($value),
                'accuracy', 'f1_score', 'baseline_accuracy', 'baseline_f1_score' => $this->unitFloat($value),
                'email_verified_at', 'processing_started_at', 'processing_completed_at', 'created_at', 'updated_at' => $this->timestamp($value),
                'source_language' => $value === null ? null : (is_string($value) && preg_match('/\A[a-z]{2,3}(?:-[A-Z]{2})?\z/', $value) ? $value : self::REDACTED),
                'pipeline_version' => $value === null ? null : (is_string($value) && preg_match('/\Aindobert-hoax-v\d{1,5}\.\d{1,5}\.\d{1,5}\z/', $value) ? $value : self::REDACTED),
                // Keep evidence of a field change, never its free text, path, or credential.
                default => self::REDACTED,
            };
        }

        return $safe ?: null;
    }

    private function unsignedInteger(mixed $value): int|string|null
    {
        if ($value === null) {
            return null;
        }
        if (! is_int($value) && ! is_string($value)) {
            return self::REDACTED;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer !== false && $integer >= 0 ? $integer : self::REDACTED;
    }

    private function safeVersionName(mixed $value): string
    {
        if (
            is_string($value)
            && ! str_contains($value, '..')
            && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/', $value)
        ) {
            return $value;
        }

        return self::REDACTED;
    }

    private function unitFloat(mixed $value): float|string|null
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value) || (! is_int($value) && ! is_float($value) && ! is_string($value))) {
            return self::REDACTED;
        }
        $float = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($float === false || ! is_finite($float) || $float < 0.0 || $float > 1.0) {
            return self::REDACTED;
        }

        return round($float, 6);
    }

    private function timestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value) && preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $value)) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
            if ($date !== false && $date->format('Y-m-d H:i:s') === $value) {
                return $value;
            }
        }

        return self::REDACTED;
    }
}
