<x-app-layout>
    <x-slot name="header">Features</x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="card flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">Feature workflow</p>
                <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Approved feature requests</h2>
                <p class="mt-2 max-w-3xl text-slate-600">Review feature requests, recommend approved client work for the next planning cycle, and track delivery status.</p>
            </div>
            @if ($isKielUser)
                <a href="{{ route('features.recommended') }}" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-indigo-700">Review recommendations</a>
            @endif
        </section>

        <section class="card">
            <form method="GET" action="{{ route('features.index') }}" class="grid gap-4 lg:grid-cols-6">
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search features" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 lg:col-span-2">

                @if ($isKielUser)
                    <select name="client_id" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All clients</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(($filters['client_id'] ?? '') == $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                @endif

                <select name="software_id" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All software</option>
                    @foreach ($softwares as $software)
                        <option value="{{ $software->id }}" @selected(($filters['software_id'] ?? '') == $software->id)>{{ $software->name }}@if($isKielUser) — {{ $software->client->name }}@endif</option>
                    @endforeach
                </select>

                <select name="urgency" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All urgency</option>
                    @foreach (App\Models\Ticket::URGENCIES as $urgency)
                        <option value="{{ $urgency }}" @selected(($filters['urgency'] ?? '') === $urgency)>{{ str($urgency)->headline() }}</option>
                    @endforeach
                </select>

                <select name="status" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All statuses</option>
                    @foreach (App\Models\Ticket::FEATURE_STATUSES as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>
                    @endforeach
                </select>

                @if ($isKielUser)
                    <select name="assigned_to" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All assignees</option>
                        @foreach ($assignees as $assignee)
                            <option value="{{ $assignee->id }}" @selected(($filters['assigned_to'] ?? '') == $assignee->id)>{{ $assignee->name }}</option>
                        @endforeach
                    </select>
                @endif

                <div class="flex gap-3 lg:col-span-6">
                    <button type="submit" class="rounded-2xl bg-slate-900 px-5 py-2.5 text-sm font-black text-white">Apply filters</button>
                    <a href="{{ route('features.index') }}" class="rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-black text-slate-600">Reset</a>
                </div>
            </form>
        </section>

        <section class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Feature</th>
                            <th class="px-5 py-4">Client / Software</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4">Urgency</th>
                            <th class="px-5 py-4">Assignee</th>
                            <th class="px-5 py-4">Dates</th>
                            <th class="px-5 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($features as $feature)
                            <tr class="align-top">
                                <td class="px-5 py-4">
                                    <a href="{{ route('features.show', $feature) }}" class="font-black text-indigo-700 hover:text-indigo-900">{{ $feature->ticket_no }}</a>
                                    <p class="mt-1 font-bold text-slate-950">{{ $feature->title }}</p>
                                    <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{{ $feature->description }}</p>
                                </td>
                                <td class="px-5 py-4 font-semibold text-slate-600"><span class="block text-slate-900">{{ $feature->client->name }}</span>{{ $feature->software->name }}</td>
                                <td class="px-5 py-4"><span class="badge badge-status">{{ $feature->formattedStatus() }}</span></td>
                                <td class="px-5 py-4"><span @class(['badge', 'badge-urgency-critical' => $feature->urgency === 'critical', 'badge-urgency-high' => $feature->urgency === 'high', 'badge-urgency-medium' => $feature->urgency === 'medium', 'badge-urgency-low' => $feature->urgency === 'low'])>{{ $feature->formattedUrgency() }}</span></td>
                                <td class="px-5 py-4 font-semibold text-slate-600">{{ $feature->assignee?->name ?? 'Unassigned' }}</td>
                                <td class="px-5 py-4 text-xs font-semibold leading-5 text-slate-500">Start: {{ $feature->start_date?->format('M j, Y') ?? '—' }}<br>Due: {{ $feature->due_date?->format('M j, Y') ?? '—' }}</td>
                                <td class="px-5 py-4 text-right">
                                    @if ($feature->status === App\Models\Ticket::STATUS_FEATURE_APPROVED)
                                        <form method="POST" action="{{ route('features.recommend', $feature) }}" data-ajax-action data-confirm-title="Recommend feature?" data-confirm-message="This will move the feature into the recommended planning queue." data-confirm-label="Recommend" data-replace-with-status>
                                            @csrf
                                            <button type="submit" class="rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-2 text-xs font-black text-indigo-700">Recommend</button>
                                        </form>
                                    @else
                                        <a href="{{ route('features.show', $feature) }}" class="rounded-2xl border border-slate-200 px-4 py-2 text-xs font-black text-slate-600">Open</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-12 text-center font-semibold text-slate-500">No feature requests match the current filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-4">{{ $features->links() }}</div>
        </section>
    </div>
</x-app-layout>
