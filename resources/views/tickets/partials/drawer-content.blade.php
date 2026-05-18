@php
$latestSprint = $ticket->sprints->sortByDesc('sprint_no')->first();
$statusOptions = $ticket->isBug() ? \App\Models\Ticket::BUG_STATUSES : ($ticket->isFeature() ? \App\Models\Ticket::FEATURE_STATUSES : \App\Models\Ticket::TASK_STATUSES);
$timerPayload = $currentTimer ? ['status' => $currentTimer->status, 'current_duration_seconds' => $currentTimer->currentDurationSeconds()] : ['status' => 'idle', 'current_duration_seconds' => 0];
$children = $ticket->children()->with('assignee')->get();
@endphp
<div class="flex h-full flex-col" data-ticket-drawer-content data-ticket-id="{{ $ticket->id }}" data-drawer-refresh-url="{{ route('tickets.drawer', $ticket) }}" x-data="{ showBlockForm:false, showUnblockForm:false }">
<header class="border-b border-slate-200 bg-white px-6 py-4">
    <div class="flex items-start gap-4">
        <div class="min-w-0 flex-1">
            <p class="text-xs font-black uppercase tracking-[0.25em] text-indigo-600">{{ $ticket->ticket_no }}</p>
            @if($isKielUser)
                <input data-inline-field="title" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" value="{{ $ticket->title }}" class="mt-1 w-full rounded-xl border border-transparent px-0 text-xl font-black text-slate-950 focus:border-indigo-300 focus:px-2 focus:ring-indigo-500">
            @else
                <h3 class="mt-1 text-xl font-black text-slate-950">{{ $ticket->title }}</h3>
            @endif
            <div class="mt-2 flex flex-wrap gap-2"><span class="badge badge-status">{{ $ticket->formattedStatus() }}</span><span class="badge">{{ $ticket->formattedUrgency() }}</span></div>
        </div>
        <div class="ml-auto flex shrink-0 items-center gap-2">
            <div class="drawer-timer-pill" data-ticket-timer data-status="{{ $timerPayload['status'] }}" data-elapsed="{{ $timerPayload['current_duration_seconds'] }}"><span data-ticket-timer-display>00:00:00</span></div>
            @if($activeBlock)<span class="drawer-timer-pill text-rose-700">Blocked · {{ gmdate('H:i:s', $totalBlockedDuration) }}</span>@endif
            @if($isKielUser)
                @if(($timerPayload['status'] ?? 'idle')==='running')
                    <button type="button" class="drawer-icon-button" data-drawer-action="timer" data-action-url="{{ route('tickets.timer.pause', $ticket) }}" title="Pause timer"><span class="sr-only">Pause</span><svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M6 4h3v12H6zM11 4h3v12h-3z"/></svg></button>
                    <button type="button" class="drawer-icon-button" data-drawer-action="timer" data-action-url="{{ route('tickets.timer.stop', $ticket) }}" title="Stop timer"><span class="sr-only">Stop</span><svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><rect x="5" y="5" width="10" height="10"/></svg></button>
                @elseif(($timerPayload['status'] ?? 'idle')==='paused')
                    <button type="button" class="drawer-icon-button" data-drawer-action="timer" data-action-url="{{ route('tickets.timer.resume', $ticket) }}" title="Resume timer"><span class="sr-only">Resume</span><svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M6 4l10 6-10 6V4z"/></svg></button>
                @else
                    <button type="button" class="drawer-icon-button" data-drawer-action="timer" data-action-url="{{ route('tickets.timer.start', $ticket) }}" title="Start timer"><span class="sr-only">Start</span><svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M6 4l10 6-10 6V4z"/></svg></button>
                @endif
                @if(!$activeBlock)
                    <button type="button" class="drawer-icon-button" title="Block" @click="showBlockForm=!showBlockForm;showUnblockForm=false"><span class="sr-only">Block</span><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><circle cx="10" cy="10" r="7"/><path d="M5 15 15 5"/></svg></button>
                @else
                    <button type="button" class="drawer-icon-button" title="Unblock" @click="showUnblockForm=!showUnblockForm;showBlockForm=false"><span class="sr-only">Unblock</span><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path d="M6 9V7a4 4 0 1 1 8 0"/><rect x="4" y="9" width="12" height="8" rx="2"/></svg></button>
                @endif
            @endif
            <button type="button" data-ticket-drawer-close class="drawer-icon-button" title="Close"><span class="sr-only">Close</span><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path d="m5 5 10 10M15 5 5 15"/></svg></button>
        </div>
    </div>
    @if($activeBlock)<section class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-800">Blocked reason: {{ $activeBlock->reason }}</section>@endif
