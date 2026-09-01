<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Submission extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'input_type',
        'raw_input',
        'extracted_text',
        'source_language',
        'translated_text',
        'translation_provider',
        'translation_model',
        'translation_cached',
        'translation_input_tokens',
        'translation_output_tokens',
        'translation_estimated_cost_usd',
        'media_path',
        'source_url',
        'status',
        'failure_reason',
        'processing_stage',
        'content_hash',
        'processing_started_at',
        'processing_completed_at',
        'last_error_service',
        'last_error_code',
        'attempt_count',
        'pipeline_version',
    ];

    protected function casts(): array
    {
        return [
            'processing_started_at' => 'datetime',
            'processing_completed_at' => 'datetime',
            'translation_cached' => 'boolean',
            'translation_input_tokens' => 'integer',
            'translation_output_tokens' => 'integer',
            'translation_estimated_cost_usd' => 'decimal:6',
        ];
    }

    protected function content(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->extracted_text ?? $this->raw_input);
    }

    protected function analysisText(): Attribute
    {
        return Attribute::get(fn (): ?string => filled($this->translated_text)
            ? $this->translated_text
            : $this->extracted_text);
    }

    protected function sourceUrl(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => $value === null ? null : trim($value));
    }

    public function scopeForUser(Builder $query, User|int $user): void
    {
        $query->where('user_id', $user instanceof User ? $user->getKey() : $user);
    }

    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('input_type', $type);
    }

    public function scopeWithStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', 'completed');
    }

    public function scopePending(Builder $query): void
    {
        $query->whereIn('status', ['pending', 'processing']);
    }

    public function scopeWithLabel(Builder $query, string $label): void
    {
        $query->whereHas(
            'detectionResult',
            fn (Builder $resultQuery) => $resultQuery->where('label', $label),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detectionResult(): HasOne
    {
        return $this->hasOne(DetectionResult::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function processingEvents(): HasMany
    {
        return $this->hasMany(SubmissionProcessingEvent::class);
    }
}
