<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Turn extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'user_id',
        'started_at',
        'ended_at',
        'status',
        'duration_seconds',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function complete(): bool
    {
        return $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function skip(): bool
    {
        return $this->update([
            'status' => 'skipped',
            'completed_at' => now(),
        ]);
    }
}