</header>
<div class="flex-1 overflow-y-auto bg-slate-50/30 sleek-scrollbar">
    @if($isKielUser && !$activeBlock)
    <form x-show="showBlockForm" data-drawer-action-form="block" action="{{ route('tickets.block', $ticket) }}" class="drawer-section space-y-2"><textarea name="reason" rows="2" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Reason for blocking"></textarea><button type="submit" class="drawer-secondary-button">Block</button></form>
    @endif
    @if($isKielUser && $activeBlock)
    <form x-show="showUnblockForm" data-drawer-action-form="unblock" action="{{ route('tickets.unblock', $ticket) }}" class="drawer-section space-y-2"><textarea name="unblock_note" rows="2" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Unblock note"></textarea><button type="submit" class="drawer-secondary-button">Unblock</button></form>
    @endif

    <section class="drawer-section"><h4 class="text-xs font-bold uppercase tracking-wide text-slate-500">Details</h4>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div><label class="text-xs font-bold uppercase tracking-wide text-slate-500">Status</label>@if($isKielUser)<select data-inline-field="status" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold">@foreach($statusOptions as $option)<option value="{{ $option }}" @selected($ticket->status===$option)>{{ str($option)->replace('_',' ')->headline() }}</option>@endforeach</select>@else<span class="mt-1 block rounded-xl bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">{{ $ticket->formattedStatus() }}</span>@endif</div>
            <div><label class="text-xs font-bold uppercase tracking-wide text-slate-500">Urgency</label>@if($isKielUser)<select data-inline-field="urgency" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold">@foreach(\App\Models\Ticket::URGENCIES as $u)<option value="{{ $u }}" @selected($ticket->urgency===$u)>{{ str($u)->headline() }}</option>@endforeach</select>@else<span class="mt-1 block rounded-xl bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">{{ $ticket->formattedUrgency() }}</span>@endif</div>
            <div><label class="text-xs font-bold uppercase tracking-wide text-slate-500">Assignee</label>@if($isKielUser)<select data-inline-field="assigned_to" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold"><option value="">Unassigned</option>@foreach($teamMembers as $member)<option value="{{ $member->id }}" @selected((int)$ticket->assigned_to===(int)$member->id)>{{ $member->name }}</option>@endforeach</select>@else<span class="mt-1 block rounded-xl bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">{{ $ticket->assignee?->name ?? 'Unassigned' }}</span>@endif</div>
            <div><label class="text-xs font-bold uppercase tracking-wide text-slate-500">Client</label><span class="mt-1 block rounded-xl bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">{{ $ticket->client?->name ?? '—' }}</span></div>
            <div><label class="text-xs font-bold uppercase tracking-wide text-slate-500">Software</label><span class="mt-1 block rounded-xl bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">{{ $ticket->software?->name ?? '—' }}</span></div>
            <div><label class="text-xs font-bold uppercase tracking-wide text-slate-500">Sprint</label><span class="mt-1 block rounded-xl bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">{{ $latestSprint ? '#'.$latestSprint->sprint_no.' '.$latestSprint->name : 'No sprint' }}</span></div>
            <div><label class="text-xs font-bold uppercase tracking-wide text-slate-500">Start date</label>@if($isKielUser)<input type="date" value="{{ $ticket->start_date?->toDateString() }}" data-inline-field="start_date" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold">@else<span class="mt-1 block rounded-xl bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">{{ $ticket->start_date?->format('M j, Y') ?? 'Not set' }}</span>@endif</div>
            <div><label class="text-xs font-bold uppercase tracking-wide text-slate-500">Due date</label>@if($isKielUser)<input type="date" value="{{ $ticket->due_date?->toDateString() }}" data-inline-field="due_date" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold">@else<span class="mt-1 block rounded-xl bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">{{ $ticket->due_date?->format('M j, Y') ?? 'Not set' }}</span>@endif</div>
        </div>
    </section>

    <section class="drawer-section"><h4 class="text-xs font-bold uppercase tracking-wide text-slate-500">Description</h4>@if($isKielUser)<textarea rows="6" data-inline-field="description" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" class="mt-3 w-full rounded-xl border-slate-200 text-sm leading-6 focus:border-indigo-500 focus:ring-indigo-500">{{ $ticket->description }}</textarea>@else<p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $ticket->description }}</p>@endif</section>

    <section class="drawer-section"><h4 class="text-xs font-bold uppercase tracking-wide text-slate-500">Sub-items</h4>
        @if($ticket->canHaveSubtasks() && $isKielUser)<button type="button" class="drawer-secondary-button mt-2" data-add-subtask data-parent-ticket-id="{{ $ticket->id }}">+ Subtask</button>@endif
        @if($ticket->isSubtask())<p class="mt-2 text-xs text-slate-500">Subtasks cannot have nested subtasks.</p>@endif
        <div class="mt-2 space-y-1">@forelse($children as $child)<button type="button" class="flex w-full items-center justify-between rounded-lg border border-slate-100 px-3 py-2 text-left hover:bg-slate-50" data-ticket-open="{{ route('tickets.drawer', $child) }}"><span class="text-sm font-semibold">{{ $child->ticket_no }} · {{ $child->title }}</span><span class="text-xs">{{ $child->formattedStatus() }} · {{ $child->assignee?->name ?? 'Unassigned' }} · {{ $child->due_date?->format('M j') ?? 'No due' }}</span></button>@empty<p class="text-sm text-slate-500">No sub-items yet.</p>@endforelse</div>
    </section>

    <section class="drawer-section"><h4 class="text-xs font-black uppercase tracking-widest text-slate-500">Comments</h4><form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" data-drawer-comment-form class="mt-3 space-y-2">@csrf <textarea name="comment" rows="3" required class="w-full rounded-xl border-slate-200 text-sm" placeholder="Add a comment"></textarea><button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white">Add comment</button></form><div class="mt-3" data-drawer-comments>@include('tickets.partials.comments',['comments'=>$comments,'ticket'=>$ticket,'isKielUser'=>$isKielUser])</div></section>
    <section class="drawer-section"><h4 class="text-xs font-black uppercase tracking-widest text-slate-500">Activity timeline</h4><div class="mt-3 border-l border-slate-200 pl-2" data-drawer-activity>@include('tickets.partials.activity-timeline',['activities'=>$ticket->activities])</div></section>
</div></div>
