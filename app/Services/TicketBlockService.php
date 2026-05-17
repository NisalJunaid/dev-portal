<?php

namespace App\Services;

use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\TicketBlock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TicketBlockService
{
    public function __construct(
        private readonly TicketActivityService $ticketActivityService,
        private readonly TimeTrackingService $timeTrackingService,
    ) {
    }

    public function block(Ticket $ticket, User $user, string $reason): TicketBlock
    {
        $this->ensureKielUser($user);
        $reason = trim($reason);

        if ($reason === '') {
            throw new HttpException(422, 'A block reason is required.');
        }

        return DB::transaction(function () use ($ticket, $user, $reason) {
            $lockedTicket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedTicket->type, Ticket::TYPES, true)) {
                throw new HttpException(422, 'Only classified tickets can be blocked.');
            }

            if ($lockedTicket->isBlocked() || $this->activeBlockFor($lockedTicket)->lockForUpdate()->exists()) {
                throw new HttpException(422, 'This ticket is already blocked.');
            }

            $oldStatus = $lockedTicket->status;
            $newStatus = $lockedTicket->isBug() ? Ticket::STATUS_BUG_BLOCKED : Ticket::STATUS_FEATURE_BLOCKED;

            $block = TicketBlock::create([
                'ticket_id' => $lockedTicket->id,
                'blocked_by' => $user->id,
                'reason' => $reason,
                'blocked_at' => now(),
            ]);

            $lockedTicket->update(['status' => $newStatus]);
            $ticket->setRawAttributes($lockedTicket->getAttributes(), true);

            $this->timeTrackingService->pauseRunningTimersForBlockedTicket($lockedTicket, $user);
            $this->ticketActivityService->log($lockedTicket, 'blocked', 'Ticket blocked: '.$reason, $user, $oldStatus, $newStatus);
            $this->ticketActivityService->log($lockedTicket, 'status changed', 'Ticket status changed because it was blocked.', $user, $oldStatus, $newStatus);

            return $block->load(['blocker', 'unblocker']);
        });
    }

    public function unblock(Ticket $ticket, User $user, string $unblockNote): TicketBlock
    {
        $this->ensureKielUser($user);
        $unblockNote = trim($unblockNote);

        if ($unblockNote === '') {
            throw new HttpException(422, 'An unblock note is required.');
        }

        return DB::transaction(function () use ($ticket, $user, $unblockNote) {
            $lockedTicket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $block = $this->activeBlockFor($lockedTicket)->lockForUpdate()->first();

            if (! $lockedTicket->isBlocked() || ! $block) {
                throw new HttpException(422, 'This ticket is not currently blocked.');
            }

            $oldStatus = $lockedTicket->status;
            $newStatus = $this->unblockedStatusFor($lockedTicket);
            $unblockedAt = now();

            $block->update([
                'unblocked_at' => $unblockedAt,
                'unblocked_by' => $user->id,
                'unblock_note' => $unblockNote,
                'duration_seconds' => (int) max(0, $block->blocked_at->diffInSeconds($unblockedAt)),
            ]);

            $lockedTicket->update(['status' => $newStatus]);
            $ticket->setRawAttributes($lockedTicket->getAttributes(), true);

            $this->ticketActivityService->log($lockedTicket, 'unblocked', 'Ticket unblocked: '.$unblockNote, $user, $oldStatus, $newStatus);
            $this->ticketActivityService->log($lockedTicket, 'status changed', 'Ticket status changed because it was unblocked.', $user, $oldStatus, $newStatus);

            return $block->load(['blocker', 'unblocker']);
        });
    }

    public function totalBlockedDurationForTicket(Ticket $ticket): int
    {
        return (int) TicketBlock::query()
            ->where('ticket_id', $ticket->id)
            ->get()
            ->sum(fn (TicketBlock $block) => $block->currentDurationSeconds());
    }

    public function activeBlockFor(Ticket $ticket)
    {
        return TicketBlock::query()
            ->where('ticket_id', $ticket->id)
            ->whereNull('unblocked_at')
            ->latest('blocked_at');
    }

    private function unblockedStatusFor(Ticket $ticket): string
    {
        if ($ticket->isBug()) {
            return Ticket::STATUS_BUG_PENDING;
        }

        if ($ticket->sprints()->where('sprints.status', Sprint::STATUS_IN_PROGRESS)->exists()) {
            return Ticket::STATUS_IN_PROGRESS;
        }

        return Ticket::STATUS_FEATURE_APPROVED;
    }

    private function ensureKielUser(User $user): void
    {
        if (! $user->isKielUser()) {
            throw new HttpException(403, 'Only Kiel users can block tickets.');
        }
    }
}
