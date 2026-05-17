<x-app-layout>
    <x-slot name="header">{{ $ticket->ticket_no }}</x-slot>

    <div class="space-y-6">
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
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase tracking-wide text-slate-700">{{ $ticket->formattedStatus() }}</span>
                                <span @class(['rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide', 'bg-rose-100 text-rose-700' => $ticket->urgency === 'critical', 'bg-orange-100 text-orange-700' => $ticket->urgency === 'high', 'bg-amber-100 text-amber-700' => $ticket->urgency === 'medium', 'bg-emerald-100 text-emerald-700' => $ticket->urgency === 'low'])>{{ $ticket->formattedUrgency() }}</span>
                            </div>
                            <h2 class="mt-4 text-3xl font-black tracking-tight text-slate-950">{{ $ticket->title }}</h2>
                            <p class="mt-2 font-semibold text-slate-500">{{ $ticket->client->name }} · {{ $ticket->software->name }}</p>
                        </div>
                        <a href="{{ route('features.index') }}" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600">Back</a>
                    </div>
                    <p class="mt-6 whitespace-pre-line leading-7 text-slate-700">{{ $ticket->description }}</p>

                    @if ($activeBlock)
                        <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="text-sm font-black uppercase tracking-wide text-rose-700">Blocked</p>
                                <p class="text-xs font-black text-rose-700">Total blocked: {{ gmdate('H:i:s', $totalBlockedDuration) }}</p>
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-rose-950">{{ $activeBlock->reason }}</p>
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
                                <label class="flex items-center gap-2 text-sm font-bold text-slate-600"><input type="checkbox" name="is_internal" value="1" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500"> Internal Kiel note</label>
                            @endif
                            <button type="submit" class="rounded-2xl bg-slate-900 px-5 py-2.5 text-sm font-black text-white">Add comment</button>
                        </div>
                    </form>
                    @include('tickets.partials.comments', ['comments' => $comments, 'ticket' => $ticket, 'isKielUser' => $isKielUser])
                </section>

                <section class="card">
                    <p class="text-sm font-bold uppercase tracking-[0.2em] text-slate-400">Audit trail</p>
                    <h3 class="mt-2 text-xl font-black text-slate-950">Activity</h3>
                    <div class="mt-6">@include('tickets.partials.activity-timeline', ['activities' => $ticket->activities])</div>
                </section>
            </main>

            <aside class="space-y-6">
                <section class="card">
                    <p class="text-sm font-bold uppercase tracking-[0.2em] text-slate-400">Feature actions</p>
                    <h3 class="mt-2 text-xl font-black text-slate-950">Workflow</h3>
                    <div class="mt-5 grid gap-3">
                        @if ($ticket->status === App\Models\Ticket::STATUS_FEATURE_APPROVED)
                            <form method="POST" action="{{ route('features.recommend', $ticket) }}">@csrf<button type="submit" class="w-full rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-black text-indigo-800">Recommend for next cycle</button></form>
                        @endif
                        @if ($isKielUser)
                            @if (in_array($ticket->status, [App\Models\Ticket::STATUS_FEATURE_APPROVED, App\Models\Ticket::STATUS_RECOMMENDED], true))
                                <form method="POST" action="{{ route('features.approve-next-sprint', $ticket) }}">@csrf<button type="submit" class="w-full rounded-2xl bg-indigo-600 px-4 py-2.5 text-sm font-black text-white">Move to next sprint</button></form>
                            @endif
                            @if ($ticket->status === App\Models\Ticket::STATUS_RECOMMENDED)
                                <form method="POST" action="{{ route('features.defer', $ticket) }}" class="space-y-2">@csrf<textarea name="reason" rows="3" class="w-full rounded-2xl border-amber-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500" placeholder="Optional deferral reason"></textarea><button type="submit" class="w-full rounded-2xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-black text-amber-800">Defer recommendation</button></form>
                                <form method="POST" action="{{ route('tickets.reject', $ticket) }}" class="space-y-2 rounded-2xl bg-rose-50 p-3">@csrf @method('PATCH')<textarea name="rejection_reason" rows="3" class="w-full rounded-2xl border-rose-200 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500" placeholder="Required rejection reason" required>{{ old('rejection_reason', $ticket->rejection_reason) }}</textarea><button type="submit" class="w-full rounded-2xl bg-rose-600 px-4 py-2.5 text-sm font-black text-white">Reject recommendation</button></form>
                            @endif
                            @if ($ticket->status !== App\Models\Ticket::STATUS_FEATURE_COMPLETED)
                                <form method="POST" action="{{ route('features.complete', $ticket) }}">@csrf<button type="submit" class="w-full rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-black text-emerald-800">Complete feature</button></form>
                            @endif
                        @endif
                    </div>
                </section>

                @if ($isKielUser)
                    <section class="card">
                        <p class="text-sm font-bold uppercase tracking-[0.2em] text-slate-400">Kiel planning</p>
                        <h3 class="mt-2 text-xl font-black text-slate-950">Edit feature</h3>
                        <form method="POST" action="{{ route('features.update', $ticket) }}" class="mt-5 space-y-3">
                            @csrf
                            @method('PATCH')
                            <input type="text" name="title" value="{{ old('title', $ticket->title) }}" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Title" required>
                            <textarea name="description" rows="5" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Description" required>{{ old('description', $ticket->description) }}</textarea>
                            <select name="urgency" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach (App\Models\Ticket::URGENCIES as $urgency)
                                    <option value="{{ $urgency }}" @selected(old('urgency', $ticket->urgency) === $urgency)>{{ str($urgency)->headline() }}</option>
                                @endforeach
                            </select>
                            <select name="assigned_to" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Unassigned</option>
                                @foreach ($teamMembers as $member)
                                    <option value="{{ $member->id }}" @selected(old('assigned_to', $ticket->assigned_to) == $member->id)>{{ $member->name }}</option>
                                @endforeach
                            </select>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input type="date" name="start_date" value="{{ old('start_date', $ticket->start_date?->format('Y-m-d')) }}" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <input type="date" name="due_date" value="{{ old('due_date', $ticket->due_date?->format('Y-m-d')) }}" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <input type="number" step="0.25" min="0" name="estimated_hours" value="{{ old('estimated_hours', $ticket->estimated_hours) }}" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Estimated hours">
                            <button type="submit" class="w-full rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-black text-white">Save feature</button>
                        </form>
                    </section>
                @endif

                <section class="card space-y-4 text-sm">
                    <p class="text-sm font-bold uppercase tracking-[0.2em] text-slate-400">Metadata</p>
                    <dl class="space-y-3">
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Submitted by</dt><dd class="text-right font-semibold text-slate-800">{{ $ticket->submitter->name }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Assigned to</dt><dd class="text-right font-semibold text-slate-800">{{ $ticket->assignee?->name ?? 'Unassigned' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Start</dt><dd class="text-right font-semibold text-slate-800">{{ $ticket->start_date?->format('M j, Y') ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Due</dt><dd class="text-right font-semibold text-slate-800">{{ $ticket->due_date?->format('M j, Y') ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Estimate</dt><dd class="text-right font-semibold text-slate-800">{{ $ticket->estimated_hours ? $ticket->estimated_hours.' hrs' : '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Completed</dt><dd class="text-right font-semibold text-slate-800">{{ $ticket->completed_at?->format('M j, Y g:i A') ?? '—' }}</dd></div>
                    </dl>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
