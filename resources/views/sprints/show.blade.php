<x-app-layout>
    <x-slot name="header">{{ $sprint->name }}</x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="card flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">Sprint cycle #{{ $sprint->sprint_no }}</p>
                <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">{{ $sprint->name }}</h2>
                <p class="mt-2 text-slate-600">{{ $sprint->client->name }} · {{ $sprint->software?->name ?? 'Multiple software / unassigned' }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('sprints.index') }}" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-600">Back to sprints</a>
                @if ($isKielUser && $sprint->status === App\Models\Sprint::STATUS_IN_PROGRESS)
                    <form method="POST" action="{{ route('sprints.complete', $sprint) }}">
                        @csrf
                        <button type="submit" class="rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-emerald-700">Complete sprint</button>
                    </form>
                @endif
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="card"><p class="text-xs font-black uppercase tracking-wide text-slate-400">Client</p><p class="mt-2 text-lg font-black text-slate-950">{{ $sprint->client->name }}</p></div>
            <div class="card"><p class="text-xs font-black uppercase tracking-wide text-slate-400">Software</p><p class="mt-2 text-lg font-black text-slate-950">{{ $sprint->software?->name ?? 'Multiple / —' }}</p></div>
            <div class="card"><p class="text-xs font-black uppercase tracking-wide text-slate-400">Start time</p><p class="mt-2 text-lg font-black text-slate-950">{{ $sprint->started_at?->format('M j, Y g:i A') ?? '—' }}</p></div>
            <div class="card"><p class="text-xs font-black uppercase tracking-wide text-slate-400">End time</p><p class="mt-2 text-lg font-black text-slate-950">{{ $sprint->ended_at?->format('M j, Y g:i A') ?? '—' }}</p></div>
            <div class="card"><p class="text-xs font-black uppercase tracking-wide text-slate-400">Duration</p><p class="mt-2 text-lg font-black text-slate-950">{{ $sprint->formattedDuration() }}</p></div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="card">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-black text-slate-950">Completed tickets</h3>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-700">{{ $completedTickets->count() }}</span>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse ($completedTickets as $ticket)
                        <a href="{{ route('features.show', $ticket) }}" class="block rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4 transition hover:border-emerald-200">
                            <p class="text-sm font-black text-emerald-800">{{ $ticket->ticket_no }}</p>
                            <p class="mt-1 font-bold text-slate-950">{{ $ticket->title }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">Completed {{ $ticket->actual_completed_at?->format('M j, Y g:i A') ?? $ticket->completed_at?->format('M j, Y g:i A') ?? '—' }}</p>
                        </a>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-200 p-5 text-sm font-semibold text-slate-500">No sprint items are completed yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-black text-slate-950">Incomplete tickets for Kiel review</h3>
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-700">{{ $incompleteTickets->count() }}</span>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse ($incompleteTickets as $ticket)
                        <a href="{{ route('features.show', $ticket) }}" class="block rounded-2xl border border-amber-100 bg-amber-50/60 p-4 transition hover:border-amber-200">
                            <p class="text-sm font-black text-amber-800">{{ $ticket->ticket_no }} · {{ $ticket->formattedStatus() }}</p>
                            <p class="mt-1 font-bold text-slate-950">{{ $ticket->title }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">Assignee: {{ $ticket->assignee?->name ?? 'Unassigned' }}</p>
                        </a>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-200 p-5 text-sm font-semibold text-slate-500">No incomplete items remain for review.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="card">
            <h3 class="text-xl font-black text-slate-950">Activity history</h3>
            <div class="mt-5 grid gap-6 xl:grid-cols-2">
                <div>
                    <p class="text-sm font-black uppercase tracking-wide text-slate-400">Sprint activity</p>
                    <div class="mt-3 space-y-3">
                        @forelse ($sprint->activities as $activity)
                            <div class="rounded-2xl border border-slate-100 bg-white p-4">
                                <p class="text-sm font-black text-slate-950">{{ str($activity->action)->headline() }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $activity->description }}</p>
                                <p class="mt-2 text-xs font-semibold text-slate-400">{{ $activity->created_at->format('M j, Y g:i A') }} · {{ $activity->user?->name ?? 'System' }}</p>
                            </div>
                        @empty
                            <p class="rounded-2xl border border-dashed border-slate-200 p-5 text-sm font-semibold text-slate-500">No sprint activity recorded.</p>
                        @endforelse
                    </div>
                </div>
                <div>
                    <p class="text-sm font-black uppercase tracking-wide text-slate-400">Ticket activity in this sprint</p>
                    <div class="mt-3 space-y-3">
                        @forelse ($ticketActivities as $activity)
                            <div class="rounded-2xl border border-slate-100 bg-white p-4">
                                <p class="text-sm font-black text-slate-950">{{ $activity->ticket?->ticket_no }} · {{ str($activity->action)->headline() }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $activity->description }}</p>
                                <p class="mt-2 text-xs font-semibold text-slate-400">{{ $activity->created_at->format('M j, Y g:i A') }} · {{ $activity->user?->name ?? 'System' }}</p>
                            </div>
                        @empty
                            <p class="rounded-2xl border border-dashed border-slate-200 p-5 text-sm font-semibold text-slate-500">No ticket activity recorded for sprint items.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
