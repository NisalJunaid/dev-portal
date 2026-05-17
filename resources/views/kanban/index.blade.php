<x-app-layout>
    <x-slot name="header">Kanban</x-slot>

    <div class="space-y-6">
        <section class="card flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">Asana-style planning</p>
                <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Kanban board</h2>
                <p class="mt-2 max-w-2xl text-slate-600">Drag tickets between workflow columns, reorder priority within a column, and open details without leaving the board.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                @foreach ($views as $viewKey => $label)
                    <a href="{{ route('kanban.index', ['view' => $viewKey]) }}" @class(['rounded-2xl px-5 py-3 text-sm font-black shadow-sm transition', 'bg-slate-900 text-white' => $activeView === $viewKey, 'border border-slate-200 bg-white text-slate-700 hover:border-indigo-200 hover:text-indigo-700' => $activeView !== $viewKey])>{{ $label }}</a>
                @endforeach
            </div>
        </section>

        <section
            data-kanban-board
            data-view="{{ $activeView }}"
            data-can-move="{{ $canMove ? 'true' : 'false' }}"
            data-reorder-url="{{ route('kanban.tickets.reorder') }}"
            class="space-y-4"
        >
            <div data-kanban-toast class="hidden rounded-2xl border px-5 py-4 text-sm font-bold"></div>

            <div class="grid auto-cols-[minmax(18rem,20rem)] grid-flow-col gap-4 overflow-x-auto pb-4 sm:auto-cols-[20rem] lg:gap-5">
                @foreach ($columns as $columnKey => $label)
                    @php($columnTickets = $ticketsByColumn[$columnKey] ?? collect())
                    <div class="w-full rounded-3xl border border-slate-200 bg-slate-100/80 p-4">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-black uppercase tracking-[0.2em] text-slate-700">{{ $label }}</h3>
                                <p class="mt-1 text-xs font-bold text-slate-400"><span data-column-count>{{ $columnTickets->count() }}</span> tickets</p>
                            </div>
                            <span data-kanban-saving="{{ $columnKey }}" class="hidden rounded-full bg-indigo-100 px-3 py-1 text-xs font-black uppercase tracking-wide text-indigo-700">Saving…</span>
                        </div>

                        <div
                            data-kanban-column
                            data-column="{{ $columnKey }}"
                            class="min-h-[28rem] space-y-3 rounded-2xl border border-dashed border-slate-300 bg-white/60 p-3 sm:min-h-[34rem]"
                        >
                            @forelse ($columnTickets as $ticket)
                                @include('kanban.partials.card', ['ticket' => $ticket, 'activeView' => $activeView])
                            @empty
                                <div data-empty-state class="rounded-2xl border border-dashed border-slate-200 bg-white/70 p-5 text-center text-sm font-bold text-slate-400">Drop tickets here</div>
                            @endforelse
                            @if ($columnTickets->count())
                                <div data-empty-state class="hidden rounded-2xl border border-dashed border-slate-200 bg-white/70 p-5 text-center text-sm font-bold text-slate-400">Drop tickets here</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @include('tickets.partials.drawer')
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const board = document.querySelector('[data-kanban-board]');
            if (! board) return;

            const toast = board.querySelector('[data-kanban-toast]');
            const view = board.dataset.view;

            const showToast = (message, type = 'success') => {
                window.Kiel?.toast(message, type);
                if (! toast) return;
                toast.textContent = message;
                toast.className = `rounded-2xl border px-5 py-4 text-sm font-bold ${type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'}`;
                window.setTimeout(() => toast.classList.add('hidden'), 4000);
            };

            const setSaving = (column, saving) => board.querySelector(`[data-kanban-saving="${column}"]`)?.classList.toggle('hidden', ! saving);
            const cardsFor = (column) => [...column.querySelectorAll('[data-kanban-card]')];
            const refreshColumn = (column) => {
                const cards = cardsFor(column);
                column.querySelector('[data-empty-state]')?.classList.toggle('hidden', cards.length > 0);
                column.closest('.rounded-3xl')?.querySelector('[data-column-count]')?.replaceChildren(document.createTextNode(cards.length));
            };
            const refreshAll = () => board.querySelectorAll('[data-kanban-column]').forEach(refreshColumn);
            const revertDrop = (event) => {
                const reference = event.from.children[event.oldIndex] || null;
                event.from.insertBefore(event.item, reference);
                refreshAll();
            };
            const syncCard = (card, ticket) => {
                card.dataset.currentStatus = ticket.status;
                card.dataset.currentColumn = ticket.column;
                card.dataset.statusLabel = ticket.status_label;
                card.querySelector('[data-card-status]')?.replaceChildren(document.createTextNode(ticket.status_label));
                card.querySelector('[data-blocked-badge]')?.classList.toggle('hidden', ! ticket.blocked);
            };
            const saveReorder = async (column) => {
                const ids = cardsFor(column).map((card) => Number(card.dataset.ticketId));
                if (! ids.length) return;
                const columnKey = column.dataset.column;
                setSaving(columnKey, true);
                try {
                    const payload = await window.Kiel.request(board.dataset.reorderUrl, {
                        method: 'PATCH',
                        body: JSON.stringify({ tickets: ids, column: columnKey, view }),
                    });
                    showToast(payload.message || 'Order saved.');
                } catch (error) {
                    showToast(error.message, 'error');
                } finally {
                    setSaving(columnKey, false);
                }
            };

            let draggedCard = false;

            const initializeSortables = () => {
                if (! window.Sortable) return window.setTimeout(initializeSortables, 50);
                board.querySelectorAll('[data-kanban-column]').forEach((column) => {
                    window.Sortable.create(column, {
                        group: 'tickets-kanban',
                        animation: 220,
                        easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                        fallbackTolerance: 4,
                        draggable: '[data-kanban-card]',
                        ghostClass: 'kanban-card-ghost',
                        chosenClass: 'kanban-card-chosen',
                        dragClass: 'kanban-card-drag',
                        disabled: board.dataset.canMove !== 'true',
                        filter: '[data-empty-state], a',
                        preventOnFilter: false,
                        onStart: () => { draggedCard = true; },
                        onEnd: async (event) => {
                            const card = event.item;
                            const newColumn = event.to.dataset.column;
                            const oldColumn = card.dataset.currentColumn;

                            if (event.from === event.to && event.oldIndex === event.newIndex) return refreshAll();

                            setSaving(newColumn, true);
                            card.classList.add('opacity-60', 'pointer-events-none');
                            try {
                                if (newColumn !== oldColumn) {
                                    const payload = await window.Kiel.request(card.dataset.moveUrl, {
                                        method: 'PATCH',
                                        body: JSON.stringify({ column: newColumn, position: event.newIndex, view }),
                                    });
                                    syncCard(card, payload.ticket);
                                    showToast(payload.message || 'Ticket saved.');
                                }
                                await saveReorder(event.to);
                            } catch (error) {
                                revertDrop(event);
                                showToast(error.message, 'error');
                            } finally {
                                setSaving(newColumn, false);
                                card.classList.remove('opacity-60', 'pointer-events-none');
                                refreshAll();
                            }
                        },
                    });
                });
            };

            board.addEventListener('click', (event) => {
                if (!draggedCard) return;
                draggedCard = false;
                event.preventDefault();
                event.stopPropagation();
            }, true);

            initializeSortables();
            refreshAll();
        });
    </script>
</x-app-layout>
