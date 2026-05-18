<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends Model
{
    use HasFactory;

    public const STATUS_PLANNED = 'planned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [self::STATUS_PLANNED, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED];

    public const TIMER_RUNNING = 'running';
    public const TIMER_PAUSED = 'paused';
    public const TIMER_COMPLETED = 'completed';

    protected $fillable = [
        'client_id',
        'software_id',
        'sprint_no',
        'name',
        'status',
        'started_at',
        'ended_at',
        'duration_seconds',
        'started_by',
        'ended_by',
        'timer_status',
        'paused_at',
        'accumulated_paused_seconds',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
        'paused_at' => 'datetime',
        'accumulated_paused_seconds' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function software(): BelongsTo
    {
        return $this->belongsTo(Software::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function ender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SprintItem::class)->orderBy('position')->orderBy('id');
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'sprint_items')->withPivot('position')->withTimestamps()->orderByPivot('position');
    }


    public function tasks(): BelongsToMany
    {
        return $this->tickets()->where(function ($query) {
            $query->where('tickets.is_generated_task', true)->orWhere('tickets.type', Ticket::TYPE_TASK);
        });
    }

    public function bugs(): BelongsToMany
    {
        return $this->tickets()->where('tickets.type', Ticket::TYPE_BUG);
    }

    public function sourceFeatures(): BelongsToMany
    {
        return $this->tickets()->where('tickets.type', Ticket::TYPE_FEATURE);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(SprintActivity::class)->latest();
    }

    public function formattedStatus(): string
    {
        return str($this->status)->replace('_', ' ')->headline()->toString();
    }


    public function elapsedSeconds(): int
    {
        if (! $this->started_at) {
            return 0;
        }

        $end = $this->ended_at ?? now();
        $elapsed = $this->started_at->diffInSeconds($end) - ((int) $this->accumulated_paused_seconds);

        if ($this->isPausedTimer() && $this->paused_at) {
            $elapsed -= $this->paused_at->diffInSeconds(now());
        }

        return max(0, $elapsed);
    }

    public function isRunningTimer(): bool
    {
        return $this->timer_status === self::TIMER_RUNNING;
    }

    public function isPausedTimer(): bool
    {
        return $this->timer_status === self::TIMER_PAUSED;
    }

    public function formattedDuration(): string
    {
        if ($this->duration_seconds === null) {
            return '—';
        }

        $days = intdiv($this->duration_seconds, 86400);
        $hours = intdiv($this->duration_seconds % 86400, 3600);
        $minutes = intdiv($this->duration_seconds % 3600, 60);

        return collect([
            $days > 0 ? $days.'d' : null,
            $hours > 0 ? $hours.'h' : null,
            $minutes > 0 ? $minutes.'m' : null,
        ])->filter()->join(' ') ?: 'Less than 1m';
    }

    public function canEnd(): bool
    {
        $this->loadMissing('tickets');

        return $this->tickets->every(fn (Ticket $ticket) => $ticket->isTerminalForSprint());
    }

    public function activeItemsCount(): int
    {
        $this->loadMissing('tickets');

        return $this->tickets
            ->filter(fn (Ticket $ticket) => ! $ticket->isTerminalForSprint())
            ->count();
    }
}
