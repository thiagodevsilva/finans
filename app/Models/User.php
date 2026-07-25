<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    public const ROLE_OWNER = 'owner';

    public const ROLE_DEPENDENT = 'dependent';

    public const ONLINE_MINUTES = 29;

    public const ONBOARDING_SKIPPED = 'skipped';

    public const ONBOARDING_COMPLETED = 'completed';

    protected $fillable = [
        'account_id',
        'name',
        'email',
        'password',
        'role',
        'is_admin',
        'last_seen_at',
        'onboarding_status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    protected $appends = [
        'is_owner',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function getIsOwnerAttribute(): bool
    {
        return $this->isOwner();
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gte(now()->subMinutes(self::ONLINE_MINUTES));
    }
}
