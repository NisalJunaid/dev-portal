<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TimelineService
{
    public function __construct(private readonly TicketActivityService $ticketActivityService)
    {
    }

    public function ganttPayload(User $user, array $filters = []): array
    {
        $tickets = $this->visibleQuery($user, $filters)
            ->whereNotNull('start_date')
            ->whereNotNull('due_date')
            ->orderBy('start_date')
            ->orderBy('due_date')
            ->orderBy('timeline_position')
            ->orderBy('priority_order')
            ->orderBy('id')
            ->get();

        return [
            'library' => 'frappe-gantt',
            'can_edit' => $this->canEdit($user),
            'tasks' => $tickets->map(fn (Ticket $ticket) => $this->taskPayload($ticket))->values(),
        ];
    }

    public function updateDates(Ticket $ticket, User $user, string $startDate, string $dueDate): array
    {
        $this->authorizeVisible($ticket, $user);
        $this->authorizeEditable($user);

        $start = Carbon::parse($startDate)->startOfDay();
        $due = Carbon::parse($dueDate)->startOfDay();

        if ($start->gt($due)) {
            throw ValidationException::withMessages(['start_date' => 'The start date must be on or before the due date.']);
        }

        $oldValue = [
            'start_date' => $ticket->start_date?->toDateString(),
            'due_date' => $ticket->due_date?->toDateString(),
        ];

        $ticket->forceFill([
            'start_date' => $start->toDateString(),
            'due_date' => $due->toDateString(),
        ])->save();

        $ticket->refresh()->load(['client', 'software', 'assignee', 'dependency', 'activeBlock']);

        $this->ticketActivityService->log(
            $ticket,
            'timeline dates changed',
            'Timeline dates updated.',
            $user,
            $oldValue,
            ['start_date' => $start->toDateString(), 'due_date' => $due->toDateString()]
        );

        return $this->taskPayload($ticket);
    }

    public function updateDependency(Ticket $ticket, User $user, ?int $dependencyId): array
    {
        $this->authorizeVisible($ticket, $user);
        $this->authorizeEditable($user);

        if ($dependencyId === $ticket->id) {
            throw ValidationException::withMessages(['depends_on_ticket_id' => 'A ticket cannot depend on itself.']);
        }

        $dependency = null;
        if ($dependencyId !== null) {
            $dependency = Ticket::query()->find($dependencyId);

            if (! $dependency) {
                throw ValidationException::withMessages(['depends_on_ticket_id' => 'The selected dependency does not exist.']);
            }

            $this->authorizeVisible($dependency, $user);

            if ($this->wouldCreateCircularDependency($ticket, $dependency)) {
                throw ValidationException::withMessages(['depends_on_ticket_id' => 'This dependency would create a circular chain.']);
            }
        }

        $oldValue = $ticket->depends_on_ticket_id;

        $ticket->forceFill(['depends_on_ticket_id' => $dependency?->id])->save();
        $ticket->refresh()->load(['client', 'software', 'assignee', 'dependency', 'activeBlock']);

        $this->ticketActivityService->log(
            $ticket,
            'timeline dependency changed',
            $dependency ? 'Timeline dependency updated.' : 'Timeline dependency removed.',
            $user,
            $oldValue,
            $dependency?->id
        );

        return $this->taskPayload($ticket);
    }

    public function visibleQuery(User $user, array $filters = []): Builder
    {
        return Ticket::query()
            ->with(['client', 'software', 'assignee', 'dependency', 'activeBlock'])
            ->when(! $user->isKielUser(), fn (Builder $query) => $query->where('client_id', $user->client_id))
            ->when($filters['client_id'] ?? null, fn (Builder $query, $clientId) => $query->where('client_id', $clientId))
            ->when($filters['software_id'] ?? null, fn (Builder $query, $softwareId) => $query->where('software_id', $softwareId))
            ->when($filters['sprint_id'] ?? null, fn (Builder $query, $sprintId) => $query->whereHas('sprints', fn (Builder $sprintQuery) => $sprintQuery->where('sprints.id', $sprintId)))
            ->when(array_key_exists('assigned_to', $filters) && $filters['assigned_to'] !== null && $filters['assigned_to'] !== '', function (Builder $query) use ($filters) {
                $filters['assigned_to'] === 'unassigned'
                    ? $query->whereNull('assigned_to')
                    : $query->where('assigned_to', $filters['assigned_to']);
            })
            ->when($filters['urgency'] ?? null, fn (Builder $query, $urgency) => $query->where('urgency', $urgency))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status));
    }

    public function taskPayload(Ticket $ticket): array
    {
        $ticket->loadMissing(['client', 'software', 'assignee', 'dependency', 'activeBlock']);

        $isOverdue = $ticket->due_date !== null
            && $ticket->due_date->lt(today())
            && ! in_array($ticket->status, [Ticket::STATUS_BUG_COMPLETED, Ticket::STATUS_FEATURE_COMPLETED], true);

        $metadata = collect([
            $ticket->assignee?->name ?? 'Unassigned',
            $ticket->formattedStatus(),
            $ticket->formattedUrgency(),
            ($ticket->isBlocked() || $ticket->activeBlock) ? 'Blocked' : null,
            $isOverdue ? 'Overdue' : null,
        ])->filter()->join(' · ');

        return [
            'id' => (string) $ticket->id,
            'name' => trim($ticket->ticket_no.' · '.$ticket->title.' — '.$metadata),
            'start' => $ticket->start_date?->toDateString(),
            'end' => $ticket->due_date?->toDateString(),
            'progress' => in_array($ticket->status, [Ticket::STATUS_BUG_COMPLETED, Ticket::STATUS_FEATURE_COMPLETED], true) ? 100 : 0,
            'dependencies' => $ticket->depends_on_ticket_id ? (string) $ticket->depends_on_ticket_id : '',
            'custom_class' => collect([
                'timeline-task',
                'timeline-urgency-'.$ticket->urgency,
                $ticket->isBlocked() || $ticket->activeBlock ? 'timeline-blocked' : null,
                $isOverdue ? 'timeline-overdue' : null,
            ])->filter()->join(' '),
            'ticket' => [
                'id' => $ticket->id,
                'ticket_no' => $ticket->ticket_no,
                'title' => $ticket->title,
                'status' => $ticket->status,
                'status_label' => $ticket->formattedStatus(),
                'urgency' => $ticket->urgency,
                'urgency_label' => $ticket->formattedUrgency(),
                'assignee' => $ticket->assignee?->name ?? 'Unassigned',
                'client' => $ticket->client?->name ?? 'No client',
                'software' => $ticket->software?->name ?? 'No software',
                'start_date' => $ticket->start_date?->toDateString(),
                'due_date' => $ticket->due_date?->toDateString(),
                'blocked' => $ticket->isBlocked() || $ticket->activeBlock !== null,
                'overdue' => $isOverdue,
                'dependency_id' => $ticket->depends_on_ticket_id,
                'dependency_label' => $ticket->dependency?->ticket_no,
                'show_url' => route('tickets.show', $ticket),
            ],
        ];
    }

    public function canEdit(User $user): bool
    {
        return $user->isKielUser();
    }

    private function authorizeVisible(Ticket $ticket, User $user): void
    {
        if (! $user->can('view timeline') || (! $user->isKielUser() && $ticket->client_id !== $user->client_id)) {
            throw new HttpResponseException(response()->json(['message' => 'You cannot access this timeline task.'], 403));
        }
    }

    private function authorizeEditable(User $user): void
    {
        if (! $this->canEdit($user)) {
            throw new HttpResponseException(response()->json(['message' => 'You cannot edit timeline tasks.'], 403));
        }
    }

    private function wouldCreateCircularDependency(Ticket $ticket, Ticket $dependency): bool
    {
        $seen = [];
        $current = $dependency;

        while ($current) {
            if ($current->id === $ticket->id) {
                return true;
            }

            if (in_array($current->id, $seen, true)) {
                return true;
            }

            $seen[] = $current->id;
            $nextId = $current->depends_on_ticket_id;
            $current = $nextId ? Ticket::query()->find($nextId) : null;
        }

        return false;
    }
}
