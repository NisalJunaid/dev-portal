<x-app-layout>
    <x-slot name="header">{{ $ticket->ticket_no }}</x-slot>

    <div class="space-y-6" x-data="{ formattedStatus: @js($ticket->formattedStatus()), activeBlock: @js($activeBlock ? ['reason' => $activeBlock->reason, 'current_duration_seconds' => $activeBlock->currentDurationSeconds()] : null), totalBlockedSeconds: @js($totalBlockedDuration) }" @ticket-block-updated.window="formattedStatus = $event.detail.ticket.formatted_status; activeBlock = $event.detail.active_block; totalBlockedSeconds = $event.detail.ticket.total_blocked_duration_seconds">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-800">Please review the highlighted fields and try again.</div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_26rem]">
            <main class="space-y-6">
                <section class="card">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="font-black text-indigo-700">{{ $ticket->ticket_no }}</span>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase tracking-wide text-slate-700"><span x-text="formattedStatus">{{ $ticket->formattedStatus() }}</span></span>
                                <span @class(['rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide', 'bg-rose-100 text-rose-700' => $ticket->urgency === 'critical', 'bg-orange-100 text-orange-700' => $ticket->urgency === 'high', 'bg-amber-100 text-amber-700' => $ticket->urgency === 'medium', 'bg-emerald-100 text-emerald-700' => $ticket->urgency === 'low'])>{{ $ticket->formattedUrgency() }}</span>
                            </div>
                            <h2 class="mt-4 text-3xl font-black tracking-tight text-slate-950">{{ $ticket->title }}</h2>
                        </div>
                        <a href="{{ route($isKielUser ? 'tickets.backlog' : 'tickets.index') }}" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600">Back</a>
                    </div>
                    <p class="mt-6 whitespace-pre-line leading-7 text-slate-700">{{ $ticket->description }}</p>



                    <div x-show="activeBlock" x-cloak class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-sm font-black uppercase tracking-wide text-rose-700">Blocked</p>
                            <p class="text-xs font-black text-rose-700">Total blocked: <span x-text="formatBlockedDuration(totalBlockedSeconds)">{{ gmdate('H:i', $totalBlockedDuration) }}</span></p>
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-rose-950" x-text="activeBlock?.reason">{{ $activeBlock?->reason }}</p>
                    </div>

                    @if ($ticket->status === App\Models\Ticket::STATUS_REJECTED && $ticket->rejection_reason)
                        <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-5">
                            <p class="text-sm font-black uppercase tracking-wide text-rose-700">Formal rejection reason</p>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-rose-900">{{ $ticket->rejection_reason }}</p>
                        </div>
                    @endif
                </section>

                <section class="card">
                    <h3 class="text-xl font-black text-slate-950">Comments</h3>
                    <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" class="mt-5 space-y-3">
                        @csrf
                        <textarea name="comment" rows="4" class="w-full rounded-2xl border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Add a comment" required>{{ old('comment') }}</textarea>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            @if ($isKielUser)
                                <label class="flex items-center gap-2 text-sm font-bold text-slate-600">
                                    <input type="checkbox" name="is_internal" value="1" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    Mark as internal (hidden from clients)
                                </label>
                            @else
                                <p class="text-sm text-slate-500">Kiel internal notes are hidden from client users.</p>
                            @endif
                            <button type="submit" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-soft hover:bg-indigo-700">Add comment</button>
                        </div>
                    </form>
                    <div class="mt-6">
                        @include('tickets.partials.comments', ['comments' => $comments, 'ticket' => $ticket, 'isKielUser' => $isKielUser])
                    </div>
                </section>

                <section class="card">
                    <h3 class="text-xl font-black text-slate-950">Visual timeline</h3>
                    <div class="mt-6 border-l border-slate-200 pl-2">
                        @include('tickets.partials.activity-timeline', ['activities' => $ticket->activities])
                    </div>
                </section>
            </main>

            <aside class="space-y-6 xl:sticky xl:top-28 xl:self-start">
                <section class="card">
                    <h3 class="text-lg font-black text-slate-950">Metadata</h3>
                    <dl class="mt-5 space-y-4 text-sm">
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Client</dt><dd class="text-right font-black text-slate-900">{{ $ticket->client->name }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Software</dt><dd class="text-right font-black text-slate-900">{{ $ticket->software->name }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Submitted by</dt><dd class="text-right font-black text-slate-900">{{ $ticket->submitter->name }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Assigned to</dt><dd class="text-right font-black text-slate-900">{{ $ticket->assignee?->name ?? 'Unassigned' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Submitted</dt><dd class="text-right font-black text-slate-900">{{ $ticket->submitted_at->format('M j, Y') }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Start</dt><dd class="text-right font-black text-slate-900">{{ $ticket->start_date?->format('M j, Y') ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Due</dt><dd class="text-right font-black text-slate-900">{{ $ticket->due_date?->format('M j, Y') ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Estimate</dt><dd class="text-right font-black text-slate-900">{{ $ticket->estimated_hours ? $ticket->estimated_hours.' hrs' : '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Blocked duration</dt><dd class="text-right font-black text-slate-900" x-text="formatBlockedDuration(totalBlockedSeconds)"></dd></div>
                    </dl>
                </section>

                @if ($isKielUser)
                    @include('tickets.partials.timer-panel', ['ticket' => $ticket, 'currentTimer' => $currentTimer, 'cumulativeDuration' => $cumulativeDuration])
                    @include('tickets.partials.block-panel', ['ticket' => $ticket, 'activeBlock' => $activeBlock, 'totalBlockedDuration' => $totalBlockedDuration])

                    @if ($blockHistory->isNotEmpty())
                        <section class="card">
                            <h3 class="text-lg font-black text-slate-950">Block history</h3>
                            <div class="mt-5 space-y-4">
                                @foreach ($blockHistory as $block)
                                    <article class="rounded-2xl border border-slate-200 p-4 text-sm">
                                        <div class="flex flex-wrap justify-between gap-3">
                                            <p class="font-black text-slate-900">{{ $block->blocker?->name ?? 'Kiel team' }} blocked this ticket</p>
                                            <p class="font-bold text-slate-500">{{ $block->blocked_at->format('M j, Y g:i A') }}</p>
                                        </div>
                                        <p class="mt-2 whitespace-pre-line text-slate-700">{{ $block->reason }}</p>
                                        @if ($block->unblocked_at)
                                            <div class="mt-3 rounded-2xl bg-emerald-50 p-3 text-emerald-900">
                                                <p class="font-black">Unblocked by {{ $block->unblocker?->name ?? 'Kiel team' }} after {{ gmdate('H:i:s', $block->duration_seconds ?? 0) }}</p>
                                                <p class="mt-1 whitespace-pre-line">{{ $block->unblock_note }}</p>
                                            </div>
                                        @else
                                            <p class="mt-3 font-black text-rose-700">Currently blocked</p>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <section class="card space-y-5">
                        <h3 class="text-lg font-black text-slate-950">Kiel controls</h3>

                        <form method="POST" action="{{ route('tickets.classify', $ticket) }}" class="space-y-3 rounded-2xl bg-slate-50 p-4">
                            @csrf
                            @method('PATCH')
                            <label class="text-sm font-black text-slate-700">Classify ticket</label>
                            <select name="type" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="bug" @selected($ticket->type === 'bug')>Bug → bug pending</option>
                                <option value="feature" @selected($ticket->type === 'feature')>Feature → feature approved</option>
                            </select>
                            <button type="submit" class="w-full rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-black text-white">Classify</button>
                        </form>

                        <form method="POST" action="{{ route('tickets.reject', $ticket) }}" class="space-y-3 rounded-2xl bg-rose-50 p-4">
                            @csrf
                            @method('PATCH')
                            <label class="text-sm font-black text-rose-800">Reject with formal reason</label>
                            <textarea name="rejection_reason" rows="4" class="w-full rounded-2xl border-rose-200 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500" required>{{ old('rejection_reason', $ticket->rejection_reason) }}</textarea>
                            <button type="submit" class="w-full rounded-2xl bg-rose-600 px-4 py-2 text-sm font-black text-white">Reject ticket</button>
                        </form>

                        <form method="POST" action="{{ route('tickets.assign', $ticket) }}" class="space-y-3 rounded-2xl bg-slate-50 p-4">
                            @csrf
                            @method('PATCH')
                            <label class="text-sm font-black text-slate-700">Assign team member</label>
                            <select name="assigned_to" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Unassigned</option>
                                @foreach ($teamMembers as $member)
                                    <option value="{{ $member->id }}" @selected($ticket->assigned_to === $member->id)>{{ $member->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="w-full rounded-2xl bg-slate-900 px-4 py-2 text-sm font-black text-white">Update assignment</button>
                        </form>

                        <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="space-y-4 rounded-2xl bg-slate-50 p-4">
                            @csrf
                            @method('PATCH')
                            <h4 class="text-sm font-black text-slate-700">Edit details</h4>
                            <select name="software_id" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($softwares as $software)
                                    <option value="{{ $software->id }}" @selected($ticket->software_id === $software->id)>{{ $software->name }} — {{ $software->client->name }}</option>
                                @endforeach
                            </select>
                            <select name="urgency" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach (App\Models\Ticket::URGENCIES as $urgency)
                                    <option value="{{ $urgency }}" @selected($ticket->urgency === $urgency)>{{ str($urgency)->headline() }}</option>
                                @endforeach
                            </select>
                            <select name="assigned_to" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Unassigned</option>
                                @foreach ($teamMembers as $member)
                                    <option value="{{ $member->id }}" @selected($ticket->assigned_to === $member->id)>{{ $member->name }}</option>
                                @endforeach
                            </select>
                            <select name="status" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach (App\Models\Ticket::STATUSES as $status)
                                    @continue(in_array($status, [App\Models\Ticket::STATUS_BUG_BLOCKED, App\Models\Ticket::STATUS_FEATURE_BLOCKED], true) && $ticket->status !== $status)
                                    <option value="{{ $status }}" @selected($ticket->status === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>
                                @endforeach
                            </select>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input type="date" name="start_date" value="{{ old('start_date', $ticket->start_date?->format('Y-m-d')) }}" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <input type="date" name="due_date" value="{{ old('due_date', $ticket->due_date?->format('Y-m-d')) }}" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <input type="number" step="0.25" min="0" name="estimated_hours" value="{{ old('estimated_hours', $ticket->estimated_hours) }}" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Estimated hours">
                            <button type="submit" class="w-full rounded-2xl bg-slate-900 px-4 py-2 text-sm font-black text-white">Save details</button>
                        </form>
                    </section>
                @endif
            </aside>
        </div>
        <script>
            function formatBlockedDuration(totalSeconds) {
                const total = Math.max(0, Number(totalSeconds || 0));
                const days = Math.floor(total / 86400);
                const hours = Math.floor((total % 86400) / 3600).toString().padStart(2, '0');
                const minutes = Math.floor((total % 3600) / 60).toString().padStart(2, '0');
                return `${days > 0 ? `${days}d ` : ''}${hours}:${minutes}`;
            }
        </script>
    </div>
</x-app-layout>
