<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    <div class="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
        <section class="card">
            <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">{{ $workspaceLabel }}</p>
            <h2 class="mt-4 text-4xl font-black tracking-tight text-slate-950">Delivery command center</h2>
            <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">Track tickets, bugs, feature work, sprints, timelines, reports, clients, software, and settings from a role-aware workspace.</p>
            <div class="mt-8 grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl bg-indigo-50 p-4">
                    <p class="text-3xl font-black text-indigo-700">{{ $clientCount }}</p>
                    <p class="mt-1 text-sm font-semibold text-indigo-900">Accessible clients</p>
                </div>
                <div class="rounded-2xl bg-emerald-50 p-4">
                    <p class="text-3xl font-black text-emerald-700">0</p>
                    <p class="mt-1 text-sm font-semibold text-emerald-900">Open tickets</p>
                </div>
                <div class="rounded-2xl bg-amber-50 p-4">
                    <p class="text-3xl font-black text-amber-700">0</p>
                    <p class="mt-1 text-sm font-semibold text-amber-900">Sprint risks</p>
                </div>
            </div>
        </section>

        <section class="card">
            <h3 class="text-lg font-black text-slate-950">Role-based access</h3>
            <ul class="mt-5 space-y-3 text-sm text-slate-600">
                <li class="rounded-2xl bg-slate-50 p-4"><strong>Super admins</strong> can access every area and organization.</li>
                <li class="rounded-2xl bg-slate-50 p-4"><strong>Kiel users</strong> can access global backend views.</li>
                <li class="rounded-2xl bg-slate-50 p-4"><strong>Client users</strong> only see their organization's data.</li>
            </ul>
        </section>
    </div>
</x-app-layout>
