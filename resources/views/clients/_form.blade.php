@csrf

<div class="grid gap-5">
    <div>
        <x-input-label for="name" value="Client name" />
        <x-text-input id="name" name="name" type="text" class="mt-2 block w-full" :value="old('name', $client->name ?? '')" required autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description" value="Description" />
        <textarea id="description" name="description" rows="5" class="mt-2 block w-full rounded-2xl border-slate-300 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500" placeholder="Summarize the client, stakeholders, products, or delivery context.">{{ old('description', $client->description ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="status" value="Status" />
        <select id="status" name="status" class="mt-2 block w-full rounded-2xl border-slate-300 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500">
            @foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $client->status ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('status')" class="mt-2" />
    </div>
</div>

<div class="mt-8 flex flex-wrap items-center justify-end gap-3">
    <a href="{{ route('clients.index') }}" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-950">Cancel</a>
    <button type="submit" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-soft transition hover:bg-indigo-700">{{ $buttonText }}</button>
</div>
