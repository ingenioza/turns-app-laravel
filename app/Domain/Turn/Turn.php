<?php

namespace App\Domain\Turn;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Carbon\Carbon;

enum TurnStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case SKIPPED = 'skipped';
}

class Turn extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'group_id',
        'user_id',
        'sequence',
        'status',
        'started_at',
        'completed_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'status' => TurnStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Activity Log Configuration
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'started_at', 'completed_at', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Domain Methods
    public function start(): void
    {
        if ($this->status !== TurnStatus::PENDING) {
            throw new \InvalidArgumentException('Only pending turns can be started');
        }

        $this->update([
            'status' => TurnStatus::ACTIVE,
            'started_at' => Carbon::now(),
        ]);
    }

    public function complete(?string $notes = null): void
    {
        if ($this->status !== TurnStatus::ACTIVE) {
            throw new \InvalidArgumentException('Only active turns can be completed');
        }

        $this->update([
            'status' => TurnStatus::COMPLETED,
            'completed_at' => Carbon::now(),
            'notes' => $notes,
        ]);
    }

    public function skip(?string $reason = null): void
    {
        if (!in_array($this->status, [TurnStatus::PENDING, TurnStatus::ACTIVE])) {
            throw new \InvalidArgumentException('Only pending or active turns can be skipped');
        }

        $metadata = $this->metadata ?? [];
        if ($reason) {
            $metadata['skip_reason'] = $reason;
        }

        $this->update([
            'status' => TurnStatus::SKIPPED,
            'completed_at' => Carbon::now(),
            'metadata' => $metadata,
        ]);
    }

    public function getDuration(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffInSeconds($this->completed_at);
        }

        return null;
    }

    public function isActive(): bool
    {
        return $this->status === TurnStatus::ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === TurnStatus::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === TurnStatus::COMPLETED;
    }

    public function isSkipped(): bool
    {
        return $this->status === TurnStatus::SKIPPED;
    }

    public function canBeStartedBy(\App\Domain\User\User $user): bool
    {
        return $this->user_id === $user->id || 
               $this->group->canUserManage($user);
    }

    public function canBeCompletedBy(\App\Domain\User\User $user): bool
    {
        return $this->user_id === $user->id || 
               $this->group->canUserManage($user);
    }

    // Relationships
    public function group(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Group\Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\User::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', TurnStatus::PENDING);
    }

    public function scopeActive($query)
    {
        return $query->where('status', TurnStatus::ACTIVE);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', TurnStatus::COMPLETED);
    }

    public function scopeSkipped($query)
    {
        return $query->where('status', TurnStatus::SKIPPED);
    }

    public function scopeForUser($query, \App\Domain\User\User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForGroup($query, \App\Domain\Group\Group $group)
    {
        return $query->where('group_id', $group->id);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }
}
