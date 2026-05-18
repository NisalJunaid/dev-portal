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
                $childrenByParent = $sectionTickets->whereNotNull('parent_ticket_id')->groupBy('parent_ticket_id');
                $parents = $sectionTickets->filter(fn ($ticket) => $ticket->parent_ticket_id === null);
                $orphanChildren = $sectionTickets->whereNotNull('parent_ticket_id')->reject(fn ($ticket) => $parents->contains('id', $ticket->parent_ticket_id));
            @endphp

            <div class="rounded-xl border border-slate-200 bg-white" data-list-section data-section="{{ $sectionKey }}">
                <button type="button" class="flex w-full items-center justify-between bg-slate-50 px-4 py-2 text-left" @click="$el.nextElementSibling.classList.toggle('hidden')">
                    <span class="font-bold text-slate-800">{{ $sectionLabel }}</span>
                    <span class="text-xs font-black text-slate-500" data-list-section-count>{{ $sectionTickets->count() }}</span>
                </button>
                <div data-list-dropzone data-section="{{ $sectionKey }}" class="overflow-visible">
                    <table class="min-w-[1600px] w-full table-fixed divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500"><tr><th class="w-8 px-1 py-2"></th><th class="whitespace-nowrap py-2 pl-1 pr-3">Ticket #</th><th class="px-4 py-2">Title</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Assignee</th></tr></thead>
                        <tbody data-list-section-body data-section="{{ $sectionKey }}">
                            @if ($sectionTickets->isEmpty())<tr data-list-empty-row><td colspan="5" class="px-4 py-4 text-xs text-slate-400">Drop tasks here</td></tr>@endif
                            @foreach ($parents as $ticket)
                                @php $statusOptionsForThisTicket = $ticket->isBug() ? [['value'=>'bug_pending','label'=>'Pending'],['value'=>'bug_blocked','label'=>'Blocked'],['value'=>'bug_completed','label'=>'Completed'],['value'=>'rejected','label'=>'Rejected']] : [['value'=>'backlog','label'=>'Backlog'],['value'=>'in_progress','label'=>'In Progress'],['value'=>'task_blocked','label'=>'Blocked'],['value'=>'task_completed','label'=>'Completed'],['value'=>'rejected','label'=>'Rejected'],['value'=>'next_sprint','label'=>'Move to Next Sprint']]; @endphp
                                @include('tasks.partials.list-row', ['ticket' => $ticket, 'isChild' => false, 'parent' => null, 'workType' => $workType, 'statusOptionsForThisTicket' => $statusOptionsForThisTicket, 'assigneeOptions' => $assigneeOptions])
                                @foreach ($childrenByParent->get($ticket->id, collect()) as $child)
                                    @php $statusOptionsForThisTicket = $child->isBug() ? [['value'=>'bug_pending','label'=>'Pending'],['value'=>'bug_blocked','label'=>'Blocked'],['value'=>'bug_completed','label'=>'Completed'],['value'=>'rejected','label'=>'Rejected']] : [['value'=>'backlog','label'=>'Backlog'],['value'=>'in_progress','label'=>'In Progress'],['value'=>'task_blocked','label'=>'Blocked'],['value'=>'task_completed','label'=>'Completed'],['value'=>'rejected','label'=>'Rejected'],['value'=>'next_sprint','label'=>'Move to Next Sprint']]; @endphp
                                    @include('tasks.partials.list-row', ['ticket' => $child, 'isChild' => true, 'parent' => $ticket, 'workType' => $workType, 'statusOptionsForThisTicket' => $statusOptionsForThisTicket, 'assigneeOptions' => $assigneeOptions])
                                @endforeach
                            @endforeach
                            @foreach ($orphanChildren as $child)
                                @php $statusOptionsForThisTicket = $child->isBug() ? [['value'=>'bug_pending','label'=>'Pending'],['value'=>'bug_blocked','label'=>'Blocked'],['value'=>'bug_completed','label'=>'Completed'],['value'=>'rejected','label'=>'Rejected']] : [['value'=>'backlog','label'=>'Backlog'],['value'=>'in_progress','label'=>'In Progress'],['value'=>'task_blocked','label'=>'Blocked'],['value'=>'task_completed','label'=>'Completed'],['value'=>'rejected','label'=>'Rejected'],['value'=>'next_sprint','label'=>'Move to Next Sprint']]; @endphp
                                @include('tasks.partials.list-row', ['ticket' => $child, 'isChild' => true, 'parent' => null, 'workType' => $workType, 'statusOptionsForThisTicket' => $statusOptionsForThisTicket, 'assigneeOptions' => $assigneeOptions])
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
    <div class="shrink-0 border-t border-slate-200 px-6 py-4">{{ $tickets->links() }}</div>
</section>
