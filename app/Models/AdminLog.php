<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminLog extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'admin_id',
        'action',
        'target_table',
        'target_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function scopeByAdmin(Builder $query, User|int $admin): void
    {
        $query->where('admin_id', $admin instanceof User ? $admin->getKey() : $admin);
    }

    public function scopeForTarget(Builder $query, string $table, ?int $id = null): void
    {
        $query->where('target_table', $table)
            ->when($id !== null, fn (Builder $targetQuery) => $targetQuery->where('target_id', $id));
    }

    public function scopeWithAction(Builder $query, string $action): void
    {
        $query->where('action', $action);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
