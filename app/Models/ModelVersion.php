<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ModelVersion extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_TRAINING = 'training';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_TRAINING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'version_name',
        'dataset_path',
        'total_rows',
        'status',
        'accuracy',
        'f1_score',
        'baseline_accuracy',
        'baseline_f1_score',
        'training_duration',
        'error_message',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'total_rows' => 0,
        'status' => self::STATUS_PENDING,
        'is_active' => false,
    ];

    protected bool $activatingExplicitly = false;

    protected bool $pendingSoleActiveEnforcement = false;

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'accuracy' => 'float',
            'f1_score' => 'float',
            'baseline_accuracy' => 'float',
            'baseline_f1_score' => 'float',
            'training_duration' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function setAttribute($key, $value): mixed
    {
        if ($key === 'is_active') {
            $this->activatingExplicitly = filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return parent::setAttribute($key, $value);
    }

    protected static function booted(): void
    {
        static::saving(function (ModelVersion $modelVersion): void {
            $modelVersion->pendingSoleActiveEnforcement = $modelVersion->is_active && (
                ! $modelVersion->exists
                || $modelVersion->isDirty('is_active')
                || $modelVersion->activatingExplicitly
            );

            $modelVersion->activatingExplicitly = false;
        });

        static::saved(function (ModelVersion $modelVersion): void {
            $shouldEnforce = $modelVersion->pendingSoleActiveEnforcement;
            $modelVersion->pendingSoleActiveEnforcement = false;

            if (! $shouldEnforce || ! $modelVersion->is_active) {
                return;
            }

            DB::transaction(function () use ($modelVersion): void {
                static::query()
                    ->whereKeyNot($modelVersion->getKey())
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                static::query()
                    ->whereKey($modelVersion->getKey())
                    ->where('is_active', false)
                    ->update([
                        'is_active' => true,
                        'updated_at' => $modelVersion->freshTimestampString(),
                    ]);
            });
        });
    }

    /**
     * Atomically mark this model version as the sole active version.
     */
    public function activate(): self
    {
        return DB::transaction(function (): self {
            if (! $this->exists) {
                $this->is_active = true;
                $this->save();

                return $this->refresh();
            }

            static::query()
                ->whereKeyNot($this->getKey())
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $this->refresh();

            if (! $this->is_active) {
                $this->forceFill(['is_active' => true])->save();
            }

            return $this->refresh();
        });
    }

    /**
     * Activate a version by model instance or primary key and deactivate all others.
     */
    public static function activateVersion(self|int $version): self
    {
        $record = $version instanceof self
            ? $version
            : static::query()->findOrFail($version);

        return $record->activate();
    }

    public static function currentActive(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeWithStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isTraining(): bool
    {
        return $this->status === self::STATUS_TRAINING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
