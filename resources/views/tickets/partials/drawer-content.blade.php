@php
        $latestSprint = $ticket->sprints->sortByDesc('sprint_no')->first();
        $statusOptions = $ticket->isBug() ? \App\Models\Ticket::BUG_STATUSES : ($ticket->isFeature() ? \App\Models\Ticket::FEATURE_STATUSES : \App\Models\Ticket::STATUSES);
        $timerPayload = $currentTimer ? [
            'status' => $currentTimer->status,
            'current_duration_seconds' => $currentTimer->currentDurationSeconds(),
        ] : null;
    @endphp

    <div class="flex h-full flex-col" data-ticket-drawer-content data-ticket-id="{{ $ticket->id }}" data-drawer-refresh-url="{{ route('tickets.drawer', $ticket) }}">
        <header class="border-b border-slate-200 bg-white px-6 py-5">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-black uppercase tracking-[0.25em] text-indigo-600">{{ $ticket->ticket_no }}</p>
                    @if ($isKielUser)
                        <input data-inline-field="title" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" value="{{ $ticket->title }}" class="mt-2 w-full rounded-2xl border-transparent px-0 text-2xl font-black tracking-tight text-slate-950 focus:border-indigo-300 focus:px-3 focus:ring-indigo-500">
                    @else
                        <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ $ticket->title }}</h3>
                    @endif
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-black uppercase tracking-wide">
                        <span class="badge badge-status">{{ $ticket->formattedStatus() }}</span>
                        <span @class(['badge', 'badge-urgency-critical' => $ticket->urgency === 'critical', 'badge-urgency-high' => $ticket->urgency === 'high', 'badge-urgency-medium' => $ticket->urgency === 'medium', 'badge-urgency-low' => $ticket->urgency === 'low'])>{{ $ticket->formattedUrgency() }}</span>
                    </div>
                </div>
                <button type="button" data-ticket-drawer-close class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-600 transition hover:border-indigo-200 hover:text-indigo-700">Close</button>
            </div>
        </header>

        <div class="flex-1 space-y-6 overflow-y-auto bg-slate-50/70 p-6">
            <div data-drawer-message class="hidden rounded-2xl border px-4 py-3 text-sm font-bold"></div>

            @if ($activeBlock)
                <section class="rounded-3xl border border-rose-200 bg-rose-50 p-5" data-blocked-banner>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm font-black uppercase tracking-wide text-rose-700">Blocked</p>
                        <p class="text-xs font-black text-rose-700">Total blocked: {{ gmdate('H:i:s', $totalBlockedDuration) }}</p>
                    </div>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-rose-950">{{ $activeBlock->reason }}</p>
                </section>
            @endif

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h4 class="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Details</h4>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-black text-slate-700">Status
                        @if ($isKielUser)
                            <select data-inline-field="status" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" class="mt-2 w-full rounded-2xl border-slate-200 text-sm font-bold shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($statusOptions as $status)
                                    <option value="{{ $status }}" @selected($ticket->status === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="mt-2 block rounded-2xl bg-slate-50 px-4 py-3 font-semibold text-slate-700">{{ $ticket->formattedStatus() }}</span>
                        @endif
                    </label>
                    <label class="block text-sm font-black text-slate-700">Urgency
                        @if ($isKielUser)
                            <select data-inline-field="urgency" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" class="mt-2 w-full rounded-2xl border-slate-200 text-sm font-bold shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach (\App\Models\Ticket::URGENCIES as $urgency)
                                    <option value="{{ $urgency }}" @selected($ticket->urgency === $urgency)>{{ str($urgency)->headline() }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="mt-2 block rounded-2xl bg-slate-50 px-4 py-3 font-semibold text-slate-700">{{ $ticket->formattedUrgency() }}</span>
                        @endif
                    </label>
                    <label class="block text-sm font-black text-slate-700">Assignee
                        @if ($isKielUser)
                            <select data-inline-field="assigned_to" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" class="mt-2 w-full rounded-2xl border-slate-200 text-sm font-bold shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Unassigned</option>
                                @foreach ($teamMembers as $member)
                                    <option value="{{ $member->id }}" @selected((int) $ticket->assigned_to === $member->id)>{{ $member->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="mt-2 block rounded-2xl bg-slate-50 px-4 py-3 font-semibold text-slate-700">{{ $ticket->assignee?->name ?? 'Unassigned' }}</span>
                        @endif
                    </label>
                    <div class="block text-sm font-black text-slate-700">Client
                        <span class="mt-2 block rounded-2xl bg-slate-50 px-4 py-3 font-semibold text-slate-700">{{ $ticket->client?->name ?? 'No client' }}</span>
                    </div>
                    <div class="block text-sm font-black text-slate-700">Software
                        <span class="mt-2 block rounded-2xl bg-slate-50 px-4 py-3 font-semibold text-slate-700">{{ $ticket->software?->name ?? 'No software' }}</span>
                    </div>
                    <div class="block text-sm font-black text-slate-700">Sprint
                        <span class="mt-2 block rounded-2xl bg-slate-50 px-4 py-3 font-semibold text-slate-700">{{ $latestSprint ? '#'.$latestSprint->sprint_no.' '.$latestSprint->name : 'No sprint' }}</span>
                    </div>
                    <label class="block text-sm font-black text-slate-700">Start date
                        @if ($isKielUser)
                            <input type="date" data-inline-field="start_date" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" value="{{ $ticket->start_date?->toDateString() }}" class="mt-2 w-full rounded-2xl border-slate-200 text-sm font-bold shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @else
                            <span class="mt-2 block rounded-2xl bg-slate-50 px-4 py-3 font-semibold text-slate-700">{{ $ticket->start_date?->format('M j, Y') ?? 'Not set' }}</span>
                        @endif
                    </label>
                    <label class="block text-sm font-black text-slate-700">Due date
                        @if ($isKielUser)
                            <input type="date" data-inline-field="due_date" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" value="{{ $ticket->due_date?->toDateString() }}" class="mt-2 w-full rounded-2xl border-slate-200 text-sm font-bold shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @else
                            <span class="mt-2 block rounded-2xl bg-slate-50 px-4 py-3 font-semibold text-slate-700">{{ $ticket->due_date?->format('M j, Y') ?? 'Not set' }}</span>
                        @endif
                    </label>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h4 class="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Description</h4>
                @if ($isKielUser)
                    <textarea rows="7" data-inline-field="description" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" class="mt-4 w-full rounded-2xl border-slate-200 text-sm leading-6 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $ticket->description }}</textarea>
                @else
                    <p class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $ticket->description }}</p>
                @endif
            </section>

            @if ($isKielUser)
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h4 class="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Timer controls</h4>
                            <p class="mt-2 text-sm font-semibold text-slate-600">Current: {{ $timerPayload ? str($timerPayload['status'])->headline() : 'No active timer' }} · Cumulative {{ gmdate('H:i:s', $cumulativeDuration ?? 0) }}</p>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        @foreach (['start' => 'Start', 'pause' => 'Pause', 'resume' => 'Resume', 'stop' => 'Stop'] as $action => $label)
                            <button type="button" data-drawer-action="timer" data-action-url="{{ route('tickets.timer.'.$action, $ticket) }}" data-saving-label="Saving…" @if($action === 'stop') data-confirm-title="Stop timer?" data-confirm-message="This will complete the active timer and add the elapsed time to the ticket." data-confirm-label="Stop timer" @endif class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-black text-white shadow-soft transition hover:bg-indigo-700">{{ $label }}</button>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h4 class="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Block controls</h4>
                    <div class="mt-4 grid gap-3">
                        @if (! $activeBlock)
                            <form data-drawer-action-form="block" data-confirm-title="Block ticket?" data-confirm-message="Blocking highlights this ticket and pauses forward progress until it is unblocked." data-confirm-label="Block ticket" action="{{ route('tickets.block', $ticket) }}" class="space-y-3">
                                <textarea name="reason" rows="3" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Reason for blocking"></textarea>
                                <button type="submit" class="rounded-2xl bg-rose-600 px-4 py-2 text-sm font-black text-white shadow-soft">Block ticket</button>
                            </form>
                        @endif
                        @if ($activeBlock)
                            <form data-drawer-action-form="unblock" data-confirm-title="Unblock ticket?" data-confirm-message="This records the unblock note and returns the ticket to active workflow." data-confirm-label="Unblock ticket" action="{{ route('tickets.unblock', $ticket) }}" class="space-y-3 border-t border-slate-100 pt-3">
                                <textarea name="unblock_note" rows="3" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Unblock note"></textarea>
                                <button type="submit" class="rounded-2xl bg-emerald-600 px-4 py-2 text-sm font-black text-white shadow-soft">Unblock ticket</button>
                            </form>
                        @endif
                    </div>
                </section>
            @endif

            @if ($canRecommend)
                <section class="rounded-3xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm">
                    <h4 class="text-sm font-black uppercase tracking-[0.2em] text-indigo-700">Client planning</h4>
                    <p class="mt-2 text-sm font-semibold text-indigo-900">Recommend this feature for the next planning cycle.</p>
                    <button type="button" data-drawer-action="recommend" data-action-url="{{ route('features.recommend', $ticket) }}" data-confirm-title="Recommend feature?" data-confirm-message="This will move the feature into the recommended planning queue." data-confirm-label="Recommend" class="mt-4 rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-soft">Recommend feature</button>
                </section>
            @endif


            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h4 class="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Sub-items</h4>
                    @if ($ticket->isFeature())
                        <button type="button" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-bold" data-add-subfeature data-parent-ticket-id="{{ $ticket->id }}">Add sub-feature</button>
                    @elseif ($ticket->isTask() && $isKielUser)
                        <button type="button" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-bold" data-add-subtask data-parent-ticket-id="{{ $ticket->id }}">Add subtask</button>
                    @endif
                </div>
                @php $children = $ticket->children()->with('assignee')->get(); @endphp
                <div class="mt-3 space-y-2">
                    @forelse ($children as $child)
                        <button type="button" class="flex w-full items-center justify-between rounded-xl border border-slate-100 px-3 py-2 text-left hover:bg-slate-50" data-ticket-open="{{ route('tickets.drawer', $child) }}">
                            <span class="text-sm font-semibold text-slate-700">{{ $child->title }}</span>
                            <span class="text-xs text-slate-500">{{ $child->formattedStatus() }}</span>
                        </button>
                    @empty
                        <p class="text-sm text-slate-500">No sub-items yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h4 class="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Comments</h4>
                <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" data-drawer-comment-form class="mt-4 space-y-3">
                    @csrf
                    <textarea name="comment" rows="4" required class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Add a comment"></textarea>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        @if ($isKielUser)
                            <label class="flex items-center gap-2 text-sm font-bold text-slate-600"><input type="checkbox" name="is_internal" value="1" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">Internal</label>
                        @endif
                        <button type="submit" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-soft">Add comment</button>
                    </div>
                </form>
                <div class="mt-5" data-drawer-comments>
                    @include('tickets.partials.comments', ['comments' => $comments, 'ticket' => $ticket, 'isKielUser' => $isKielUser])
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h4 class="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Activity timeline</h4>
                <div class="mt-5 border-l border-slate-200 pl-2" data-drawer-activity>
                    @include('tickets.partials.activity-timeline', ['activities' => $ticket->activities])
                </div>
            </section>
        </div>
    </div>
