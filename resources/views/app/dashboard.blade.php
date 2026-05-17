<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    @php
        $tones = [
            'indigo' => 'bg-indigo-50 text-indigo-700 text-indigo-900',
            'rose' => 'bg-rose-50 text-rose-700 text-rose-900',
            'emerald' => 'bg-emerald-50 text-emerald-700 text-emerald-900',
            'amber' => 'bg-amber-50 text-amber-700 text-amber-900',
            'sky' => 'bg-sky-50 text-sky-700 text-sky-900',
            'red' => 'bg-red-50 text-red-700 text-red-900',
            'violet' => 'bg-violet-50 text-violet-700 text-violet-900',
        ];
    @endphp

    <div class="space-y-6">
        <section class="card">
            <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">{{ $workspaceLabel }}</p>
            <h2 class="mt-4 text-4xl font-black tracking-tight text-slate-950">{{ $mode === 'kiel' ? 'Delivery command center' : 'Client delivery dashboard' }}</h2>
            <p class="mt-4 max-w-3xl text-base leading-7 text-slate-600">
                {{ $mode === 'kiel' ? 'Monitor every ticket, sprint, blockage, and time-tracking signal across the Kiel delivery workspace.' : 'Review your submitted work, approved and recommended features, sprint progress, blockers, and recent completions for your organization.' }}
            </p>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($summaryCards as $card)
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
                    <p class="text-sm font-black uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-3 text-4xl font-black {{ explode(' ', $tones[$card['tone']] ?? $tones['indigo'])[1] }}">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </section>

        @if ($mode === 'kiel')
            <div class="grid gap-6 xl:grid-cols-2">
                <section class="card">
                    <h3 class="text-lg font-black text-slate-950">Tickets by client</h3>
                    <div class="mt-5 space-y-3">
                        @forelse ($ticketsByClient as $client)
                            <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-4">
                                <span class="font-bold text-slate-700">{{ $client->name }}</span>
                                <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-black text-indigo-700">{{ $client->tickets_count }} tickets</span>
                            </div>
                        @empty
                            <p class="rounded-2xl bg-slate-50 p-6 text-center font-semibold text-slate-500">No client ticket data yet.</p>
                        @endforelse
                    </div>
                </section>

                <section class="card">
                    <h3 class="text-lg font-black text-slate-950">Tickets by software</h3>
                    <div class="mt-5 space-y-3">
                        @forelse ($ticketsBySoftware as $software)
                            <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-4">
                                <span class="font-bold text-slate-700">{{ $software->name }}</span>
                                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-700">{{ $software->tickets_count }} tickets</span>
                            </div>
                        @empty
                            <p class="rounded-2xl bg-slate-50 p-6 text-center font-semibold text-slate-500">No software ticket data yet.</p>
                        @endforelse
                    </div>
                </section>
            </div>

            <section class="card">
                <h3 class="text-lg font-black text-slate-950">Recent activity</h3>
                <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Activity</th><th class="px-4 py-3">Ticket</th><th class="px-4 py-3">Client</th><th class="px-4 py-3">When</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($recentActivities as $activity)
                                <tr><td class="px-4 py-3 font-semibold text-slate-700">{{ $activity->description }}</td><td class="px-4 py-3"><a href="{{ route('tickets.show', $activity->ticket) }}" class="font-black text-indigo-700">{{ $activity->ticket?->ticket_no }}</a></td><td class="px-4 py-3 text-slate-600">{{ $activity->ticket?->client?->name ?? '—' }}</td><td class="px-4 py-3 text-xs font-semibold text-slate-500">{{ $activity->created_at->diffForHumans() }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-10 text-center font-semibold text-slate-500">No recent activity yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <div class="grid gap-6 xl:grid-cols-[1fr_1fr]">
                <section class="card">
                    <h3 class="text-lg font-black text-slate-950">Current sprint progress</h3>
                    @if ($currentSprint)
                        @php $progress = $currentSprint->items_count > 0 ? round(($currentSprint->completed_items_count / $currentSprint->items_count) * 100) : 0; @endphp
                        <p class="mt-4 font-bold text-slate-700">{{ $currentSprint->name }}</p>
                        <div class="mt-4 h-4 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-indigo-600" style="width: {{ $progress }}%"></div></div>
                        <p class="mt-3 text-sm font-semibold text-slate-500">{{ $currentSprint->completed_items_count }} of {{ $currentSprint->items_count }} items completed ({{ $progress }}%).</p>
                    @else
                        <p class="mt-5 rounded-2xl bg-slate-50 p-6 text-center font-semibold text-slate-500">No active sprint is currently in progress.</p>
                    @endif
                </section>

                <section class="card">
                    <h3 class="text-lg font-black text-slate-950">Blocked tickets requiring attention</h3>
                    <div class="mt-5 space-y-3">
                        @forelse ($blockedTickets as $ticket)
                            <a href="{{ route('tickets.show', $ticket) }}" class="block rounded-2xl bg-amber-50 p-4 font-bold text-amber-900">{{ $ticket->ticket_no }} — {{ $ticket->title }}<span class="mt-1 block text-xs font-semibold text-amber-700">{{ $ticket->activeBlock?->reason ?? 'Blocked' }}</span></a>
                        @empty
                            <p class="rounded-2xl bg-slate-50 p-6 text-center font-semibold text-slate-500">No blocked tickets need attention.</p>
                        @endforelse
                    </div>
                </section>
            </div>

            <section class="card">
                <h3 class="text-lg font-black text-slate-950">Recently completed items</h3>
                <div class="mt-5 grid gap-3 md:grid-cols-2">
                    @forelse ($recentlyCompleted as $ticket)
                        <a href="{{ route('tickets.show', $ticket) }}" class="rounded-2xl bg-emerald-50 p-4 font-bold text-emerald-900">{{ $ticket->ticket_no }} — {{ $ticket->title }}<span class="mt-1 block text-xs font-semibold text-emerald-700">{{ $ticket->software?->name ?? 'No software assigned' }}</span></a>
                    @empty
                        <p class="rounded-2xl bg-slate-50 p-6 text-center font-semibold text-slate-500 md:col-span-2">No completed items yet.</p>
                    @endforelse
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
