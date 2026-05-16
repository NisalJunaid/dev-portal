@csrf

<div class="grid gap-5">
    <div>
        <x-input-label for="client_id" value="Client" />
        <select id="client_id" name="client_id" class="mt-2 block w-full rounded-2xl border-slate-300 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500" required>
            <option value="">Select a client</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected((int) old('client_id', $software->client_id ?? request('client_id')) === $client->id)>{{ $client->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="name" value="Software/product name" />
        <x-text-input id="name" name="name" type="text" class="mt-2 block w-full" :value="old('name', $software->name ?? '')" required autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description" value="Description" />
        <textarea id="description" name="description" rows="5" class="mt-2 block w-full rounded-2xl border-slate-300 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500" placeholder="What does this product do for the client?">{{ old('description', $software->description ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <input type="hidden" name="is_enabled" value="0">
        <input type="checkbox" name="is_enabled" value="1" class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked((bool) old('is_enabled', $software->is_enabled ?? true))>
        <span>
            <span class="block text-sm font-black text-slate-950">Enabled for client users</span>
            <span class="mt-1 block text-sm text-slate-500">Client users can only see enabled products assigned to their own client.</span>
        </span>
    </label>
</div>

<div class="mt-8 flex flex-wrap items-center justify-end gap-3">
    <a href="{{ route('softwares.index') }}" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-950">Cancel</a>
    <button type="submit" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-soft transition hover:bg-indigo-700">{{ $buttonText }}</button>
</div>
