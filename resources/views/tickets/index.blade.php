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

        @include('tickets.partials.list-view')
        @include('tickets.partials.drawer')
    </div>
</x-app-layout>
