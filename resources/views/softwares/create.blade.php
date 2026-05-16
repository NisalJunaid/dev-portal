<x-app-layout>
    <x-slot name="header">New product</x-slot>

    <section class="card mx-auto max-w-3xl">
        <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">Create software/product</p>
        <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Add a product</h2>
        <p class="mt-2 text-slate-600">Products belong to clients and can be enabled or disabled for client users.</p>

        <form method="POST" action="{{ route('softwares.store') }}" class="mt-8">
            @include('softwares._form', ['software' => null, 'buttonText' => 'Create product'])
        </form>
    </section>
</x-app-layout>
