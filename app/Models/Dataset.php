<?php

namespace App\Models;

use App\Rules\AdminUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;

class Dataset extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'text',
        'label',
        'source',
        'verified_by',
    ];

    protected static function booted(): void
    {
        static::saving(function (Dataset $dataset): void {
            Validator::make(
                ['verified_by' => $dataset->verified_by],
                ['verified_by' => ['nullable', new AdminUser]],
            )->validate();
        });
    }

    public function scopeWithLabel(Builder $query, string $label): void
    {
        $query->where('label', $label);
    }

    public function scopeVerified(Builder $query): void
    {
        $query->whereNotNull('verified_by');
    }

    public function scopeUnverified(Builder $query): void
    {
        $query->whereNull('verified_by');
    }

    public function scopeFromSource(Builder $query, string $source): void
    {
        $query->where('source', $source);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
