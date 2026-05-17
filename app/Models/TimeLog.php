<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeLog extends Model
{
    use HasFactory;

    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [self::STATUS_RUNNING, self::STATUS_PAUSED, self::STATUS_COMPLETED];

    protected $fillable = [
        'ticket_id',
        'user_id',
        'client_id',
        'software_id',
        'started_at',
        'paused_at',
        'resumed_at',
        'ended_at',
        'duration_seconds',
        'status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'paused_at' => 'datetime',
        'resumed_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function software(): BelongsTo
    {
        return $this->belongsTo(Software::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_RUNNING, self::STATUS_PAUSED]);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function currentDurationSeconds(): int
    {
        if ($this->status !== self::STATUS_RUNNING) {
            return $this->duration_seconds;
        }

        $runningSince = $this->resumed_at ?? $this->started_at;

        return (int) ($this->duration_seconds + max(0, $runningSince->diffInSeconds(now())));
    }
}
