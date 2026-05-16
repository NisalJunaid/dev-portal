<x-app-layout>
    <x-slot name="header">{{ $client->name }}</x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="card flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">Client profile</p>
                    <span @class(['rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide', 'bg-emerald-100 text-emerald-700' => $client->status === 'active', 'bg-slate-200 text-slate-600' => $client->status !== 'active'])>{{ $client->status }}</span>
                </div>
                <h2 class="mt-4 text-4xl font-black tracking-tight text-slate-950">{{ $client->name }}</h2>
                <p class="mt-4 max-w-3xl text-slate-600">{{ $client->description ?: 'No client description has been added yet.' }}</p>
            </div>
            @can('update', $client)
                <a href="{{ route('clients.edit', $client) }}" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-soft transition hover:bg-indigo-700">Edit client</a>
            @endcan
        </section>

        <div class="grid gap-6 md:grid-cols-3">
            <section class="card">
                <p class="text-sm font-bold text-slate-500">Products</p>
                <p class="mt-3 text-4xl font-black text-slate-950">{{ $client->softwares_count }}</p>
            </section>
            <section class="card">
                <p class="text-sm font-bold text-slate-500">Users</p>
                <p class="mt-3 text-4xl font-black text-slate-950">{{ $client->users_count }}</p>
            </section>
            <section class="card">
                <p class="text-sm font-bold text-slate-500">Created</p>
                <p class="mt-3 text-xl font-black text-slate-950">{{ $client->created_at->format('M j, Y') }}</p>
            </section>
        </div>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-xl font-black text-slate-950">Software/products</h3>
                    @can('create', App\Models\Software::class)
                        <a href="{{ route('softwares.create') }}" class="text-sm font-bold text-indigo-700 hover:text-indigo-900">Add product</a>
                    @endcan
                </div>
                <div class="mt-5 space-y-3">
                    @forelse ($client->softwares as $software)
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-black text-slate-950">{{ $software->name }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $software->description ?: 'No description.' }}</p>
                                </div>
                                <span @class(['rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide', 'bg-emerald-100 text-emerald-700' => $software->is_enabled, 'bg-slate-200 text-slate-600' => ! $software->is_enabled])>{{ $software->is_enabled ? 'Enabled' : 'Disabled' }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl bg-slate-50 p-6 text-center text-sm font-semibold text-slate-500">No software products have been added for this client.</div>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <h3 class="text-xl font-black text-slate-950">Users</h3>
                <div class="mt-5 space-y-3">
                    @forelse ($client->users as $user)
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <p class="font-black text-slate-950">{{ $user->name }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $user->email }}</p>
                            <p class="mt-2 text-xs font-bold uppercase tracking-widest text-indigo-600">{{ $user->roles->pluck('name')->map(fn ($role) => str($role)->headline())->join(', ') }}</p>
                        </div>
                    @empty
                        <div class="rounded-2xl bg-slate-50 p-6 text-center text-sm font-semibold text-slate-500">No users are attached to this client.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
