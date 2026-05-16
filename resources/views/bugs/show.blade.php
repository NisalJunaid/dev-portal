<x-app-layout>
    <x-slot name="header">{{ $ticket->ticket_no }} bug</x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="card flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.25em] text-rose-600">Bug fix detail</p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <h2 class="text-3xl font-black tracking-tight text-slate-950">{{ $ticket->title }}</h2>
                    <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-black uppercase tracking-wide text-rose-700">{{ $ticket->formattedStatus() }}</span>
                </div>
                <p class="mt-2 text-sm font-bold text-slate-500">{{ $ticket->ticket_no }} · {{ $ticket->client->name }} · {{ $ticket->software->name }}</p>
            </div>
            <a href="{{ route('bugs.index', ['view' => 'kanban']) }}" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 shadow-sm transition hover:border-rose-200 hover:text-rose-700">Back to bug board</a>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <main class="space-y-6">
                <section class="card">
                    <div class="flex flex-wrap gap-3">
                        <span @class(['rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide', 'bg-rose-100 text-rose-700' => $ticket->urgency === 'critical', 'bg-orange-100 text-orange-700' => $ticket->urgency === 'high', 'bg-amber-100 text-amber-700' => $ticket->urgency === 'medium', 'bg-emerald-100 text-emerald-700' => $ticket->urgency === 'low'])>{{ $ticket->formattedUrgency() }}</span>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase tracking-wide text-slate-700">Bug</span>
                    </div>
                    <p class="mt-6 whitespace-pre-line leading-7 text-slate-700">{{ $ticket->description }}</p>
                </section>

                <section class="card">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-bold uppercase tracking-[0.2em] text-slate-400">Discussion</p>
                            <h3 class="mt-2 text-xl font-black text-slate-950">Comments</h3>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" class="mb-6 space-y-3">
                        @csrf
                        <textarea name="comment" rows="4" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500" placeholder="Add a bug note or client-facing update"></textarea>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            @if ($isKielUser)
                                <label class="flex items-center gap-2 text-sm font-bold text-slate-600">
                                    <input type="checkbox" name="is_internal" value="1" class="rounded border-slate-300 text-rose-600 shadow-sm focus:ring-rose-500">
                                    Internal Kiel note
                                </label>
                            @endif
                            <button type="submit" class="rounded-2xl bg-slate-900 px-5 py-2.5 text-sm font-black text-white">Add comment</button>
                        </div>
                    </form>

                    @include('tickets.partials.comments', ['comments' => $comments, 'ticket' => $ticket, 'isKielUser' => $isKielUser])
                </section>

                <section class="card">
                    <p class="text-sm font-bold uppercase tracking-[0.2em] text-slate-400">Audit trail</p>
                    <h3 class="mt-2 text-xl font-black text-slate-950">Activity</h3>
                    <div class="mt-6">
                        @include('tickets.partials.activity-timeline', ['activities' => $ticket->activities])
                    </div>
                </section>
            </main>

            <aside class="space-y-6">
                <section class="card">
                    <p class="text-sm font-bold uppercase tracking-[0.2em] text-slate-400">Bug workflow</p>
                    <h3 class="mt-2 text-xl font-black text-slate-950">Status controls</h3>
                    @if ($canUpdateBugs)
                        <div class="mt-5 grid gap-3">
                            <form method="POST" action="{{ route('bugs.pending', $ticket) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-700 shadow-sm transition hover:border-indigo-200 hover:text-indigo-700">Move to pending</button>
                            </form>
                            <form method="POST" action="{{ route('bugs.block', $ticket) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-2xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-black text-amber-800 shadow-sm transition hover:bg-amber-100">Mark blocked</button>
                            </form>
                            <form method="POST" action="{{ route('bugs.complete', $ticket) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-black text-emerald-800 shadow-sm transition hover:bg-emerald-100">Complete bug</button>
                            </form>
                        </div>
                    @else
                        <p class="mt-4 rounded-2xl bg-amber-50 p-4 text-sm font-bold leading-6 text-amber-800">You can view this bug because it belongs to your client workspace. Status updates are disabled unless Kiel grants update permission.</p>
                    @endif
                </section>

                <section class="card space-y-4 text-sm">
                    <p class="text-sm font-bold uppercase tracking-[0.2em] text-slate-400">Metadata</p>
                    <dl class="space-y-3">
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Submitted by</dt><dd class="text-right font-semibold text-slate-800">{{ $ticket->submitter->name }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Assigned to</dt><dd class="text-right font-semibold text-slate-800">{{ $ticket->assignee?->name ?? 'Unassigned' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Submitted</dt><dd class="text-right font-semibold text-slate-800">{{ $ticket->submitted_at->format('M j, Y') }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Completed</dt><dd class="text-right font-semibold text-slate-800">{{ $ticket->completed_at?->format('M j, Y g:i A') ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Actual completed</dt><dd class="text-right font-semibold text-slate-800">{{ $ticket->actual_completed_at?->format('M j, Y g:i A') ?? '—' }}</dd></div>
                    </dl>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
