<x-app-layout>
    <x-slot name="header">Tasks</x-slot>

    <div
        x-data="taskWorkspace({ initialView: @js($activeView), initialFiltersOpen: false })"
        x-init="init()"
        class="space-y-4"
    >
        <section class="card flex flex-wrap items-center justify-between gap-3 py-3">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-black text-slate-950">Tasks</h2>
                <div class="inline-flex items-center rounded-xl border border-slate-200 bg-white p-1">
                    <template x-for="view in views" :key="view.key">
                        <button type="button" class="rounded-lg p-2 transition" :class="activeView === view.key ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'" :title="view.label" @click="setView(view.key)">
                            <span class="sr-only" x-text="view.label"></span>
                            <span x-html="view.icon"></span>
                        </button>
                    </template>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="inline-flex items-center rounded-xl border border-slate-200 p-2 text-slate-700 transition hover:border-slate-300" title="Filters" @click="toggleFilters" :aria-expanded="showFilters.toString()">
                    <span class="sr-only">Filters</span>
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 5h14v2H3V5zm3 4h8v2H6V9zm3 4h2v2H9v-2z"/></svg>
                </button>
                @if ($isKielUser)
                    <a href="{{ route('tickets.create') }}" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-black text-white">Create Ticket</a>
                @endif
            </div>
        </section>

        <section x-cloak x-show="showFilters" x-transition.opacity.duration.150ms class="card p-4">
            <form method="GET" action="{{ route('tasks.index') }}" class="grid gap-3 xl:grid-cols-[minmax(16rem,1fr)_repeat(7,minmax(0,10rem))_auto]">
                <input type="hidden" name="view" :value="activeView">
                <label class="xl:col-span-2"><span class="sr-only">Search tickets</span><input name="search" value="{{ $filters['search'] ?? '' }}" type="search" placeholder="Search..." class="w-full rounded-xl border-slate-200 text-sm"></label>
                <select name="type" class="rounded-xl border-slate-200 text-sm"><option value="">All types</option>@foreach (\App\Models\Ticket::TYPES as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ str($type)->headline() }}</option>@endforeach</select>
                <select name="urgency" class="rounded-xl border-slate-200 text-sm"><option value="">All urgency</option>@foreach (\App\Models\Ticket::URGENCIES as $urgency)<option value="{{ $urgency }}" @selected(($filters['urgency'] ?? '') === $urgency)>{{ str($urgency)->headline() }}</option>@endforeach</select>
                <select name="status" class="rounded-xl border-slate-200 text-sm"><option value="">All statuses</option>@foreach (\App\Models\Ticket::STATUSES as $statusOption)<option value="{{ $statusOption }}" @selected(($filters['status'] ?? '') === $statusOption)>{{ str($statusOption)->replace('_', ' ')->headline() }}</option>@endforeach</select>
                <select name="assigned_to" class="rounded-xl border-slate-200 text-sm"><option value="">Any assignee</option><option value="unassigned" @selected(($filters['assigned_to'] ?? '') === 'unassigned')>Unassigned</option>@foreach ($teamMembers as $member)<option value="{{ $member->id }}" @selected((string) ($filters['assigned_to'] ?? '') === (string) $member->id)>{{ $member->name }}</option>@endforeach</select>
                @if ($isKielUser)<select name="client_id" class="rounded-xl border-slate-200 text-sm"><option value="">All clients</option>@foreach ($clients as $client)<option value="{{ $client->id }}" @selected((string) ($filters['client_id'] ?? '') === (string) $client->id)>{{ $client->name }}</option>@endforeach</select>@endif
                <select name="software_id" class="rounded-xl border-slate-200 text-sm"><option value="">All software</option>@foreach ($softwares as $software)<option value="{{ $software->id }}" @selected((string) ($filters['software_id'] ?? '') === (string) $software->id)>{{ $software->name }}</option>@endforeach</select>
                <select name="blocked" class="rounded-xl border-slate-200 text-sm"><option value="">Any block</option><option value="yes" @selected(($filters['blocked'] ?? '') === 'yes')>Blocked</option><option value="no" @selected(($filters['blocked'] ?? '') === 'no')>Not blocked</option></select>
                <select name="per_page" class="rounded-xl border-slate-200 text-sm">@foreach ([10, 25, 50, 100] as $size)<option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 25) === $size)>{{ $size }}/page</option>@endforeach</select>
                <div class="flex gap-2"><button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-black text-white">Apply</button><a href="{{ route('tasks.index', ['view' => $activeView]) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-600">Reset</a></div>
            </form>
        </section>

        <section class="min-h-[65vh]">
            <div x-cloak x-show="activeView === 'list'" x-transition.opacity.duration.150ms>@include('tasks.partials.list-view')</div>
            <div x-cloak x-show="activeView === 'board'" x-transition.opacity.duration.150ms>@include('tasks.partials.kanban-view', ['activeView' => 'all', 'columns' => $kanbanColumns, 'ticketsByColumn' => $kanbanTicketsByColumn, 'canMove' => $canMove])</div>
            <div x-cloak x-show="activeView === 'timeline'" x-transition.opacity.duration.150ms>@include('tasks.partials.timeline-view', ['canEdit' => $canEditTimeline])</div>
        </section>

        @include('tickets.partials.drawer')
    </div>

    <script>
        function taskWorkspace(config) { return {
            activeView: config.initialView || 'list',
            showFilters: config.initialFiltersOpen ?? false,
            views: [
                { key: 'list', label: 'List', icon: '<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 5h14v2H3V5zm0 4h14v2H3V9zm0 4h14v2H3v-2z"/></svg>' },
                { key: 'board', label: 'Board', icon: '<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4h4v12H3V4zm5 0h4v12H8V4zm5 0h4v12h-4V4z"/></svg>' },
                { key: 'timeline', label: 'Timeline', icon: '<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M4 4h12v2H4V4zm0 4h6v2H4V8zm8 0h4v2h-4V8zM4 12h4v2H4v-2zm6 0h6v2h-6v-2z"/></svg>' },
            ],
            init() { const saved = localStorage.getItem('kiel.tasks.filters.open'); this.showFilters = saved === '1'; this.$nextTick(() => this.initView()); },
            initView() { if (this.activeView === 'board') window.KielKanban?.initAll?.(); if (this.activeView === 'timeline') window.KielTimeline?.initAll?.(); },
            toggleFilters() { this.showFilters = !this.showFilters; localStorage.setItem('kiel.tasks.filters.open', this.showFilters ? '1' : '0'); },
            setView(view) { this.activeView = view; const url = new URL(window.location); url.searchParams.set('view', view); window.history.pushState({}, '', url); this.$nextTick(() => this.initView()); }
        }}
    </script>
</x-app-layout>
