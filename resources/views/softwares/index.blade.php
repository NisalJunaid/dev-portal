<x-app-layout>
    <x-slot name="header">Software</x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="card flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">Software/products</p>
                <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Product catalog</h2>
                <p class="mt-2 max-w-2xl text-slate-600">
                    @if ($canManageSoftware)
                        Manage client products, availability, and product descriptions.
                    @else
                        View enabled products for your client workspace.
                    @endif
                </p>
            </div>
            @can('create', App\Models\Software::class)
                <a href="{{ route('softwares.create') }}" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-soft transition hover:bg-indigo-700">New product</a>
            @endcan
        </section>

        <section class="grid gap-5 md:grid-cols-3">
            <div class="card">
                <p class="text-sm font-bold text-slate-500">Visible products</p>
                <p class="mt-3 text-4xl font-black text-slate-950">{{ $softwares->total() }}</p>
            </div>
            <div class="card">
                <p class="text-sm font-bold text-slate-500">Active clients</p>
                <p class="mt-3 text-4xl font-black text-slate-950">{{ $clients->count() }}</p>
            </div>
            <div class="card">
                <p class="text-sm font-bold text-slate-500">Access</p>
                <p class="mt-3 text-xl font-black text-slate-950">{{ $canManageSoftware ? 'Manager' : 'Client view' }}</p>
            </div>
        </section>

        <section class="card overflow-hidden p-0">
            @if ($softwares->count())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500">
                            <tr>
                                <th class="px-6 py-4">Product</th>
                                <th class="px-6 py-4">Client</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Updated</th>
                                @if ($canManageSoftware)
                                    <th class="px-6 py-4 text-right">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($softwares as $software)
                                <tr class="transition hover:bg-slate-50/80">
                                    <td class="px-6 py-5">
                                        <p class="font-black text-slate-950">{{ $software->name }}</p>
                                        <p class="mt-1 max-w-xl text-sm text-slate-500">{{ $software->description ?: 'No description added yet.' }}</p>
                                    </td>
                                    <td class="px-6 py-5 text-sm font-bold text-slate-700">{{ $software->client->name }}</td>
                                    <td class="px-6 py-5">
                                        <span @class(['rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide', 'bg-emerald-100 text-emerald-700' => $software->is_enabled, 'bg-slate-200 text-slate-600' => ! $software->is_enabled])>{{ $software->is_enabled ? 'Enabled' : 'Disabled' }}</span>
                                    </td>
                                    <td class="px-6 py-5 text-sm text-slate-500">{{ $software->updated_at->diffForHumans() }}</td>
                                    @if ($canManageSoftware)
                                        <td class="px-6 py-5">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="{{ route('softwares.edit', $software) }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:border-indigo-200 hover:text-indigo-700">Edit</a>
                                                <form x-data="{ confirmOpen: false }" method="POST" action="{{ route('softwares.toggle', $software) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="button" @click="confirmOpen = true" @class(['rounded-xl border px-3 py-2 text-xs font-bold', 'border-rose-200 text-rose-600 hover:bg-rose-50' => $software->is_enabled, 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' => ! $software->is_enabled])>{{ $software->is_enabled ? 'Disable' : 'Enable' }}</button>
                                                    <div x-show="confirmOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" style="display: none;">
                                                        <div @click.outside="confirmOpen = false" class="w-full max-w-md rounded-3xl bg-white p-6 shadow-soft">
                                                            <h3 class="text-xl font-black text-slate-950">{{ $software->is_enabled ? 'Disable' : 'Enable' }} {{ $software->name }}?</h3>
                                                            <p class="mt-3 text-sm leading-6 text-slate-600">{{ $software->is_enabled ? 'Client users will no longer see this product.' : 'Client users for this client will be able to see this product.' }}</p>
                                                            <div class="mt-6 flex justify-end gap-3">
                                                                <button type="button" @click="confirmOpen = false" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600">Cancel</button>
                                                                <button type="submit" class="rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-black text-white">Confirm</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-6 py-4">{{ $softwares->links() }}</div>
            @else
                <div class="p-12 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-indigo-50 text-2xl">🧩</div>
                    <h3 class="mt-5 text-xl font-black text-slate-950">No products to show</h3>
                    <p class="mt-2 text-slate-500">{{ $canManageSoftware ? 'Create a product for an active client.' : 'Your client does not have any enabled products yet.' }}</p>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
