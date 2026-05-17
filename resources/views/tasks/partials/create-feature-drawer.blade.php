<div x-cloak x-show="createFeatureDrawerOpen" class="fixed inset-0 z-[90]" @keydown.escape.window="closeCreateFeatureDrawer()">
    <div class="absolute inset-0 bg-slate-900/40" @click="closeCreateFeatureDrawer()"></div>
    <aside class="absolute right-0 top-0 h-full w-full max-w-xl transform bg-white shadow-2xl transition" @click.stop>
        <div class="flex h-full flex-col">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><h3 class="text-lg font-black text-slate-950">Feature Request</h3><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" @click="closeCreateFeatureDrawer()">✕</button></div>
            <form class="flex-1 overflow-y-auto p-5 space-y-4" @submit.prevent="submitCreateFeature">
                <label class="block text-sm font-semibold">Title<input x-model="createFeatureForm.title" class="mt-1 w-full rounded-xl border-slate-200" required></label>
                <label class="block text-sm font-semibold">Description<textarea x-model="createFeatureForm.description" rows="4" class="mt-1 w-full rounded-xl border-slate-200" required></textarea></label>
                <label class="block text-sm font-semibold">Software<select x-model="createFeatureForm.software_id" class="mt-1 w-full rounded-xl border-slate-200" required><option value="">Select software</option>@foreach($softwares as $software)<option value="{{ $software->id }}">{{ $software->name }}</option>@endforeach</select></label>
                <label class="block text-sm font-semibold">Urgency<select x-model="createFeatureForm.urgency" class="mt-1 w-full rounded-xl border-slate-200" required><option value="">Select urgency</option>@foreach(\App\Models\Ticket::URGENCIES as $urgency)<option value="{{ $urgency }}">{{ str($urgency)->headline() }}</option>@endforeach</select></label>
                <div class="flex justify-end gap-2"><button type="button" class="rounded-xl border px-4 py-2" @click="closeCreateFeatureDrawer()">Cancel</button><button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-white">Create Feature</button></div>
            </form>
        </div>
    </aside>
</div>
