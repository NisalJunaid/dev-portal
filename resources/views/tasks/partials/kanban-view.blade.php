<section
    data-kanban-board
    data-view="{{ $activeView }}"
    data-can-move="{{ $canMove ? 'true' : 'false' }}"
    data-reorder-url="{{ route('kanban.tickets.reorder') }}"
    class="h-full min-h-0"
>
    <div class="grid h-full min-h-0 auto-cols-[minmax(18rem,20rem)] grid-flow-col gap-4 overflow-x-auto pb-4 sm:auto-cols-[20rem] lg:gap-5 sleek-scrollbar">
        @foreach ($columns as $columnKey => $label)
            @php($columnTickets = $ticketsByColumn[$columnKey] ?? collect())
            <div class="flex h-full min-h-0 w-full flex-col border border-slate-200 bg-slate-100/80 p-4">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-[0.2em] text-slate-700">{{ $label }}</h3>
                        <p class="mt-1 text-xs font-bold text-slate-400"><span data-column-count>{{ $columnTickets->count() }}</span> tickets</p>
                    </div>
                </div>
                <div data-kanban-column data-column="{{ $columnKey }}" class="min-h-0 flex-1 space-y-3 overflow-y-auto rounded-2xl border border-dashed border-slate-300 bg-white/60 p-3 sleek-scrollbar">
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
</section>
