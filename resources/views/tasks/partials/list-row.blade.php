@php
    $isChild = $isChild ?? false;
    $parent = $parent ?? null;
@endphp
<tr
    data-list-task-row
    data-ticket-id="{{ $ticket->id }}"
    data-parent-ticket-id="{{ $ticket->parent_ticket_id ?? '' }}"
    data-is-child="{{ $isChild ? 'true' : 'false' }}"
    data-ticket-type="{{ $ticket->type }}"
    data-current-section="{{ $ticket->listSectionKey($workType ?? 'tasks') }}"
    data-current-status="{{ $ticket->status }}"
    data-move-url="{{ route('tickets.inline-update', $ticket) }}"
    @class(['border-t', 'task-child-row' => $isChild])
>
    <td class="w-8 py-2 pl-2 pr-1 align-middle">
        <button type="button" data-list-drag-handle title="Drag to change status" class="flex h-6 w-5 cursor-grab items-center justify-center rounded text-slate-300 transition hover:bg-slate-100 hover:text-slate-600 active:cursor-grabbing">⋮⋮</button>
    </td>
    <td class="whitespace-nowrap py-2 pl-1 pr-3 font-bold">
        <div class="flex items-center gap-1.5">
            @if($isChild)
                <span class="task-child-elbow" aria-hidden="true"></span>
            @endif
            <button type="button" data-list-ticket-no data-ticket-drawer-url="{{ route('tickets.drawer', $ticket) }}" class="font-black text-slate-950 transition hover:text-indigo-700">{{ $ticket->ticket_no }}</button>
        </div>
    </td>
    <td class="px-4 py-2" data-list-title>
        <button type="button" data-ticket-drawer-url="{{ route('tickets.drawer', $ticket) }}" class="text-left font-bold text-slate-800 hover:text-indigo-700">{{ $ticket->title }}</button>
        @if($isChild && $parent === null)
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Subtask of {{ $ticket->parent?->ticket_no ?? 'Hidden parent' }}</p>
        @endif
    </td>
    <td class="relative overflow-visible px-4 py-2">
        <button type="button" data-list-status-trigger data-field-menu-trigger data-field-type="status" data-ticket-id="{{ $ticket->id }}" data-endpoint="{{ route('tickets.inline-update', $ticket) }}" data-current-value="{{ $ticket->status }}" data-options='@json($statusOptionsForThisTicket)' class="badge badge-status cursor-pointer" @click.stop="openFieldMenuFromDataset($event)">
            <span data-list-status-label>{{ $ticket->formattedStatus() }}</span>
        </button>
    </td>
    <td class="px-4 py-2" data-list-assignee>
        <button type="button" data-list-assignee-trigger data-field-menu-trigger data-field-type="assignee" data-ticket-id="{{ $ticket->id }}" data-endpoint="{{ route('tickets.inline-update', $ticket) }}" data-current-value="{{ $ticket->assigned_to ? (string) $ticket->assigned_to : '' }}" data-options='@json($assigneeOptions)' class="inline-flex cursor-pointer items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 hover:bg-slate-200" @click.stop="openFieldMenuFromDataset($event)">
            <span data-list-assignee-label>{{ $ticket->assignee?->name ?? 'Unassigned' }}</span>
        </button>
    </td>
</tr>
