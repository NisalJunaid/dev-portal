<x-app-layout>
    <x-slot name="header">Reports</x-slot>
    <div class="space-y-6">
        <section class="card flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">Reporting center</p>
                <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Dashboards and exports</h2>
                <p class="mt-2 max-w-3xl text-slate-600">Filter delivery data by client, software, user, ticket type, status, date range, and sprint. Client users only see their organization and cannot export internal reports.</p>
            </div>
        </section>
        @include('reports.partials.filters', ['action' => route('reports.index')])
        @include('reports.partials.cards', ['cards' => $cards])
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('reports.time', request()->query()) }}" class="card block"><h3 class="font-black text-slate-950">Time tracking report</h3><p class="mt-2 text-sm text-slate-600">Tracked hours by ticket, user, client, and software.</p></a>
            <a href="{{ route('reports.tickets', request()->query()) }}" class="card block"><h3 class="font-black text-slate-950">Tickets report</h3><p class="mt-2 text-sm text-slate-600">Ticket status, type, ownership, and due dates.</p></a>
            <a href="{{ route('reports.sprints', request()->query()) }}" class="card block"><h3 class="font-black text-slate-950">Sprint summary report</h3><p class="mt-2 text-sm text-slate-600">Sprint cycle progress and timing summaries.</p></a>
            <a href="{{ route('reports.blocked', request()->query()) }}" class="card block"><h3 class="font-black text-slate-950">Blocked time report</h3><p class="mt-2 text-sm text-slate-600">Active and resolved block durations.</p></a>
        </section>
    </div>
</x-app-layout>
