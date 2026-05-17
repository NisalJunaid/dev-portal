<x-app-layout>
    <x-slot name="header">Sprints</x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="card flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">Sprint cycles</p>
                <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Sprint management</h2>
                <p class="mt-2 max-w-3xl text-slate-600">Start delivery cycles from next sprint feature tickets, review in-progress work, and inspect completed sprint analytics.</p>
            </div>
            @if ($isKielUser)
                <a href="{{ route('sprints.start') }}" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-indigo-700">Start sprint</a>
            @endif
        </section>

        <section class="card">
            <form method="GET" action="{{ route('sprints.index') }}" class="grid gap-4 lg:grid-cols-4">
                @if ($isKielUser)
                    <select name="client_id" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All clients</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(($filters['client_id'] ?? '') == $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                @endif

                <select name="status" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All statuses</option>
                    @foreach (App\Models\Sprint::STATUSES as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>
                    @endforeach
                </select>

                <div class="flex gap-3 lg:col-span-2">
                    <button type="submit" class="rounded-2xl bg-slate-900 px-5 py-2.5 text-sm font-black text-white">Apply filters</button>
                    <a href="{{ route('sprints.index') }}" class="rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-black text-slate-600">Reset</a>
                </div>
            </form>
        </section>

        <section class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Sprint</th>
                            <th class="px-5 py-4">Client / Software</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4">Timing</th>
                            <th class="px-5 py-4">Items</th>
                            <th class="px-5 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($sprints as $sprint)
                            <tr class="align-top">
                                <td class="px-5 py-4">
                                    <a href="{{ route('sprints.show', $sprint) }}" class="font-black text-indigo-700 hover:text-indigo-900">{{ $sprint->name }}</a>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">Cycle #{{ $sprint->sprint_no }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-bold text-slate-950">{{ $sprint->client->name }}</p>
                                    <p class="text-xs font-semibold text-slate-500">{{ $sprint->software?->name ?? 'Multiple software / unassigned' }}</p>
                                </td>
                                <td class="px-5 py-4"><span @class(['rounded-full px-3 py-1 text-xs font-black', 'bg-slate-100 text-slate-600' => $sprint->status === App\Models\Sprint::STATUS_PLANNED, 'bg-indigo-100 text-indigo-700' => $sprint->status === App\Models\Sprint::STATUS_IN_PROGRESS, 'bg-emerald-100 text-emerald-700' => $sprint->status === App\Models\Sprint::STATUS_COMPLETED])>{{ $sprint->formattedStatus() }}</span></td>
                                <td class="px-5 py-4 text-xs font-semibold leading-5 text-slate-500">Start: {{ $sprint->started_at?->format('M j, Y g:i A') ?? '—' }}<br>End: {{ $sprint->ended_at?->format('M j, Y g:i A') ?? '—' }}<br>Duration: {{ $sprint->formattedDuration() }}</td>
                                <td class="px-5 py-4 font-semibold text-slate-600">{{ $sprint->items_count }} tickets</td>
                                <td class="px-5 py-4 text-right"><a href="{{ route('sprints.show', $sprint) }}" class="rounded-2xl border border-slate-200 px-4 py-2 text-xs font-black text-slate-600">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-12 text-center font-semibold text-slate-500">No sprint cycles match the current filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-4">{{ $sprints->links() }}</div>
        </section>
    </div>
</x-app-layout>
