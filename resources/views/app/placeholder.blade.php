<x-app-layout>
    <x-slot name="header">{{ $title }}</x-slot>

    <section class="card">
        <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">{{ $title }}</p>
        <h2 class="mt-4 text-3xl font-black tracking-tight text-slate-950">{{ $title }} workspace</h2>
        <p class="mt-4 max-w-2xl text-slate-600">This role-gated area is ready for the next implementation phase. Permissions already control whether this menu item and route are available to the current user.</p>
    </section>
</x-app-layout>
