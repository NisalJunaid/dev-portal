<x-app-layout>
    <x-slot name="header">Global backlog</x-slot>

    <div class="space-y-6">
        <section class="card flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">Kiel review queue</p>
                <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Unclassified backlog</h2>
                <p class="mt-2 max-w-2xl text-slate-600">Classify incoming client requests as bugs or features, reject unsuitable tickets, and assign ownership.</p>
            </div>
            <a href="{{ route('tickets.index') }}" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 shadow-sm transition hover:border-indigo-200 hover:text-indigo-700">All tickets</a>
        </section>

        <section class="grid gap-4">
            @forelse ($tickets as $ticket)
                <a href="{{ route('tickets.show', $ticket) }}" class="card block transition hover:-translate-y-0.5 hover:border-indigo-100 hover:shadow-soft">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="font-black text-indigo-700">{{ $ticket->ticket_no }}</span>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase tracking-wide text-slate-700">{{ $ticket->formattedStatus() }}</span>
                                <span @class(['rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide', 'bg-rose-100 text-rose-700' => $ticket->urgency === 'critical', 'bg-orange-100 text-orange-700' => $ticket->urgency === 'high', 'bg-amber-100 text-amber-700' => $ticket->urgency === 'medium', 'bg-emerald-100 text-emerald-700' => $ticket->urgency === 'low'])>{{ $ticket->formattedUrgency() }}</span>
                            </div>
                            <h3 class="mt-3 text-xl font-black text-slate-950">{{ $ticket->title }}</h3>
                            <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">{{ $ticket->description }}</p>
                        </div>
                        <div class="text-sm text-slate-500 lg:text-right">
                            <p class="font-bold text-slate-800">{{ $ticket->client->name }}</p>
                            <p>{{ $ticket->software->name }}</p>
                            <p class="mt-2">{{ $ticket->submitted_at->diffForHumans() }}</p>
                        </div>
                    </div>
                </a>
            @empty
                <div class="card p-12 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-emerald-50 text-2xl">✅</div>
                    <h3 class="mt-5 text-xl font-black text-slate-950">Backlog is clear</h3>
                    <p class="mt-2 text-slate-500">New client submissions will appear here automatically.</p>
                </div>
            @endforelse
        </section>

        {{ $tickets->links() }}
    </div>
</x-app-layout>
