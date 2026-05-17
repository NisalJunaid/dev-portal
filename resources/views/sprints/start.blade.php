<x-app-layout>
    <x-slot name="header">Start Sprint</x-slot>

    <div class="space-y-6">
        <section class="card">
            <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">New sprint cycle</p>
            <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Select a client to start their next sprint</h2>
            <p class="mt-2 max-w-3xl text-slate-600">The system will pull every feature ticket currently marked as next sprint for that client, create the next numbered sprint cycle, and move those tickets into progress.</p>
        </section>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-800">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="card">
            <form method="POST" action="{{ route('sprints.start.store') }}" class="space-y-5">
                @csrf
                <div>
                    <label for="client_id" class="block text-sm font-black text-slate-700">Client</label>
                    <select id="client_id" name="client_id" required class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Choose a client</option>
                        @foreach ($clients as $client)
                            @php($activeSprint = $activeSprints->get($client->id))
                            <option value="{{ $client->id }}" @selected(old('client_id') == $client->id) @disabled($client->next_sprint_features_count === 0 || $activeSprint)>
                                {{ $client->name }} — {{ $client->next_sprint_features_count }} ready {{ str('feature')->plural($client->next_sprint_features_count) }}{{ $activeSprint ? ' — active sprint already in progress' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs font-semibold text-slate-500">Clients can keep recommending features while an active sprint is running; those items stay in the next sprint queue until the next cycle starts.</p>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-indigo-700">Start sprint cycle</button>
                    <a href="{{ route('sprints.index') }}" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-600">Cancel</a>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
