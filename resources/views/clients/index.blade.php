<x-app-layout>
    <x-slot name="header">Clients</x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="card flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">Client management</p>
                <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Organizations</h2>
                <p class="mt-2 max-w-2xl text-slate-600">Create, review, and disable client workspaces before associating products and users.</p>
            </div>
            @can('create', App\Models\Client::class)
                <a href="{{ route('clients.create') }}" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-soft transition hover:bg-indigo-700">New client</a>
            @endcan
        </section>

        <section class="card overflow-hidden p-0">
            @if ($clients->count())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500">
                            <tr>
                                <th class="px-6 py-4">Client</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Products</th>
                                <th class="px-6 py-4">Users</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($clients as $client)
                                <tr class="transition hover:bg-slate-50/80">
                                    <td class="px-6 py-5">
                                        <a href="{{ route('clients.show', $client) }}" class="font-black text-slate-950 hover:text-indigo-700">{{ $client->name }}</a>
                                        <p class="mt-1 line-clamp-2 max-w-xl text-sm text-slate-500">{{ $client->description ?: 'No description added yet.' }}</p>
                                    </td>
                                    <td class="px-6 py-5">
                                        <span @class(['rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide', 'bg-emerald-100 text-emerald-700' => $client->status === 'active', 'bg-slate-200 text-slate-600' => $client->status !== 'active'])>{{ $client->status }}</span>
                                    </td>
                                    <td class="px-6 py-5 text-sm font-bold text-slate-700">{{ $client->softwares_count }}</td>
                                    <td class="px-6 py-5 text-sm font-bold text-slate-700">{{ $client->users_count }}</td>
                                    <td class="px-6 py-5">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('clients.show', $client) }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:border-indigo-200 hover:text-indigo-700">View</a>
                                            @can('update', $client)
                                                <a href="{{ route('clients.edit', $client) }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:border-indigo-200 hover:text-indigo-700">Edit</a>
                                            @endcan
                                            @can('disable', $client)
                                                <form x-data="{ confirmOpen: false }" method="POST" action="{{ route('clients.disable', $client) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="button" @click="confirmOpen = true" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50">Disable</button>
                                                    <div x-show="confirmOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" style="display: none;">
                                                        <div @click.outside="confirmOpen = false" class="w-full max-w-md rounded-3xl bg-white p-6 shadow-soft">
                                                            <h3 class="text-xl font-black text-slate-950">Disable {{ $client->name }}?</h3>
                                                            <p class="mt-3 text-sm leading-6 text-slate-600">This keeps historical data but marks the client inactive. New products cannot be assigned to inactive clients.</p>
                                                            <div class="mt-6 flex justify-end gap-3">
                                                                <button type="button" @click="confirmOpen = false" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600">Cancel</button>
                                                                <button type="submit" class="rounded-2xl bg-rose-600 px-4 py-2 text-sm font-black text-white">Disable client</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-6 py-4">{{ $clients->links() }}</div>
            @else
                <div class="p-12 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-indigo-50 text-2xl">🏢</div>
                    <h3 class="mt-5 text-xl font-black text-slate-950">No clients yet</h3>
                    <p class="mt-2 text-slate-500">Create your first client workspace to start organizing products.</p>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
