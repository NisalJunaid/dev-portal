<div x-cloak x-show="createFeatureDrawerOpen" class="fixed inset-0 z-[90]" @keydown.escape.window="closeCreateFeatureDrawer()">
    <div x-show="createFeatureDrawerOpen" x-transition.opacity class="absolute inset-0 bg-slate-950/40" @click="closeCreateFeatureDrawer()"></div>
    <aside x-show="createFeatureDrawerOpen" x-transition:enter="duration-300 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="duration-200 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="absolute right-0 top-0 h-full w-full max-w-xl bg-white shadow-2xl" @click.stop>
        <div class="flex h-full flex-col">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4"><h3 class="text-lg font-black text-slate-950">Feature Request</h3><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" @click="closeCreateFeatureDrawer()">✕</button></div>
            <form class="flex-1 overflow-y-auto space-y-4 px-6 py-5" @submit.prevent="submitCreateFeature" x-ref="createFeatureForm">
                <label class="block text-sm font-semibold">Title<input x-model="createFeatureForm.title" x-ref="createFeatureTitle" class="mt-1 w-full rounded-xl border-slate-200" required></label>
                <p x-show="createFeatureErrors.title" x-text="createFeatureErrors.title" class="text-xs text-rose-600"></p>
                <label class="block text-sm font-semibold">Description<textarea x-model="createFeatureForm.description" rows="4" class="mt-1 w-full rounded-xl border-slate-200" required></textarea></label>
                <label class="block text-sm font-semibold">Software<select x-model="createFeatureForm.software_id" class="mt-1 w-full rounded-xl border-slate-200" required><option value="">Select software</option>@foreach($softwares as $software)<option value="{{ $software->id }}">{{ $software->name }}</option>@endforeach</select></label>
                <label class="block text-sm font-semibold">Urgency<select x-model="createFeatureForm.urgency" class="mt-1 w-full rounded-xl border-slate-200" required><option value="">Select urgency</option>@foreach(\App\Models\Ticket::URGENCIES as $urgency)<option value="{{ $urgency }}">{{ str($urgency)->headline() }}</option>@endforeach</select></label>
                <div class="flex justify-end gap-2 pt-2"><button type="button" class="rounded-xl border px-4 py-2" @click="closeCreateFeatureDrawer()">Cancel</button><button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-white" :disabled="createFeatureSubmitting"><span x-show="!createFeatureSubmitting">Create Feature</span><span x-show="createFeatureSubmitting">Creating…</span></button></div>
            </form>
        </div>
    </aside>
</div>
