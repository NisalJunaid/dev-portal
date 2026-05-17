<x-app-layout>
    <x-slot name="header">Tasks</x-slot>

    <div x-data="{ activeView: @js($activeView), showFilters: false }" class="space-y-4">
        <section class="card flex items-center justify-between py-4">
            <h2 class="text-2xl font-black text-slate-950">Tasks</h2>
            @if ($isKielUser)
                <a href="{{ route('tickets.create') }}" class="rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-black text-white">Create Ticket</a>
            @endif
        </section>

        <section class="card flex items-center justify-between py-3">
            <div class="flex items-center gap-2 text-sm font-bold">
                <a href="{{ route('tasks.index', array_merge(request()->query(), ['view' => 'list'])) }}" :class="activeView==='list' ? 'bg-slate-900 text-white' : 'text-slate-600'" class="rounded-xl px-3 py-1.5">List</a>
                <a href="{{ route('tasks.index', array_merge(request()->query(), ['view' => 'board'])) }}" :class="activeView==='board' ? 'bg-slate-900 text-white' : 'text-slate-600'" class="rounded-xl px-3 py-1.5">Board</a>
                <a href="{{ route('tasks.index', array_merge(request()->query(), ['view' => 'timeline'])) }}" :class="activeView==='timeline' ? 'bg-slate-900 text-white' : 'text-slate-600'" class="rounded-xl px-3 py-1.5">Timeline</a>
            </div>
            <button type="button" @click="showFilters=!showFilters" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-1.5 text-sm font-bold text-slate-700">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M3 5h14v2H3V5zm3 4h8v2H6V9zm3 4h2v2H9v-2z"/></svg>
                Filters
            </button>
        </section>

        <div x-show="activeView==='list'">
            @include('tasks.partials.list-view')
        </div>
        <div x-show="activeView==='board'">
            @include('tasks.partials.kanban-view', ['activeView' => 'all', 'columns' => $kanbanColumns, 'ticketsByColumn' => $kanbanTicketsByColumn, 'canMove' => $canMove])
        </div>
        <div x-show="activeView==='timeline'">
            @include('tasks.partials.timeline-view', ['canEdit' => $canEditTimeline])
        </div>

        @include('tickets.partials.drawer')
    </div>
</x-app-layout>
