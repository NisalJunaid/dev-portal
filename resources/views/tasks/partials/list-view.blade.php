@php
    $query = request()->query();
    $groupedTickets = $tickets->getCollection()->groupBy(fn ($ticket) => $ticket->listSectionKey($workType ?? 'tasks'));
    $assigneeOptions = collect([['value' => '', 'label' => 'Unassigned']])
        ->merge(($teamMembers ?? collect())->map(fn ($member) => ['value' => (string) $member->id, 'label' => $member->name]))
        ->values();
@endphp

<section class="asana-panel h-full min-h-0 overflow-hidden p-0" data-list-board data-work-type="{{ $workType ?? 'tasks' }}">
    <div class="tasks-scroll-area scrollbar-fade-mask flex-1 min-h-0 space-y-3 overflow-auto p-3 sleek-scrollbar">
        @foreach (($listSections ?? \App\Models\Ticket::listSections($workType ?? 'tasks')) as $sectionKey => $sectionLabel)
            @php
                $sectionTickets = $groupedTickets->get($sectionKey, collect());
            @endphp

            <div class="rounded-xl border border-slate-200 bg-white" data-list-section data-section="{{ $sectionKey }}">
                <button
                    type="button"
                    class="flex w-full items-center justify-between bg-slate-50 px-4 py-2 text-left"
                    @click="$el.nextElementSibling.classList.toggle('hidden')"
                >
                    <span class="font-bold text-slate-800">{{ $sectionLabel }}</span>
                    <span class="text-xs font-black text-slate-500" data-list-section-count>{{ $sectionTickets->count() }}</span>
                </button>

                <div data-list-dropzone data-section="{{ $sectionKey }}" class="overflow-visible">
                    <table class="min-w-[1600px] w-full table-fixed divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500">
                            <tr>
                                <th class="w-8 px-1 py-2"></th>
                                <th class="whitespace-nowrap py-2 pl-1 pr-3">Ticket #</th>
                                <th class="px-4 py-2">Title</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Assignee</th>
                            </tr>
                        </thead>
                        <tbody data-list-section-body data-section="{{ $sectionKey }}">
                            @if ($sectionTickets->isEmpty())
                                <tr data-list-empty-row>
                                    <td colspan="5" class="px-4 py-4 text-xs text-slate-400">Drop tasks here</td>
                                </tr>
                            @endif

                            @foreach ($sectionTickets as $ticket)
                                @php
                                    $statusOptionsForThisTicket = $ticket->isBug()
                                        ? [
                                            ['value' => 'bug_pending', 'label' => 'Pending'],
                                            ['value' => 'bug_blocked', 'label' => 'Blocked'],
                                            ['value' => 'bug_completed', 'label' => 'Completed'],
                                            ['value' => 'rejected', 'label' => 'Rejected'],
                                        ]
                                        : [
                                            ['value' => 'backlog', 'label' => 'Backlog'],
                                            ['value' => 'in_progress', 'label' => 'In Progress'],
                                            ['value' => 'task_blocked', 'label' => 'Blocked'],
                                            ['value' => 'task_completed', 'label' => 'Completed'],
                                            ['value' => 'rejected', 'label' => 'Rejected'],
                                            ['value' => 'next_sprint', 'label' => 'Move to Next Sprint'],
                                        ];
                                @endphp

                                <tr
                                    data-list-task-row
                                    data-ticket-id="{{ $ticket->id }}"
                                    data-ticket-type="{{ $ticket->type }}"
                                    data-current-section="{{ $ticket->listSectionKey($workType ?? 'tasks') }}"
                                    data-current-status="{{ $ticket->status }}"
                                    data-move-url="{{ route('tickets.inline-update', $ticket) }}"
                                    class="border-t"
                                >
                                    <td class="w-8 py-2 pl-2 pr-1 align-middle">
                                        <button
                                            type="button"
                                            data-list-drag-handle
                                            title="Drag to change status"
                                            class="flex h-6 w-5 cursor-grab items-center justify-center rounded text-slate-300 transition hover:bg-slate-100 hover:text-slate-600 active:cursor-grabbing"
                                        >
                                            ⋮⋮
                                        </button>
                                    </td>
                                    <td class="whitespace-nowrap py-2 pl-1 pr-3 font-bold">
                                        <button
                                            type="button"
                                            data-list-ticket-no
                                            data-ticket-drawer-url="{{ route('tickets.drawer', $ticket) }}"
                                            class="font-black text-slate-950 transition hover:text-indigo-700"
                                        >
                                            {{ $ticket->ticket_no }}
                                        </button>
                                    </td>
                                    <td class="px-4 py-2" data-list-title>
                                        <button
                                            type="button"
                                            data-ticket-drawer-url="{{ route('tickets.drawer', $ticket) }}"
                                            class="text-left font-bold text-slate-800 hover:text-indigo-700"
                                        >
                                            {{ $ticket->title }}
                                        </button>
                                    </td>
                                    <td class="relative overflow-visible px-4 py-2">
                                        <button
                                            type="button"
                                            data-list-status-trigger
                                            data-field-menu-trigger
                                            data-field-type="status"
                                            data-ticket-id="{{ $ticket->id }}"
                                            data-endpoint="{{ route('tickets.inline-update', $ticket) }}"
                                            data-current-value="{{ $ticket->status }}"
                                            data-options='@json($statusOptionsForThisTicket)'
                                            class="badge badge-status cursor-pointer"
                                            @click.stop="openFieldMenuFromDataset($event)"
                                        >
                                            <span data-list-status-label>{{ $ticket->formattedStatus() }}</span>
                                        </button>
                                    </td>
                                    <td class="px-4 py-2" data-list-assignee>
                                        <button
                                            type="button"
                                            data-list-assignee-trigger
                                            data-field-menu-trigger
                                            data-field-type="assignee"
                                            data-ticket-id="{{ $ticket->id }}"
                                            data-endpoint="{{ route('tickets.inline-update', $ticket) }}"
                                            data-current-value="{{ $ticket->assigned_to ? (string) $ticket->assigned_to : '' }}"
                                            data-options='@json($assigneeOptions)'
                                            class="inline-flex cursor-pointer items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 hover:bg-slate-200"
                                            @click.stop="openFieldMenuFromDataset($event)"
                                        >
                                            <span data-list-assignee-label>{{ $ticket->assignee?->name ?? 'Unassigned' }}</span>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>

    <div class="shrink-0 border-t border-slate-200 px-6 py-4">
        {{ $tickets->links() }}
    </div>
</section>
