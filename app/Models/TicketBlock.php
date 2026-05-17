<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'blocked_by',
        'reason',
        'blocked_at',
        'unblocked_at',
        'unblocked_by',
        'unblock_note',
        'duration_seconds',
    ];

    protected $casts = [
        'blocked_at' => 'datetime',
        'unblocked_at' => 'datetime',
        'duration_seconds' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function unblocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unblocked_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('unblocked_at');
    }

    public function currentDurationSeconds(): int
    {
        if ($this->duration_seconds !== null) {
            return $this->duration_seconds;
        }

        return (int) max(0, $this->blocked_at->diffInSeconds(now()));
    }
}
