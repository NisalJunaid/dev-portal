<x-app-layout>
    <x-slot name="header">Recommended features</x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="card flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">Kiel review</p>
                <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Recommended next-cycle features</h2>
                <p class="mt-2 max-w-2xl text-slate-600">Review client recommendations, promote accepted work to the next sprint, or defer requests back to the approved feature list.</p>
            </div>
            <a href="{{ route('features.index') }}" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700">All features</a>
        </section>

        <div class="grid gap-5 xl:grid-cols-2">
            @forelse ($features as $feature)
                <article class="card">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <a href="{{ route('features.show', $feature) }}" class="font-black text-indigo-700">{{ $feature->ticket_no }}</a>
                            <h3 class="mt-2 text-xl font-black text-slate-950">{{ $feature->title }}</h3>
                            <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600">{{ $feature->description }}</p>
                        </div>
                        <span @class(['rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide', 'bg-rose-100 text-rose-700' => $feature->urgency === 'critical', 'bg-orange-100 text-orange-700' => $feature->urgency === 'high', 'bg-amber-100 text-amber-700' => $feature->urgency === 'medium', 'bg-emerald-100 text-emerald-700' => $feature->urgency === 'low'])>{{ $feature->formattedUrgency() }}</span>
                    </div>
                    <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="font-black text-slate-400">Client</dt><dd class="font-semibold text-slate-800">{{ $feature->client->name }}</dd></div>
                        <div><dt class="font-black text-slate-400">Software</dt><dd class="font-semibold text-slate-800">{{ $feature->software->name }}</dd></div>
                        <div><dt class="font-black text-slate-400">Assignee</dt><dd class="font-semibold text-slate-800">{{ $feature->assignee?->name ?? 'Unassigned' }}</dd></div>
                        <div><dt class="font-black text-slate-400">Estimate</dt><dd class="font-semibold text-slate-800">{{ $feature->estimated_hours ? $feature->estimated_hours.' hrs' : '—' }}</dd></div>
                    </dl>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <form method="POST" action="{{ route('features.approve-next-sprint', $feature) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-2xl bg-indigo-600 px-4 py-2.5 text-sm font-black text-white">Move to next sprint</button>
                        </form>
                        <form method="POST" action="{{ route('features.defer', $feature) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-2xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-black text-amber-800">Defer</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="card xl:col-span-2 text-center font-semibold text-slate-500">No recommended features are waiting for review.</div>
            @endforelse
        </div>

        {{ $features->links() }}
    </div>
</x-app-layout>
