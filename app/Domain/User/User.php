<?php

namespace App\Domain\User;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable;

    protected $fillable = [
        'firebase_uid',
        'name',
        'email',
        'username',
        'password',
        'avatar_url',
        'email_verified_at',
        'last_active_at',
        'status',
        'settings',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_active_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Activity Log Configuration
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Domain Methods
    public function isActive(): bool
    {
        return $this->last_active_at?->isAfter(Carbon::now()->subMinutes(15)) ?? false;
    }

    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    public function markAsActive(): void
    {
        $this->update(['last_active_at' => Carbon::now()]);
    }

    public function updateProfile(array $data): void
    {
        $this->update(array_filter([
            'name' => $data['name'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? null,
        ]));
    }

    // Relationships
    public function groups()
    {
        return $this->belongsToMany(\App\Domain\Group\Group::class, 'group_member')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function ownedGroups()
    {
        return $this->hasMany(\App\Domain\Group\Group::class, 'owner_id');
    }

    public function turns()
    {
        return $this->hasMany(\App\Domain\Turn\Turn::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('last_active_at', '>', Carbon::now()->subMinutes(15));
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }
}
