<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TimeTrackingService
{
    public function __construct(private readonly TicketActivityService $ticketActivityService)
    {
    }

    public function start(Ticket $ticket, User $user): TimeLog
    {
        $this->ensureKielUser($user);
        $this->ensureTicketIsTrackable($ticket);

        return DB::transaction(function () use ($ticket, $user) {
            $this->lockTicket($ticket);

            if ($this->activeTimerFor($ticket, $user)->lockForUpdate()->exists()) {
                throw new HttpException(422, 'You already have an active timer for this ticket.');
            }

            $timeLog = TimeLog::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'client_id' => $ticket->client_id,
                'software_id' => $ticket->software_id,
                'started_at' => now(),
                'status' => TimeLog::STATUS_RUNNING,
            ]);

            $this->ticketActivityService->log($ticket, 'timer started', $user->name.' started a timer.', $user);

            return $timeLog;
        });
    }

    public function pause(Ticket $ticket, User $user): TimeLog
    {
        $this->ensureKielUser($user);

        return DB::transaction(function () use ($ticket, $user) {
            $timeLog = $this->runningTimerFor($ticket, $user)->lockForUpdate()->first();

            if (! $timeLog) {
                throw new HttpException(422, 'There is no running timer to pause for this ticket.');
            }

            $this->pauseLog($timeLog);
            $this->ticketActivityService->log($ticket, 'timer paused', $user->name.' paused a timer.', $user);

            return $timeLog;
        });
    }

    public function resume(Ticket $ticket, User $user): TimeLog
    {
        $this->ensureKielUser($user);
        $this->ensureTicketIsTrackable($ticket);

        return DB::transaction(function () use ($ticket, $user) {
            $timeLog = $this->pausedTimerFor($ticket, $user)->lockForUpdate()->first();

            if (! $timeLog) {
                throw new HttpException(422, 'There is no paused timer to resume for this ticket.');
            }

            if ($this->runningTimerFor($ticket, $user)->lockForUpdate()->exists()) {
                throw new HttpException(422, 'You already have a running timer for this ticket.');
            }

            $timeLog->update([
                'resumed_at' => now(),
                'paused_at' => null,
                'status' => TimeLog::STATUS_RUNNING,
            ]);

            $this->ticketActivityService->log($ticket, 'timer resumed', $user->name.' resumed a timer.', $user);

            return $timeLog;
        });
    }

    public function stop(Ticket $ticket, User $user): TimeLog
    {
        $this->ensureKielUser($user);

        return DB::transaction(function () use ($ticket, $user) {
            $timeLog = $this->activeTimerFor($ticket, $user)->lockForUpdate()->first();

            if (! $timeLog) {
                throw new HttpException(422, 'There is no active timer to stop for this ticket.');
            }

            if ($timeLog->status === TimeLog::STATUS_RUNNING) {
                $this->syncRunningDuration($timeLog);
            }

            $timeLog->update([
                'paused_at' => null,
                'ended_at' => now(),
                'status' => TimeLog::STATUS_COMPLETED,
            ]);

            $this->ticketActivityService->log($ticket, 'timer stopped', $user->name.' stopped a timer.', $user, null, $timeLog->duration_seconds);

            return $timeLog;
        });
    }

    public function pauseRunningTimersForBlockedTicket(Ticket $ticket, ?User $actor = null): int
    {
        return DB::transaction(function () use ($ticket, $actor) {
            $paused = 0;

            $this->runningTimersForTicket($ticket)->lockForUpdate()->get()->each(function (TimeLog $timeLog) use ($ticket, $actor, &$paused) {
                $this->pauseLog($timeLog);
                $this->ticketActivityService->log($ticket, 'timer paused', 'Timer paused automatically because the ticket was blocked.', $actor ?? $timeLog->user);
                $paused++;
            });

            return $paused;
        });
    }

    public function cumulativeDurationForTicket(Ticket $ticket): int
    {
        return (int) TimeLog::query()
            ->where('ticket_id', $ticket->id)
            ->get()
            ->sum(fn (TimeLog $timeLog) => $timeLog->currentDurationSeconds());
    }

    public function reportQuery(array $filters = []): Builder
    {
        return TimeLog::query()
            ->when($filters['client_id'] ?? null, fn (Builder $query, $clientId) => $query->where('client_id', $clientId))
            ->when($filters['software_id'] ?? null, fn (Builder $query, $softwareId) => $query->where('software_id', $softwareId))
            ->when($filters['user_id'] ?? null, fn (Builder $query, $userId) => $query->where('user_id', $userId))
            ->when($filters['from'] ?? null, fn (Builder $query, $from) => $query->whereDate('started_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, $to) => $query->whereDate('started_at', '<=', $to));
    }

    public function runningTimerFor(Ticket $ticket, User $user): Builder
    {
        return TimeLog::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $user->id)
            ->where('status', TimeLog::STATUS_RUNNING);
    }

    public function activeTimerFor(Ticket $ticket, User $user): Builder
    {
        return TimeLog::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $user->id)
            ->active();
    }

    private function pausedTimerFor(Ticket $ticket, User $user): Builder
    {
        return TimeLog::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $user->id)
            ->where('status', TimeLog::STATUS_PAUSED);
    }

    private function runningTimersForTicket(Ticket $ticket): Builder
    {
        return TimeLog::query()
            ->where('ticket_id', $ticket->id)
            ->where('status', TimeLog::STATUS_RUNNING);
    }

    private function pauseLog(TimeLog $timeLog): void
    {
        $this->syncRunningDuration($timeLog);

        $timeLog->update([
            'paused_at' => now(),
            'status' => TimeLog::STATUS_PAUSED,
        ]);
    }

    private function syncRunningDuration(TimeLog $timeLog): void
    {
        $runningSince = $timeLog->resumed_at ?? $timeLog->started_at;
        $timeLog->duration_seconds += (int) max(0, $runningSince->diffInSeconds(now()));
        $timeLog->resumed_at = null;
        $timeLog->save();
    }

    private function lockTicket(Ticket $ticket): void
    {
        Ticket::query()->whereKey($ticket->id)->lockForUpdate()->first();
    }

    private function ensureKielUser(User $user): void
    {
        if (! $user->isKielUser()) {
            throw new HttpException(403, 'Only Kiel users can use timers.');
        }
    }

    private function ensureTicketIsTrackable(Ticket $ticket): void
    {
        if (in_array($ticket->status, [Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_FEATURE_BLOCKED], true)) {
            throw new HttpException(422, 'Blocked tickets cannot have running timers.');
        }
    }
}
