<x-app-layout>
    <x-slot name="header">Submit ticket</x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <section class="card">
            <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">Client submission</p>
            <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Create a ticket</h2>
            <p class="mt-2 text-slate-600">Submission date is automatically timestamped when you send this request.</p>
        </section>

        <form method="POST" action="{{ route('tickets.store') }}" class="card space-y-5">
            @csrf
            <div>
                <label for="title" class="text-sm font-black text-slate-700">Ticket name</label>
                <input id="title" name="title" value="{{ old('title') }}" class="mt-2 w-full rounded-2xl border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                <x-input-error :messages="$errors->get('title')" class="mt-2" />
            </div>

            <div>
                <label for="description" class="text-sm font-black text-slate-700">Description</label>
                <textarea id="description" name="description" rows="7" class="mt-2 w-full rounded-2xl border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>{{ old('description') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="software_id" class="text-sm font-black text-slate-700">Software</label>
                    <select id="software_id" name="software_id" class="mt-2 w-full rounded-2xl border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option value="">Select software</option>
                        @foreach ($softwares as $software)
                            <option value="{{ $software->id }}" @selected(old('software_id') == $software->id)>{{ $software->name }} — {{ $software->client->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('software_id')" class="mt-2" />
                </div>

                <div>
                    <label for="urgency" class="text-sm font-black text-slate-700">Urgency level</label>
                    <select id="urgency" name="urgency" class="mt-2 w-full rounded-2xl border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        @foreach (App\Models\Ticket::URGENCIES as $urgency)
                            <option value="{{ $urgency }}" @selected(old('urgency', 'medium') === $urgency)>{{ str($urgency)->headline() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('urgency')" class="mt-2" />
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
                <a href="{{ route('tickets.index') }}" class="rounded-2xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-600">Cancel</a>
                <button type="submit" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-soft hover:bg-indigo-700">Submit ticket</button>
            </div>
        </form>
    </div>
</x-app-layout>
