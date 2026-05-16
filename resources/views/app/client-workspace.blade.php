<x-app-layout>
    <x-slot name="header">{{ $client->name }}</x-slot>

    <section class="card">
        <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">Client workspace</p>
        <h2 class="mt-4 text-3xl font-black tracking-tight text-slate-950">{{ $client->name }}</h2>
        <p class="mt-4 text-slate-600">Client-scoped users may only load this page for their assigned organization.</p>
    </section>
</x-app-layout>
