<x-app-layout>
    <x-slot name="header">Edit product</x-slot>

    <section class="card mx-auto max-w-3xl">
        <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">Software/product settings</p>
        <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Edit {{ $software->name }}</h2>
        <p class="mt-2 text-slate-600">Update ownership, description, and visibility for client users.</p>

        <form method="POST" action="{{ route('softwares.update', $software) }}" class="mt-8">
            @method('PUT')
            @include('softwares._form', ['buttonText' => 'Save changes'])
        </form>
    </section>
</x-app-layout>
