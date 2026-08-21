<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetectionResult extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'submission_id',
        'label',
        'confidence_score',
        'model_version',
        'explanation',
        'raw_scores',
        'inference_ms',
        'classifier_cached',
        'explanation_status',
        'explanation_model',
        'explanation_cached',
        'prompt_tokens',
        'completion_tokens',
        'estimated_cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'decimal:4',
            'raw_scores' => 'array',
            'classifier_cached' => 'boolean',
            'explanation_cached' => 'boolean',
            'estimated_cost_usd' => 'decimal:6',
        ];
    }

    protected function confidencePercentage(): Attribute
    {
        return Attribute::get(
            fn (): float => round((float) $this->confidence_score * 100, 2),
        );
    }

    protected function labelDisplay(): Attribute
    {
        return Attribute::get(fn (): string => ucfirst($this->label));
    }

    public function scopeWithLabel(Builder $query, string $label): void
    {
        $query->where('label', $label);
    }

    public function scopeUsingModel(Builder $query, string $version): void
    {
        $query->where('model_version', $version);
    }

    public function scopeConfident(Builder $query, float $minimum = 0.8): void
    {
        $query->where('confidence_score', '>=', $minimum);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
