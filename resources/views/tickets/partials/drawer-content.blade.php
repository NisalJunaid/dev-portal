@php
$latestSprint = $ticket->sprints->sortByDesc('sprint_no')->first();
$statusOptions = $ticket->isBug() ? \App\Models\Ticket::BUG_STATUSES : ($ticket->isFeature() ? \App\Models\Ticket::FEATURE_STATUSES : \App\Models\Ticket::STATUSES);
$timerPayload = $currentTimer ? ['status' => $currentTimer->status, 'current_duration_seconds' => $currentTimer->currentDurationSeconds()] : ['status' => 'idle', 'current_duration_seconds' => 0];
@endphp
<div class="flex h-full flex-col" data-ticket-drawer-content data-ticket-id="{{ $ticket->id }}" data-drawer-refresh-url="{{ route('tickets.drawer', $ticket) }}" x-data="{ showBlockForm:false, showUnblockForm:false }">
<header class="border-b border-slate-200 bg-white px-6 py-4">
<div class="flex items-start justify-between gap-4"><div class="min-w-0 flex-1"><p class="text-xs font-black uppercase tracking-[0.25em] text-indigo-600">{{ $ticket->ticket_no }}</p>
@if($isKielUser)<input data-inline-field="title" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" value="{{ $ticket->title }}" class="mt-1 w-full rounded-xl border border-transparent px-0 text-xl font-black text-slate-950 focus:border-indigo-300 focus:px-2 focus:ring-indigo-500">@else<h3 class="mt-1 text-xl font-black text-slate-950">{{ $ticket->title }}</h3>@endif
<div class="mt-2 flex flex-wrap gap-2"><span class="badge badge-status">{{ $ticket->formattedStatus() }}</span><span @class(['badge','badge-urgency-critical'=>$ticket->urgency==='critical','badge-urgency-high'=>$ticket->urgency==='high','badge-urgency-medium'=>$ticket->urgency==='medium','badge-urgency-low'=>$ticket->urgency==='low'])>{{ $ticket->formattedUrgency() }}</span></div>
<div class="drawer-action-bar">
<div class="drawer-timer-pill" data-ticket-timer data-status="{{ $timerPayload['status'] ?? 'idle' }}" data-elapsed="{{ $timerPayload['current_duration_seconds'] ?? 0 }}"><span data-ticket-timer-display>00:00:00</span><span class="text-[10px] uppercase">{{ str($timerPayload['status'] ?? 'idle')->headline() }}</span></div>
@if($isKielUser)
@if(($timerPayload['status'] ?? 'idle')==='running')
<button type="button" data-drawer-action="timer" data-action-url="{{ route('tickets.timer.pause', $ticket) }}" class="drawer-icon-button" title="Pause timer"><span class="sr-only">Pause timer</span>⏸</button><button type="button" data-drawer-action="timer" data-action-url="{{ route('tickets.timer.stop', $ticket) }}" data-confirm-title="Stop timer?" data-confirm-message="This will complete the active timer and add the elapsed time to the ticket." data-confirm-label="Stop timer" class="drawer-icon-button drawer-icon-button-danger" title="Stop timer"><span class="sr-only">Stop timer</span>■</button>
@elseif(($timerPayload['status'] ?? 'idle')==='paused')
<button type="button" data-drawer-action="timer" data-action-url="{{ route('tickets.timer.resume', $ticket) }}" class="drawer-icon-button" title="Resume timer"><span class="sr-only">Resume timer</span>▶</button><button type="button" data-drawer-action="timer" data-action-url="{{ route('tickets.timer.stop', $ticket) }}" data-confirm-title="Stop timer?" data-confirm-message="This will complete the active timer and add the elapsed time to the ticket." data-confirm-label="Stop timer" class="drawer-icon-button drawer-icon-button-danger" title="Stop timer"><span class="sr-only">Stop timer</span>■</button>
@else
<button type="button" data-drawer-action="timer" data-action-url="{{ route('tickets.timer.start', $ticket) }}" class="drawer-icon-button" title="Start timer"><span class="sr-only">Start timer</span>▶</button>
@endif
@if(!$activeBlock)
<button type="button" class="drawer-icon-button" title="Block task" @click="showBlockForm = !showBlockForm; showUnblockForm = false"><span class="sr-only">Block task</span>🚫</button>
@else
<span class="drawer-timer-pill text-rose-700">Blocked · {{ gmdate('H:i:s', $totalBlockedDuration) }}</span>
<button type="button" class="drawer-icon-button" title="Unblock task" @click="showUnblockForm = !showUnblockForm; showBlockForm = false"><span class="sr-only">Unblock task</span>🔓</button>
@endif
@endif
</div></div><button type="button" data-ticket-drawer-close class="drawer-icon-button" title="Close"><span class="sr-only">Close</span>✕</button></div>
@if($activeBlock)<section class="border-t border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800" data-blocked-banner>Blocked reason: {{ $activeBlock->reason }}</section>@endif
</header>
<div class="flex-1 overflow-y-auto bg-slate-50/30 sleek-scrollbar">
<div data-drawer-message class="hidden m-4 rounded-xl border px-4 py-3 text-sm font-bold"></div>
@if($isKielUser && !$activeBlock)
<form x-show="showBlockForm" data-drawer-action-form="block" action="{{ route('tickets.block', $ticket) }}" class="drawer-section space-y-2"><textarea name="reason" rows="2" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Reason for blocking"></textarea><div class="flex gap-2"><button type="submit" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white">Block</button><button type="button" class="rounded-lg border px-3 py-1.5 text-xs" @click="showBlockForm=false">Cancel</button></div></form>
@endif
@if($isKielUser && $activeBlock)
<form x-show="showUnblockForm" data-drawer-action-form="unblock" action="{{ route('tickets.unblock', $ticket) }}" class="drawer-section space-y-2"><textarea name="unblock_note" rows="2" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Unblock note"></textarea><div class="flex gap-2"><button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white">Unblock</button><button type="button" class="rounded-lg border px-3 py-1.5 text-xs" @click="showUnblockForm=false">Cancel</button></div></form>
@endif
<section class="drawer-section"><h4 class="text-xs font-black uppercase tracking-widest text-slate-500">Details</h4></section>
<section class="drawer-section"><h4 class="text-xs font-black uppercase tracking-widest text-slate-500">Sub-items</h4>@php $children=$ticket->children()->with('assignee')->get(); @endphp
<div class="mt-2 space-y-1">@forelse($children as $child)<button type="button" class="flex w-full items-center justify-between rounded-lg border border-slate-100 px-3 py-2 text-left hover:bg-slate-50" data-ticket-open="{{ route('tickets.drawer', $child) }}"><span class="text-sm font-semibold">{{ $child->ticket_no }} · {{ $child->title }}</span><span class="text-xs">{{ $child->formattedStatus() }} · {{ $child->assignee?->name ?? 'Unassigned' }}</span></button>@empty<p class="text-sm text-slate-500">No sub-items yet.</p>@endforelse</div></section>
<section class="drawer-section"><h4 class="text-xs font-black uppercase tracking-widest text-slate-500">Comments</h4><form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" data-drawer-comment-form class="mt-3 space-y-2">@csrf <textarea name="comment" rows="3" required class="w-full rounded-xl border-slate-200 text-sm" placeholder="Add a comment"></textarea><button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white">Add comment</button></form><div class="mt-3" data-drawer-comments>@include('tickets.partials.comments',['comments'=>$comments,'ticket'=>$ticket,'isKielUser'=>$isKielUser])</div></section>
<section class="drawer-section"><h4 class="text-xs font-black uppercase tracking-widest text-slate-500">Activity timeline</h4><div class="mt-3 border-l border-slate-200 pl-2" data-drawer-activity>@include('tickets.partials.activity-timeline',['activities'=>$ticket->activities])</div></section>
</div></div>
