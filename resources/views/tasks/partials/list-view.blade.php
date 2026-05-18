@php
    $query = request()->query();
    $sortUrl = function (string $column) use ($query, $sort, $direction) {
        return route('tasks.index', array_merge($query, ['sort' => $column,'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc','page' => null,]));
    };
    $sortIndicator = fn (string $column) => $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '↕';
    $groupedTickets = $tickets->getCollection()->groupBy(fn ($ticket) => $ticket->listSectionKey());
@endphp
<section class="asana-panel h-full min-h-0 overflow-hidden p-0" x-data="ticketList({ canInlineEdit: @js($isKielUser) })" data-list-board>
<div class="tasks-scroll-area scrollbar-fade-mask flex-1 min-h-0 overflow-auto sleek-scrollbar p-3 space-y-3">
@foreach (($listSections ?? \App\Models\Ticket::listSections()) as $sectionKey => $sectionLabel)
@php($sectionTickets = $groupedTickets->get($sectionKey, collect()))
<div class="rounded-xl border border-slate-200 bg-white" data-list-section data-section="{{ $sectionKey }}">
<button type="button" class="w-full flex items-center justify-between px-4 py-2 text-left bg-slate-50" @click="$el.nextElementSibling.classList.toggle('hidden')">
<span class="font-bold text-slate-800">{{ $sectionLabel }}</span>
<span class="text-xs font-black text-slate-500" data-list-section-count>{{ $sectionTickets->count() }}</span>
</button>
<div data-list-dropzone data-section="{{ $sectionKey }}" class="overflow-auto">
<table class="min-w-[1600px] table-fixed divide-y divide-slate-200 text-sm w-full">
<thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500"><tr>
<th class="px-2 py-2"></th><th class="px-4 py-2">Ticket #</th><th class="px-4 py-2">Title</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Assignee</th>
</tr></thead>
<tbody data-list-section-body data-section="{{ $sectionKey }}">
@foreach($sectionTickets as $ticket)
<tr data-list-task-row data-ticket-id="{{ $ticket->id }}" data-ticket-type="{{ $ticket->type }}" data-current-section="{{ $ticket->listSectionKey() }}" data-current-status="{{ $ticket->status }}" data-move-url="{{ route('tickets.inline-update', $ticket) }}" class="border-t">
<td class="px-2 py-2"><button type="button" data-list-drag-handle title="Drag to change status" class="cursor-grab text-slate-400">⋮⋮</button></td>
<td class="px-4 py-2 font-bold"><a data-ticket-drawer-url="{{ route('tickets.drawer', $ticket) }}" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->ticket_no }}</a></td>
<td class="px-4 py-2">{{ $ticket->title }}</td>
<td class="px-4 py-2"><span class="badge badge-status" data-list-status-label>{{ $ticket->formattedStatus() }}</span></td>
<td class="px-4 py-2">{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
</tr>
@endforeach
</tbody></table>
<div class="px-4 py-3 text-xs text-slate-400" data-list-empty @if($sectionTickets->count()) hidden @endif>Drop tasks here</div>
</div></div>
@endforeach
</div>
<div class="shrink-0 border-t border-slate-200 px-6 py-4">{{ $tickets->links() }}</div>
</section>
