@php
    $columnKey = $activeView === \App\Services\KanbanService::VIEW_ALL
        ? match ($ticket->status) {
            \App\Models\Ticket::STATUS_BUG_PENDING, \App\Models\Ticket::STATUS_FEATURE_APPROVED, \App\Models\Ticket::STATUS_BACKLOG => \App\Models\Ticket::STATUS_BACKLOG,
            \App\Models\Ticket::STATUS_BUG_BLOCKED, \App\Models\Ticket::STATUS_FEATURE_BLOCKED => 'blocked',
            \App\Models\Ticket::STATUS_BUG_COMPLETED, \App\Models\Ticket::STATUS_FEATURE_COMPLETED => 'completed',
            default => $ticket->status,
        }
        : $ticket->status;
    $isOverdue = $ticket->due_date !== null && $ticket->due_date->isPast() && ! in_array($ticket->status, [\App\Models\Ticket::STATUS_BUG_COMPLETED, \App\Models\Ticket::STATUS_FEATURE_COMPLETED], true);
@endphp

<article
    data-kanban-card
    data-ticket-id="{{ $ticket->id }}"
    data-current-status="{{ $ticket->status }}"
    data-current-column="{{ $columnKey }}"
    data-move-url="{{ route('kanban.tickets.move', $ticket) }}"
    data-show-url="{{ route('tickets.show', $ticket) }}"
    data-ticket-no="{{ $ticket->ticket_no }}"
    data-title="{{ $ticket->title }}"
    data-urgency="{{ $ticket->formattedUrgency() }}"
    data-assignee="{{ $ticket->assignee?->name ?? 'Unassigned' }}"
    data-due="{{ $ticket->due_date?->format('M j, Y') ?? 'Not set' }}"
    data-status="{{ $ticket->formattedStatus() }}"
    data-status-label="{{ $ticket->formattedStatus() }}"
    data-client="{{ $ticket->client?->name ?? 'No client' }}"
    data-software="{{ $ticket->software?->name ?? 'No software' }}"
    @class([
        'kanban-card rounded-2xl border bg-white p-4 shadow-sm hover:-translate-y-0.5 hover:border-indigo-100 hover:shadow-soft',
        'border-slate-200' => ! $isOverdue && ! $ticket->isBlocked() && $ticket->urgency !== 'critical',
        'kanban-card-overdue' => $isOverdue,
        'kanban-card-blocked' => $ticket->isBlocked(),
        'kanban-card-critical' => $ticket->urgency === 'critical',
    ])
>
    <button type="button" data-open-ticket data-ticket-drawer-url="{{ route('tickets.drawer', $ticket) }}" class="block w-full text-left">
        <div class="flex items-start justify-between gap-3">
            <span class="font-black text-indigo-700">{{ $ticket->ticket_no }}</span>
            <span @class(['badge', 'badge-urgency-critical' => $ticket->urgency === 'critical', 'badge-urgency-high' => $ticket->urgency === 'high', 'badge-urgency-medium' => $ticket->urgency === 'medium', 'badge-urgency-low' => $ticket->urgency === 'low'])>{{ $ticket->formattedUrgency() }}</span>
        </div>
        <h4 class="mt-3 text-sm font-black leading-5 text-slate-950">{{ $ticket->title }}</h4>
        <div class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-xs font-semibold text-slate-500">
            <p>Assignee: <span class="text-slate-700">{{ $ticket->assignee?->name ?? 'Unassigned' }}</span></p>
            <p>Due: <span @class(['text-slate-700', 'font-black text-rose-700' => $isOverdue])>{{ $ticket->due_date?->format('M j, Y') ?? 'Not set' }}</span></p>
            <p>Client: <span class="text-slate-700">{{ $ticket->client?->name ?? 'No client' }}</span></p>
            <p>Software: <span class="text-slate-700">{{ $ticket->software?->name ?? 'No software' }}</span></p>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <span data-card-status class="badge badge-status">{{ $ticket->formattedStatus() }}</span>
            <span data-blocked-badge @class(['badge badge-blocked', 'hidden' => ! $ticket->isBlocked()])>Blocked</span>
            <span @class(['badge badge-overdue', 'hidden' => ! $isOverdue])>Overdue</span>
        </div>
    </button>
</article>
