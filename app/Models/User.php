<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            $isBeingDemoted = $user->isDirty('is_admin') && ! $user->is_admin;

            if ($isBeingDemoted && $user->verifiedDatasets()->exists()) {
                throw ValidationException::withMessages([
                    'is_admin' => 'Administrator masih tercatat sebagai verifier katalog dataset.',
                ]);
            }
        });
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function verifiedDatasets(): HasMany
    {
        return $this->hasMany(Dataset::class, 'verified_by');
    }

    public function adminLogs(): HasMany
    {
        return $this->hasMany(AdminLog::class, 'admin_id');
    }

    public function scopeAdmins(Builder $query): void
    {
        $query->where('is_admin', true);
    }

    public function scopeRegularUsers(Builder $query): void
    {
        $query->where('is_admin', false);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }
}
