<x-app-layout>
    <x-slot name="header">New client</x-slot>

    <section class="card mx-auto max-w-3xl">
        <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">Create client</p>
        <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Add a client workspace</h2>
        <p class="mt-2 text-slate-600">Client records group users, software products, and future intake work.</p>

        <form method="POST" action="{{ route('clients.store') }}" class="mt-8">
            @include('clients._form', ['client' => null, 'buttonText' => 'Create client'])
        </form>
    </section>
</x-app-layout>
