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
    @class([
        'kanban-card rounded-2xl border bg-white p-4 shadow-sm hover:-translate-y-0.5 hover:border-indigo-100 hover:shadow-soft',
        'border-slate-200' => ! $isOverdue && ! $ticket->isBlocked() && $ticket->urgency !== 'critical',
        'kanban-card-overdue' => $isOverdue,
        'kanban-card-blocked' => $ticket->isBlocked(),
        'kanban-card-critical' => $ticket->urgency === 'critical',
    ])
>
    <div data-kanban-drag-handle class="-mx-1 mb-2 cursor-grab rounded-lg px-1 py-1 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Drag</div>
    <div class="flex items-start justify-between gap-3">
        <button type="button" data-open-ticket data-ticket-drawer-url="{{ route('tickets.drawer', $ticket) }}" class="text-left">
            <span class="font-black text-indigo-700">{{ $ticket->ticket_no }}</span>
            <h4 class="mt-2 text-sm font-black leading-5 text-slate-950">{{ $ticket->title }}</h4>
        </button>
        <span @class(['badge', 'badge-urgency-critical' => $ticket->urgency === 'critical', 'badge-urgency-high' => $ticket->urgency === 'high', 'badge-urgency-medium' => $ticket->urgency === 'medium', 'badge-urgency-low' => $ticket->urgency === 'low'])>{{ $ticket->formattedUrgency() }}</span>
    </div>
</article>
