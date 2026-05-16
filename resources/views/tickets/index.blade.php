<x-app-layout>
    <x-slot name="header">Tickets</x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="card flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">Centralized intake</p>
                <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Tickets</h2>
                <p class="mt-2 max-w-2xl text-slate-600">Submit, review, classify, reject, assign, and discuss all product work from one intake stream.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                @if ($isKielUser)
                    <a href="{{ route('tickets.backlog') }}" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 shadow-sm transition hover:border-indigo-200 hover:text-indigo-700">Global backlog</a>
                @endif
                <a href="{{ route('tickets.create') }}" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-soft transition hover:bg-indigo-700">New ticket</a>
            </div>
        </section>

        <section class="card overflow-hidden p-0">
            @if ($tickets->count())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500">
                            <tr>
                                <th class="px-6 py-4">Ticket</th>
                                <th class="px-6 py-4">Client / Software</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Urgency</th>
                                <th class="px-6 py-4">Assigned</th>
                                <th class="px-6 py-4">Submitted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($tickets as $ticket)
                                <tr class="transition hover:bg-slate-50/80">
                                    <td class="px-6 py-5">
                                        <a href="{{ route('tickets.show', $ticket) }}" class="font-black text-slate-950 hover:text-indigo-700">{{ $ticket->ticket_no }}</a>
                                        <p class="mt-1 text-sm font-semibold text-slate-700">{{ $ticket->title }}</p>
                                    </td>
                                    <td class="px-6 py-5 text-sm text-slate-600">
                                        <p class="font-bold text-slate-800">{{ $ticket->client->name }}</p>
                                        <p>{{ $ticket->software->name }}</p>
                                    </td>
                                    <td class="px-6 py-5"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase tracking-wide text-slate-700">{{ $ticket->formattedStatus() }}</span></td>
                                    <td class="px-6 py-5"><span @class(['rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide', 'bg-rose-100 text-rose-700' => $ticket->urgency === 'critical', 'bg-orange-100 text-orange-700' => $ticket->urgency === 'high', 'bg-amber-100 text-amber-700' => $ticket->urgency === 'medium', 'bg-emerald-100 text-emerald-700' => $ticket->urgency === 'low'])>{{ $ticket->formattedUrgency() }}</span></td>
                                    <td class="px-6 py-5 text-sm font-bold text-slate-600">{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
                                    <td class="px-6 py-5 text-sm text-slate-500">{{ $ticket->submitted_at->format('M j, Y g:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-6 py-4">{{ $tickets->links() }}</div>
            @else
                <div class="p-12 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-indigo-50 text-2xl">🎫</div>
                    <h3 class="mt-5 text-xl font-black text-slate-950">No tickets yet</h3>
                    <p class="mt-2 text-slate-500">Submit the first ticket to start the intake flow.</p>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
