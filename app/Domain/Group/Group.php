<?php

namespace App\Domain\Group;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Group extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'owner_id',
        'turn_algorithm',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Activity Log Configuration
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'turn_algorithm', 'settings'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Domain Methods
    public function addMember(\App\Domain\User\User $user, string $role = 'member'): void
    {
        if (!$this->hasMember($user)) {
            $this->members()->attach($user->id, [
                'role' => $role,
                'joined_at' => now(),
            ]);
        }
    }

    public function removeMember(\App\Domain\User\User $user): void
    {
        $this->members()->detach($user->id);
    }

    public function hasMember(\App\Domain\User\User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function isOwner(\App\Domain\User\User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function getMemberRole(\App\Domain\User\User $user): ?string
    {
        $member = $this->members()->where('user_id', $user->id)->first();
        return $member?->pivot?->role;
    }

    public function canUserManage(\App\Domain\User\User $user): bool
    {
        return $this->isOwner($user) || 
               in_array($this->getMemberRole($user), ['admin', 'moderator']);
    }

    public function getNextTurn(): ?\App\Domain\Turn\Turn
    {
        return $this->turns()
            ->where('status', 'pending')
            ->orderBy('sequence')
            ->first();
    }

    public function getCurrentTurn(): ?\App\Domain\Turn\Turn
    {
        return $this->turns()
            ->where('status', 'active')
            ->first();
    }

    public function updateSettings(array $settings): void
    {
        $currentSettings = $this->settings ?? [];
        $this->update([
            'settings' => array_merge($currentSettings, $settings)
        ]);
    }

    // Accessors & Mutators
    protected function memberCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->members()->count(),
        );
    }

    protected function activeMembers(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->members()->active()->count(),
        );
    }

    // Relationships
    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domain\User\User::class, 'group_members')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function turns(): HasMany
    {
        return $this->hasMany(\App\Domain\Turn\Turn::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOwnedBy($query, \App\Domain\User\User $user)
    {
        return $query->where('owner_id', $user->id);
    }

    public function scopeWhereUserIsMember($query, \App\Domain\User\User $user)
    {
        return $query->whereHas('members', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }
}
