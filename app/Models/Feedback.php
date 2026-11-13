<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'submission_id',
        'user_id',
        'is_correct',
        'comment',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function scopeCorrect(Builder $query): void
    {
        $query->where('is_correct', true);
    }

    public function scopeIncorrect(Builder $query): void
    {
        $query->where('is_correct', false);
    }

    /** Feedback nobody has reviewed yet (T3.7). */
    public function scopePendingReview(Builder $query): void
    {
        $query->whereNull('reviewed_at');
    }

    public function scopeReviewed(Builder $query): void
    {
        $query->whereNotNull('reviewed_at');
    }

    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }

    public function scopeForUser(Builder $query, User|int $user): void
    {
        $query->where('user_id', $user instanceof User ? $user->getKey() : $user);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
