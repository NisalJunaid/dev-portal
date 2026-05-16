<x-app-layout>
    <x-slot name="header">Bug fixes</x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="card flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.25em] text-rose-600">Dedicated workflow</p>
                <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Bug fixes</h2>
                <p class="mt-2 max-w-2xl text-slate-600">Track client bug tickets separately from intake, feature requests, sprint planning, and feature delivery.</p>
                @unless ($canUpdateBugs)
                    <p class="mt-3 rounded-2xl bg-amber-50 px-4 py-2 text-sm font-bold text-amber-800">You can view your own bug tickets, but status updates are restricted to authorized Kiel users.</p>
                @endunless
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('bugs.index', ['view' => 'list']) }}" @class(['rounded-2xl px-5 py-3 text-sm font-black shadow-sm transition', 'bg-slate-900 text-white' => $view === 'list', 'border border-slate-200 bg-white text-slate-700 hover:border-rose-200 hover:text-rose-700' => $view !== 'list'])>List view</a>
                <a href="{{ route('bugs.index', ['view' => 'kanban']) }}" @class(['rounded-2xl px-5 py-3 text-sm font-black shadow-sm transition', 'bg-slate-900 text-white' => $view === 'kanban', 'border border-slate-200 bg-white text-slate-700 hover:border-rose-200 hover:text-rose-700' => $view !== 'kanban'])>Kanban view</a>
            </div>
        </section>

        @if ($view === 'kanban')
            <section
                data-bug-kanban
                data-can-update="{{ $canUpdateBugs ? 'true' : 'false' }}"
                class="space-y-4"
            >
                <div data-bug-toast class="hidden rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-800"></div>
                <div class="grid gap-5 xl:grid-cols-3">
                    @foreach ($columns as $status => $label)
                        <div class="rounded-3xl border border-slate-200 bg-slate-100/70 p-4">
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-black uppercase tracking-[0.2em] text-slate-700">{{ $label }}</h3>
                                    <p class="mt-1 text-xs font-bold text-slate-400">{{ ($bugs[$status] ?? collect())->count() }} bugs</p>
                                </div>
                                <span data-bug-saving="{{ $status }}" class="hidden rounded-full bg-indigo-100 px-3 py-1 text-xs font-black uppercase tracking-wide text-indigo-700">Saving…</span>
                            </div>

                            <div
                                data-bug-column
                                data-status="{{ $status }}"
                                class="min-h-96 space-y-3 rounded-2xl border border-dashed border-slate-300 bg-white/50 p-3"
                            >
                                @forelse (($bugs[$status] ?? collect()) as $bug)
                                    <article
                                        data-bug-card
                                        data-ticket-id="{{ $bug->id }}"
                                        data-current-status="{{ $bug->status }}"
                                        @if ($canUpdateBugs)
                                            data-pending-url="{{ route('bugs.pending', $bug) }}"
                                            data-block-url="{{ route('bugs.block', $bug) }}"
                                            data-complete-url="{{ route('bugs.complete', $bug) }}"
                                        @endif
                                        class="bug-card rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-rose-100 hover:shadow-soft"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <a href="{{ route('bugs.show', $bug) }}" class="font-black text-rose-700 hover:text-rose-800">{{ $bug->ticket_no }}</a>
                                            <span @class(['rounded-full px-2.5 py-1 text-[0.65rem] font-black uppercase tracking-wide', 'bg-rose-100 text-rose-700' => $bug->urgency === 'critical', 'bg-orange-100 text-orange-700' => $bug->urgency === 'high', 'bg-amber-100 text-amber-700' => $bug->urgency === 'medium', 'bg-emerald-100 text-emerald-700' => $bug->urgency === 'low'])>{{ $bug->formattedUrgency() }}</span>
                                        </div>
                                        <h4 class="mt-3 text-sm font-black leading-5 text-slate-950">{{ $bug->title }}</h4>
                                        <p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-500">{{ $bug->description }}</p>
                                        <div class="mt-4 border-t border-slate-100 pt-3 text-xs font-semibold text-slate-500">
                                            <p class="font-bold text-slate-700">{{ $bug->client->name }}</p>
                                            <p>{{ $bug->software->name }}</p>
                                            <p class="mt-2">Assigned: {{ $bug->assignee?->name ?? 'Unassigned' }}</p>
                                        </div>
                                    </article>
                                @empty
                                    <div data-empty-state class="rounded-2xl bg-white/70 p-6 text-center text-sm font-bold text-slate-400">Drop bugs here</div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @else
            <section class="card overflow-hidden p-0">
                @if ($bugs->count())
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500">
                                <tr>
                                    <th class="px-6 py-4">Bug</th>
                                    <th class="px-6 py-4">Client / Software</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4">Urgency</th>
                                    <th class="px-6 py-4">Assigned</th>
                                    <th class="px-6 py-4">Submitted</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach ($bugs as $bug)
                                    <tr class="transition hover:bg-slate-50/80">
                                        <td class="px-6 py-5">
                                            <a href="{{ route('bugs.show', $bug) }}" class="font-black text-rose-700 hover:text-rose-800">{{ $bug->ticket_no }}</a>
                                            <p class="mt-1 text-sm font-semibold text-slate-700">{{ $bug->title }}</p>
                                        </td>
                                        <td class="px-6 py-5 text-sm text-slate-600">
                                            <p class="font-bold text-slate-800">{{ $bug->client->name }}</p>
                                            <p>{{ $bug->software->name }}</p>
                                        </td>
                                        <td class="px-6 py-5"><span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-black uppercase tracking-wide text-rose-700">{{ $bug->formattedStatus() }}</span></td>
                                        <td class="px-6 py-5"><span @class(['rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide', 'bg-rose-100 text-rose-700' => $bug->urgency === 'critical', 'bg-orange-100 text-orange-700' => $bug->urgency === 'high', 'bg-amber-100 text-amber-700' => $bug->urgency === 'medium', 'bg-emerald-100 text-emerald-700' => $bug->urgency === 'low'])>{{ $bug->formattedUrgency() }}</span></td>
                                        <td class="px-6 py-5 text-sm font-bold text-slate-600">{{ $bug->assignee?->name ?? 'Unassigned' }}</td>
                                        <td class="px-6 py-5 text-sm text-slate-500">{{ $bug->submitted_at->format('M j, Y g:i A') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-200 px-6 py-4">{{ $bugs->links() }}</div>
                @else
                    <div class="p-12 text-center">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-rose-50 text-2xl">🐞</div>
                        <h3 class="mt-5 text-xl font-black text-slate-950">No bug tickets yet</h3>
                        <p class="mt-2 text-slate-500">Classified bug tickets appear here, separate from feature workflow.</p>
                    </div>
                @endif
            </section>
        @endif
    </div>

        @if ($view === 'kanban' && $canUpdateBugs)
            <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js" defer></script>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const board = document.querySelector('[data-bug-kanban]');

                    if (! board || board.dataset.canUpdate !== 'true') {
                        return;
                    }

                    const statusAction = {
                        bug_pending: 'pending',
                        bug_blocked: 'block',
                        bug_completed: 'complete',
                    };
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    const toast = board.querySelector('[data-bug-toast]');

                    const showToast = (message) => {
                        if (! toast) {
                            return;
                        }

                        toast.textContent = message || 'Unable to update bug status.';
                        toast.classList.remove('hidden');
                        window.setTimeout(() => toast.classList.add('hidden'), 5000);
                    };

                    const setSaving = (status, isSaving) => {
                        const saving = board.querySelector(`[data-bug-saving="${status}"]`);
                        saving?.classList.toggle('hidden', ! isSaving);
                    };

                    const refreshEmptyStates = () => {
                        board.querySelectorAll('[data-bug-column]').forEach((column) => {
                            const empty = column.querySelector('[data-empty-state]');
                            const cards = column.querySelectorAll('[data-bug-card]');
                            empty?.classList.toggle('hidden', cards.length > 0);
                        });
                    };

                    const revertDrop = (event) => {
                        if (event.from === event.to && event.oldIndex === event.newIndex) {
                            return;
                        }

                        const reference = event.from.children[event.oldIndex] || null;
                        event.from.insertBefore(event.item, reference);
                        refreshEmptyStates();
                    };

                    const initializeSortables = () => {
                        if (! window.Sortable) {
                            window.setTimeout(initializeSortables, 50);
                            return;
                        }

                        board.querySelectorAll('[data-bug-column]').forEach((column) => {
                            window.Sortable.create(column, {
                                group: 'bug-kanban',
                                animation: 180,
                                easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                                draggable: '[data-bug-card]',
                                ghostClass: 'bug-card-ghost',
                                chosenClass: 'bug-card-chosen',
                                dragClass: 'bug-card-drag',
                                onEnd: async (event) => {
                                    const card = event.item;
                                    const newStatus = event.to.dataset.status;
                                    const oldStatus = card.dataset.currentStatus;

                                    if (! newStatus || newStatus === oldStatus) {
                                        refreshEmptyStates();
                                        return;
                                    }

                                    const action = statusAction[newStatus];
                                    const url = card.dataset[`${action}Url`];

                                    if (! action || ! url) {
                                        revertDrop(event);
                                        showToast('That drop is not allowed for this bug.');
                                        return;
                                    }

                                    setSaving(newStatus, true);
                                    card.classList.add('opacity-60', 'pointer-events-none');

                                    try {
                                        const response = await fetch(url, {
                                            method: 'POST',
                                            headers: {
                                                Accept: 'application/json',
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': csrf,
                                            },
                                            body: JSON.stringify({ status: newStatus }),
                                        });

                                        if (! response.ok) {
                                            const payload = await response.json().catch(() => ({}));
                                            throw new Error(payload.message || 'Unable to update bug status.');
                                        }

                                        card.dataset.currentStatus = newStatus;
                                        refreshEmptyStates();
                                    } catch (error) {
                                        revertDrop(event);
                                        showToast(error.message);
                                    } finally {
                                        setSaving(newStatus, false);
                                        card.classList.remove('opacity-60', 'pointer-events-none');
                                    }
                                },
                            });
                        });
                    };

                    initializeSortables();
                    refreshEmptyStates();
                });
            </script>
        @endif
</x-app-layout>
