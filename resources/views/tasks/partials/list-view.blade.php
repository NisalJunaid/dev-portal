@php
    $query = request()->query();
    $groupedTickets = $tickets->getCollection()->groupBy(fn ($ticket) => $ticket->listSectionKey());
@endphp
<section class="asana-panel h-full min-h-0 overflow-hidden p-0" data-list-board>
<div class="tasks-scroll-area scrollbar-fade-mask flex-1 min-h-0 overflow-auto sleek-scrollbar p-3 space-y-3">
@foreach (($listSections ?? \App\Models\Ticket::listSections()) as $sectionKey => $sectionLabel)
@php($sectionTickets = $groupedTickets->get($sectionKey, collect()))
<div class="rounded-xl border border-slate-200 bg-white" data-list-section data-section="{{ $sectionKey }}">
<button type="button" class="w-full flex items-center justify-between px-4 py-2 text-left bg-slate-50" @click="$el.nextElementSibling.classList.toggle('hidden')">
<span class="font-bold text-slate-800">{{ $sectionLabel }}</span>
<span class="text-xs font-black text-slate-500" data-list-section-count>{{ $sectionTickets->count() }}</span>
</button>
<div data-list-dropzone data-section="{{ $sectionKey }}" class="overflow-visible">
<table class="min-w-[1600px] table-fixed divide-y divide-slate-200 text-sm w-full"><thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500"><tr>
<th class="w-8 px-1 py-2"></th><th class="whitespace-nowrap py-2 pl-1 pr-3">Ticket #</th><th class="px-4 py-2">Title</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Assignee</th>
</tr></thead>
<tbody data-list-section-body data-section="{{ $sectionKey }}">
@if($sectionTickets->isEmpty())<tr data-list-empty-row><td colspan="5" class="px-4 py-4 text-xs text-slate-400">Drop tasks here</td></tr>@endif
@foreach($sectionTickets as $ticket)
<tr data-list-task-row data-ticket-id="{{ $ticket->id }}" data-ticket-type="{{ $ticket->type }}" data-current-section="{{ $ticket->listSectionKey() }}" data-current-status="{{ $ticket->status }}" data-move-url="{{ route('tickets.inline-update', $ticket) }}" class="border-t">
<td class="w-8 py-2 pl-2 pr-1 align-middle"><button type="button" data-list-drag-handle title="Drag to change status" class="flex h-6 w-5 cursor-grab items-center justify-center rounded text-slate-300 transition hover:bg-slate-100 hover:text-slate-600 active:cursor-grabbing">⋮⋮</button></td>
<td class="whitespace-nowrap py-2 pl-1 pr-3 font-bold"><button type="button" data-list-ticket-no data-ticket-drawer-url="{{ route('tickets.drawer', $ticket) }}" class="font-black text-slate-950 transition hover:text-indigo-700">{{ $ticket->ticket_no }}</button></td>
<td class="px-4 py-2" data-list-title><button type="button" data-ticket-drawer-url="{{ route('tickets.drawer', $ticket) }}" class="text-left font-bold text-slate-800 hover:text-indigo-700">{{ $ticket->title }}</button></td>
<td class="px-4 py-2 relative overflow-visible"><button type="button" data-list-status-trigger class="badge badge-status cursor-pointer" data-list-status-label>{{ $ticket->formattedStatus() }}</button></td>
<td class="px-4 py-2" data-list-assignee>{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
</tr>
@endforeach
</tbody></table>
</div></div>
@endforeach
</div>
<div class="shrink-0 border-t border-slate-200 px-6 py-4">{{ $tickets->links() }}</div>
</section>
