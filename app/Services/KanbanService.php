<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class KanbanService
{
    public const VIEW_ALL = 'all';
    public const VIEW_BUGS = 'bugs';
    public const VIEW_FEATURES = 'features';
    public const VIEW_SPRINT = 'sprint';

    public function __construct(private readonly TicketActivityService $ticketActivityService)
    {
    }

    public function columnsFor(string $view): array
    {
        return match ($view) {
            self::VIEW_BUGS => [
                Ticket::STATUS_BUG_PENDING => 'Pending',
                Ticket::STATUS_BUG_BLOCKED => 'Blocked',
                Ticket::STATUS_BUG_COMPLETED => 'Completed',
            ],
            self::VIEW_FEATURES => [
                Ticket::STATUS_FEATURE_APPROVED => 'Approved',
                Ticket::STATUS_RECOMMENDED => 'Recommended',
                Ticket::STATUS_NEXT_SPRINT => 'Next Sprint',
            ],
            self::VIEW_SPRINT => [
                Ticket::STATUS_BACKLOG => 'Backlog',
                Ticket::STATUS_IN_PROGRESS => 'In Progress',
                'blocked' => 'Blocked',
                'completed' => 'Completed',
            ],
            default => [
                Ticket::STATUS_BACKLOG => 'Backlog',
                Ticket::STATUS_RECOMMENDED => 'Recommended',
                Ticket::STATUS_NEXT_SPRINT => 'Next Sprint',
                Ticket::STATUS_IN_PROGRESS => 'In Progress',
                'blocked' => 'Blocked',
                'completed' => 'Completed',
                Ticket::STATUS_REJECTED => 'Rejected',
            ],
        };
    }

    public function queryFor(User $user, string $view, array $filters = [])
    {
        $query = Ticket::query()->notArchived()->with(['client', 'software', 'assignee', 'sprints']);

        if (! $user->isKielUser()) {
            $query->where('client_id', $user->client_id);
        }

        $query = match ($view) {
            self::VIEW_BUGS => $query->where('type', Ticket::TYPE_BUG)->whereIn('status', Ticket::BUG_STATUSES),
            self::VIEW_FEATURES => $query->where('type', Ticket::TYPE_FEATURE)->whereIn('status', Ticket::FEATURE_STATUSES),
            self::VIEW_SPRINT => $query->where(function ($q) {
                $q->where('is_generated_task', true)
                    ->orWhere('type', Ticket::TYPE_TASK)
                    ->orWhere('type', Ticket::TYPE_BUG);
            }),
            default => $query->where(function ($q) {
                $q->where('type', Ticket::TYPE_BUG)
                    ->orWhere('is_generated_task', true)
                    ->orWhere('type', Ticket::TYPE_TASK)
                    ->orWhereNull('type');
            }),
        };

        $workType = $filters['work_type'] ?? null;
        if ($workType === 'bugs') {
            $query->where('type', Ticket::TYPE_BUG);
        } elseif ($workType === 'tasks') {
            $query->where(function ($q) {
                $q->where('type', Ticket::TYPE_TASK)->orWhere('is_generated_task', true);
            });
        }

        $scope = $filters['scope'] ?? 'current_sprint';
        $sprintId = $filters['sprint_id'] ?? null;
        $currentSprintIds = $filters['current_sprint_ids'] ?? [];
        if ($scope === 'current_sprint') {
            $query->whereHas('sprints', fn ($s) => $s->whereIn('sprints.id', ! empty($currentSprintIds) ? $currentSprintIds : [0]));
        } elseif ($scope === 'unsprinted') {
            $query->whereDoesntHave('sprints');
        } elseif ($scope === 'completed_sprints') {
            $query->whereHas('sprints', fn ($s) => $s->where('status', 'completed'));
        } elseif ($scope === 'sprint' && $sprintId) {
            $query->whereHas('sprints', fn ($s) => $s->where('sprints.id', $sprintId));
        }

        return $query;
    }

    public function groupedTickets(User $user, string $view, array $filters = [])
    {
        return $this->queryFor($user, $view, $filters)
            ->orderByRaw('priority_order is null')
            ->orderBy('priority_order')
            ->latest('updated_at')
            ->get()
            ->groupBy(fn (Ticket $ticket) => $this->columnKeyForTicket($ticket, $view));
    }

    public function columnsForWorkType(string $workType): array
    {
        return $workType === 'bugs'
            ? [Ticket::STATUS_BUG_PENDING => 'Pending', 'blocked' => 'Blocked', 'completed' => 'Completed', Ticket::STATUS_REJECTED => 'Rejected']
            : [Ticket::STATUS_BACKLOG => 'Backlog', Ticket::STATUS_IN_PROGRESS => 'In Progress', 'blocked' => 'Blocked', 'completed' => 'Completed', Ticket::STATUS_REJECTED => 'Rejected', Ticket::STATUS_NEXT_SPRINT => 'Next Sprint'];
    }

    public function moveTicket(Ticket $ticket, User $user, string $column, int $position, string $view): array
    {
        $this->authorizeVisible($ticket, $user);

        $newStatus = $this->statusForColumn($ticket, $column, $view);
        $this->validateTransition($ticket, $user, $newStatus, $view);

        $oldStatus = $ticket->status;

        DB::transaction(function () use ($ticket, $user, $newStatus, $oldStatus, $position, $view) {
            $updates = [
                'status' => $newStatus,
                'priority_order' => $this->priorityForPosition($position),
            ];

            if (in_array($newStatus, [Ticket::STATUS_BUG_COMPLETED, Ticket::STATUS_FEATURE_COMPLETED, Ticket::STATUS_TASK_COMPLETED], true)) {
                $updates['completed_at'] = $ticket->completed_at ?? now();
                $updates['actual_completed_at'] = $ticket->actual_completed_at ?? now();
            } elseif (in_array($oldStatus, [Ticket::STATUS_BUG_COMPLETED, Ticket::STATUS_FEATURE_COMPLETED, Ticket::STATUS_TASK_COMPLETED], true)) {
                $updates['completed_at'] = null;
                $updates['actual_completed_at'] = null;
            }

            $ticket->update($updates);

            if ($oldStatus !== $ticket->status) {
                $this->ticketActivityService->log($ticket, 'kanban status changed', 'Ticket moved on the Kanban board.', $user, $oldStatus, $ticket->status);
            }

            $this->normalizeColumnOrder($user, $ticket->status, $view);
        });

        $ticket->refresh()->load(['client', 'software', 'assignee', 'sprints']);

        return $this->ticketPayload($ticket, $view);
    }

    public function reorder(User $user, array $ticketIds, string $column, string $view): array
    {
        $tickets = $this->queryFor($user, $view)->whereIn('id', $ticketIds)->get()->keyBy('id');

        if ($tickets->count() !== count(array_unique($ticketIds))) {
            throw ValidationException::withMessages(['tickets' => 'One or more tickets are not available on this board.']);
        }

        DB::transaction(function () use ($user, $ticketIds, $tickets, $column, $view) {
            foreach (array_values($ticketIds) as $index => $ticketId) {
                /** @var Ticket $ticket */
                $ticket = $tickets[(int) $ticketId];
                $expectedStatus = $this->statusForColumn($ticket, $column, $view);

                if ($ticket->status !== $expectedStatus) {
                    throw ValidationException::withMessages(['tickets' => 'Only tickets in the same Kanban column can be reordered.']);
                }

                $ticket->update(['priority_order' => $this->priorityForPosition($index)]);
            }

            $this->ticketActivityService->log($tickets[(int) $ticketIds[0]], 'kanban reordered', 'Kanban ticket order updated.', $user);
        });

        return ['message' => 'Kanban order saved.'];
    }

    public function ticketPayload(Ticket $ticket, string $view): array
    {
        return [
            'id' => $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'title' => $ticket->title,
            'type' => $ticket->type,
            'status' => $ticket->status,
            'column' => $this->columnKeyForTicket($ticket, $view),
            'status_label' => $ticket->formattedStatus(),
            'urgency' => $ticket->urgency,
            'urgency_label' => $ticket->formattedUrgency(),
            'assignee' => $ticket->assignee?->name ?? 'Unassigned',
            'due_date' => $ticket->due_date?->format('M j, Y') ?? 'Not set',
            'client' => $ticket->client?->name ?? 'No client',
            'software' => $ticket->software?->name ?? 'No software',
            'blocked' => $ticket->isBlocked(),
            'priority_order' => $ticket->priority_order,
            'show_url' => route('tickets.show', $ticket),
        ];
    }

    private function authorizeVisible(Ticket $ticket, User $user): void
    {
        if (! $user->can('view tickets') || (! $user->isKielUser() && $ticket->client_id !== $user->client_id)) {
            throw new HttpException(403, 'You cannot move this ticket.');
        }
    }

    private function validateTransition(Ticket $ticket, User $user, string $newStatus, string $view): void
    {
        if (! in_array($newStatus, $this->statusesForTicket($ticket, $view), true)) {
            throw ValidationException::withMessages(['status' => 'That ticket cannot move to this Kanban column.']);
        }

        if ($user->isKielUser()) {
            return;
        }

        $allowedClientRecommendation = $ticket->isFeature()
            && $ticket->status === Ticket::STATUS_FEATURE_APPROVED
            && $newStatus === Ticket::STATUS_RECOMMENDED;

        if (! $allowedClientRecommendation) {
            throw new HttpException(403, 'Clients can only recommend eligible feature tickets and cannot move internal workflow statuses.');
        }
    }

    private function statusesForTicket(Ticket $ticket, string $view): array
    {
        return match ($view) {
            self::VIEW_BUGS => $ticket->isBug() ? Ticket::BUG_STATUSES : [],
            self::VIEW_FEATURES => $ticket->isFeature() ? Ticket::FEATURE_STATUSES : [],
            self::VIEW_SPRINT => ($ticket->isBug() || $ticket->is_generated_task || $ticket->type === Ticket::TYPE_TASK || $ticket->type === null)
                ? [Ticket::STATUS_BACKLOG, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_TASK_BLOCKED, Ticket::STATUS_FEATURE_BLOCKED, Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_TASK_COMPLETED, Ticket::STATUS_FEATURE_COMPLETED, Ticket::STATUS_BUG_COMPLETED, Ticket::STATUS_REJECTED, Ticket::STATUS_NEXT_SPRINT]
                : [],
            default => $ticket->isBug()
                ? [Ticket::STATUS_BUG_PENDING, Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_BUG_COMPLETED, Ticket::STATUS_REJECTED]
                : ($ticket->isFeature() ? [Ticket::STATUS_FEATURE_APPROVED, Ticket::STATUS_RECOMMENDED, Ticket::STATUS_NEXT_SPRINT, Ticket::STATUS_REJECTED] : [Ticket::STATUS_BACKLOG, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_TASK_BLOCKED, Ticket::STATUS_FEATURE_BLOCKED, Ticket::STATUS_TASK_COMPLETED, Ticket::STATUS_FEATURE_COMPLETED, Ticket::STATUS_REJECTED, Ticket::STATUS_NEXT_SPRINT]),
        };
    }

    private function statusForColumn(Ticket $ticket, string $column, string $view): string
    {
        return match ($column) {
            Ticket::STATUS_BACKLOG => $ticket->isBug() ? Ticket::STATUS_BUG_PENDING : ($ticket->isFeature() ? Ticket::STATUS_FEATURE_APPROVED : Ticket::STATUS_BACKLOG),
            'blocked' => $ticket->isBug() ? Ticket::STATUS_BUG_BLOCKED : ($ticket->isTask() ? Ticket::STATUS_TASK_BLOCKED : Ticket::STATUS_FEATURE_BLOCKED),
            'completed' => $ticket->isBug() ? Ticket::STATUS_BUG_COMPLETED : ($ticket->isTask() ? Ticket::STATUS_TASK_COMPLETED : Ticket::STATUS_FEATURE_COMPLETED),
            default => $column,
        };
    }

    private function columnKeyForTicket(Ticket $ticket, string $view): string
    {
        if (in_array($view, [self::VIEW_ALL, self::VIEW_SPRINT], true)) {
            return match ($ticket->status) {
                Ticket::STATUS_BUG_PENDING, Ticket::STATUS_FEATURE_APPROVED, Ticket::STATUS_BACKLOG => Ticket::STATUS_BACKLOG,
                Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_FEATURE_BLOCKED, Ticket::STATUS_TASK_BLOCKED => 'blocked',
                Ticket::STATUS_BUG_COMPLETED, Ticket::STATUS_FEATURE_COMPLETED, Ticket::STATUS_TASK_COMPLETED => 'completed',
                default => $ticket->status,
            };
        }

        return $ticket->status;
    }

    private function priorityForPosition(int $position): int
    {
        return ($position + 1) * 1000;
    }

    private function normalizeColumnOrder(User $user, string $status, string $view): void
    {
        $this->queryFor($user, $view)
            ->where('status', $status)
            ->orderByRaw('priority_order is null')
            ->orderBy('priority_order')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->values()
            ->each(fn (Ticket $ticket, int $index) => $ticket->updateQuietly(['priority_order' => $this->priorityForPosition($index)]));
    }
}
