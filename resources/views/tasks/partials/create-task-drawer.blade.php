<div x-cloak x-show="createTaskDrawerOpen" class="fixed inset-0 z-[90]" @keydown.escape.window="closeCreateTaskDrawer()">
    <div class="absolute inset-0 bg-slate-900/40" @click="closeCreateTaskDrawer()"></div>
    <aside class="absolute right-0 top-0 h-full w-full max-w-xl transform bg-white shadow-2xl transition" x-show="createTaskDrawerOpen" x-transition:enter="duration-200 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="duration-150 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" @click.stop>
        <div class="flex h-full flex-col">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h3 class="text-lg font-black text-slate-950">Create Task</h3>
                <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" @click="closeCreateTaskDrawer()">✕</button>
            </div>
            <form class="flex-1 overflow-y-auto p-5 space-y-4" @submit.prevent="submitCreateTask">
                <template x-if="createTaskErrors.global"><p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700" x-text="createTaskErrors.global"></p></template>
                <label class="block text-sm font-semibold text-slate-700">Title
                    <input x-model="createTaskForm.title" type="text" class="mt-1 w-full rounded-xl border-slate-200 text-sm" required>
                    <span class="mt-1 block text-xs text-rose-600" x-text="createTaskErrors.title"></span>
                </label>
                <label class="block text-sm font-semibold text-slate-700">Description
                    <textarea x-model="createTaskForm.description" rows="5" class="mt-1 w-full rounded-xl border-slate-200 text-sm" required></textarea>
                    <span class="mt-1 block text-xs text-rose-600" x-text="createTaskErrors.description"></span>
                </label>
                <label class="block text-sm font-semibold text-slate-700">Software
                    <select x-model="createTaskForm.software_id" class="mt-1 w-full rounded-xl border-slate-200 text-sm" required>
                        <option value="">Select software</option>
                        @foreach ($softwares as $software)
                            <option value="{{ $software->id }}">{{ $software->name }}</option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-xs text-rose-600" x-text="createTaskErrors.software_id"></span>
                </label>
                <label class="block text-sm font-semibold text-slate-700">Urgency
                    <select x-model="createTaskForm.urgency" class="mt-1 w-full rounded-xl border-slate-200 text-sm" required>
                        <option value="">Select urgency</option>
                        @foreach (\App\Models\Ticket::URGENCIES as $urgency)
                            <option value="{{ $urgency }}">{{ str($urgency)->headline() }}</option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-xs text-rose-600" x-text="createTaskErrors.urgency"></span>
                </label>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700" @click="closeCreateTaskDrawer()">Cancel</button>
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-black text-white" :disabled="createTaskSubmitting" x-text="createTaskSubmitting ? 'Creating…' : 'Create Task'"></button>
                </div>
            </form>
        </div>
    </aside>
</div>
