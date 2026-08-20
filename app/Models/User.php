<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
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
        'pinned_dashboard_chart',
        'marketing_emails_opted_in',
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
        'marketing_emails_opted_in' => 'boolean',
        'marketing_unsubscribed_at' => 'datetime',
    ];

    protected $appends = [
        'is_owner',
    ];

    protected $attributes = [
        'marketing_emails_opted_in' => true,
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

    public function wantsMarketingEmails(): bool
    {
        return $this->marketing_emails_opted_in !== false;
    }

    public function unsubscribeFromMarketing(): void
    {
        $this->forceFill([
            'marketing_emails_opted_in' => false,
            'marketing_unsubscribed_at' => now(),
        ])->save();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}
