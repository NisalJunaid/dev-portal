@php
    $query = request()->query();
    $sortUrl = function (string $column) use ($query, $sort, $direction) {
        return route('tickets.index', array_merge($query, [
            'sort' => $column,
            'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
            'page' => null,
        ]));
    };
    $sortIndicator = fn (string $column) => $sort === $column ? ($direction === 'asc' ? '↑' : '↓') : '↕';
    $statusOptions = [
        'bug' => collect(\App\Models\Ticket::BUG_STATUSES)->map(fn ($status) => ['value' => $status, 'label' => str($status)->replace('_', ' ')->headline()->toString()])->values(),
        'feature' => collect(\App\Models\Ticket::FEATURE_STATUSES)->map(fn ($status) => ['value' => $status, 'label' => str($status)->replace('_', ' ')->headline()->toString()])->values(),
        'all' => collect(\App\Models\Ticket::STATUSES)->map(fn ($status) => ['value' => $status, 'label' => str($status)->replace('_', ' ')->headline()->toString()])->values(),
    ];
    $urgencyOptions = collect(\App\Models\Ticket::URGENCIES)->map(fn ($urgency) => ['value' => $urgency, 'label' => str($urgency)->headline()->toString()])->values();
    $teamMemberOptions = $teamMembers->map(fn ($member) => ['value' => (string) $member->id, 'label' => $member->name])->values();
@endphp

<section
    class="card overflow-hidden p-0"
    x-data="ticketList({
        csrf: @js(csrf_token()),
        canInlineEdit: @js($isKielUser),
        urgencyOptions: @js($urgencyOptions),
        teamMembers: @js($teamMemberOptions),
        statusOptions: @js($statusOptions),
    })"
>
    <div class="border-b border-slate-200 bg-white p-5">
        <form method="GET" action="{{ route('tickets.index') }}" class="grid gap-3 xl:grid-cols-[minmax(18rem,1fr)_repeat(7,minmax(0,11rem))_auto]">
            <label class="xl:col-span-2">
                <span class="sr-only">Search tickets</span>
                <input name="search" value="{{ $filters['search'] ?? '' }}" type="search" placeholder="Search ticket number, title, client, software, assignee..." class="w-full rounded-2xl border-slate-200 text-sm font-semibold shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </label>
            <select name="type" class="rounded-2xl border-slate-200 text-sm font-bold text-slate-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All types</option>
                @foreach (\App\Models\Ticket::TYPES as $type)
                    <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ str($type)->headline() }}</option>
                @endforeach
            </select>
            <select name="urgency" class="rounded-2xl border-slate-200 text-sm font-bold text-slate-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All urgency</option>
                @foreach (\App\Models\Ticket::URGENCIES as $urgency)
                    <option value="{{ $urgency }}" @selected(($filters['urgency'] ?? '') === $urgency)>{{ str($urgency)->headline() }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-2xl border-slate-200 text-sm font-bold text-slate-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All statuses</option>
                @foreach (\App\Models\Ticket::STATUSES as $statusOption)
                    <option value="{{ $statusOption }}" @selected(($filters['status'] ?? '') === $statusOption)>{{ str($statusOption)->replace('_', ' ')->headline() }}</option>
                @endforeach
            </select>
            <select name="assigned_to" class="rounded-2xl border-slate-200 text-sm font-bold text-slate-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Any assignee</option>
                <option value="unassigned" @selected(($filters['assigned_to'] ?? '') === 'unassigned')>Unassigned</option>
                @foreach ($teamMembers as $member)
                    <option value="{{ $member->id }}" @selected((string) ($filters['assigned_to'] ?? '') === (string) $member->id)>{{ $member->name }}</option>
                @endforeach
            </select>
            @if ($isKielUser)
                <select name="client_id" class="rounded-2xl border-slate-200 text-sm font-bold text-slate-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All clients</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) ($filters['client_id'] ?? '') === (string) $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
            @endif
            <select name="software_id" class="rounded-2xl border-slate-200 text-sm font-bold text-slate-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All software</option>
                @foreach ($softwares as $software)
                    <option value="{{ $software->id }}" @selected((string) ($filters['software_id'] ?? '') === (string) $software->id)>{{ $software->name }}</option>
                @endforeach
            </select>
            <select name="blocked" class="rounded-2xl border-slate-200 text-sm font-bold text-slate-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Any block</option>
                <option value="yes" @selected(($filters['blocked'] ?? '') === 'yes')>Blocked</option>
                <option value="no" @selected(($filters['blocked'] ?? '') === 'no')>Not blocked</option>
            </select>
            <select name="per_page" class="rounded-2xl border-slate-200 text-sm font-bold text-slate-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @foreach ([10, 25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 25) === $size)>{{ $size }}/page</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-black text-white shadow-soft transition hover:bg-indigo-700">Filter</button>
                <a href="{{ route('tickets.index') }}" class="rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-black text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-700">Reset</a>
            </div>
        </form>
    </div>

    @if ($tickets->count())
        <div class="overflow-x-auto">
            <table class="min-w-[1500px] divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-widest text-slate-500">
                    <tr>
                        @foreach ([
                            'ticket_no' => 'Ticket #',
                            'title' => 'Title',
                            'type' => 'Type',
                            'urgency' => 'Urgency',
                            'status' => 'Status',
                            'assigned_to' => 'Assigned to',
                            'client' => 'Client',
                            'software' => 'Software',
                            'start_date' => 'Start date',
                            'due_date' => 'Due date',
                            'sprint' => 'Sprint cycle',
                            'blocked' => 'Blocked',
                            'updated_at' => 'Last updated',
                        ] as $column => $label)
                            <th class="px-4 py-4 whitespace-nowrap">
                                <a href="{{ $sortUrl($column) }}" class="inline-flex items-center gap-1 transition hover:text-indigo-700">
                                    {{ $label }} <span aria-hidden="true">{{ $sortIndicator($column) }}</span>
                                </a>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($tickets as $ticket)
                        @php
                            $latestSprint = $ticket->sprints->sortByDesc('sprint_no')->first();
                            $isOverdue = $ticket->due_date && $ticket->due_date->isPast() && ! in_array($ticket->status, [\App\Models\Ticket::STATUS_BUG_COMPLETED, \App\Models\Ticket::STATUS_FEATURE_COMPLETED], true);
                            $rowStatusOptions = $ticket->isBug() ? $statusOptions['bug'] : ($ticket->isFeature() ? $statusOptions['feature'] : $statusOptions['all']);
                        @endphp
                        <tr
                            class="group transition duration-150 ease-out hover:bg-indigo-50/40"
                            x-data="ticketRow({
                                endpoint: @js(route('tickets.inline-update', $ticket)),
                                values: @js([
                                    'title' => $ticket->title,
                                    'urgency' => $ticket->urgency,
                                    'assigned_to' => $ticket->assigned_to ? (string) $ticket->assigned_to : '',
                                    'start_date' => $ticket->start_date?->toDateString() ?? '',
                                    'due_date' => $ticket->due_date?->toDateString() ?? '',
                                    'status' => $ticket->status,
                                ]),
                                labels: @js([
                                    'urgency' => $ticket->formattedUrgency(),
                                    'assignee' => $ticket->assignee?->name ?? 'Unassigned',
                                    'start_date' => $ticket->start_date?->format('M j, Y') ?? 'Not set',
                                    'due_date' => $ticket->due_date?->format('M j, Y') ?? 'Not set',
                                    'status' => $ticket->formattedStatus(),
                                    'updated_at' => $ticket->updated_at?->format('M j, Y g:i A'),
                                ]),
                                meta: @js(['due_date_overdue' => $isOverdue, 'blocked' => $ticket->isBlocked()]),
                            })"
                        >
                            <td class="whitespace-nowrap px-4 py-4 align-top">
                                <a href="{{ route('tickets.show', $ticket) }}" data-ticket-drawer-url="{{ route('tickets.drawer', $ticket) }}" class="font-black text-slate-950 transition hover:text-indigo-700">{{ $ticket->ticket_no }}</a>
                            </td>
                            <td class="min-w-72 px-4 py-3 align-top">
                                @if ($isKielUser)
                                    <div class="relative" :class="stateClass('title')">
                                        <input type="text" x-model="values.title" @blur="save('title')" @keydown.enter.prevent="$event.target.blur()" class="w-full rounded-xl border border-transparent bg-transparent px-2 py-1 font-bold text-slate-800 transition focus:border-indigo-200 focus:bg-white focus:ring-indigo-500">
                                        <span x-show="states.title === 'saving'" class="absolute right-2 top-2 text-xs font-black text-indigo-600">Saving…</span>
                                    </div>
                                    <p x-show="errors.title" x-text="errors.title" class="mt-1 text-xs font-bold text-rose-600"></p>
                                @else
                                    <a href="{{ route('tickets.show', $ticket) }}" data-ticket-drawer-url="{{ route('tickets.drawer', $ticket) }}" class="font-bold text-slate-800 hover:text-indigo-700">{{ $ticket->title }}</a>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-top font-bold text-slate-600">{{ str($ticket->type ?? 'unclassified')->headline() }}</td>
                            <td class="whitespace-nowrap px-4 py-3 align-top">
                                @if ($isKielUser)
                                    <select x-model="values.urgency" @change="save('urgency')" :class="badgeClass('urgency')" class="rounded-full border-0 px-3 py-1 text-xs font-black uppercase tracking-wide ring-1 ring-inset focus:ring-2 focus:ring-indigo-500">
                                        <template x-for="option in window.ticketListConfig.urgencyOptions" :key="option.value"><option :value="option.value" x-text="option.label"></option></template>
                                    </select>
                                    <span x-show="states.urgency === 'saving'" class="ml-2 text-xs font-black text-indigo-600">Saving…</span>
                                @else
                                    <span @class(['rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide', 'bg-rose-100 text-rose-700' => $ticket->urgency === 'critical', 'bg-orange-100 text-orange-700' => $ticket->urgency === 'high', 'bg-amber-100 text-amber-700' => $ticket->urgency === 'medium', 'bg-emerald-100 text-emerald-700' => $ticket->urgency === 'low'])>{{ $ticket->formattedUrgency() }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-top">
                                @if ($isKielUser)
                                    <select x-model="values.status" @change="save('status')" :class="badgeClass('status')" class="max-w-48 rounded-full border-0 px-3 py-1 text-xs font-black uppercase tracking-wide ring-1 ring-inset focus:ring-2 focus:ring-indigo-500">
                                        @foreach ($rowStatusOptions as $option)
                                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <span x-show="states.status === 'saving'" class="ml-2 text-xs font-black text-indigo-600">Saving…</span>
                                @else
                                    <span @class(['rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide', 'bg-rose-100 text-rose-700' => $ticket->isBlocked(), 'bg-emerald-100 text-emerald-700' => in_array($ticket->status, [\App\Models\Ticket::STATUS_BUG_COMPLETED, \App\Models\Ticket::STATUS_FEATURE_COMPLETED], true), 'bg-indigo-100 text-indigo-700' => $ticket->status === \App\Models\Ticket::STATUS_IN_PROGRESS, 'bg-slate-100 text-slate-700' => ! $ticket->isBlocked()])>{{ $ticket->formattedStatus() }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-top">
                                @if ($isKielUser)
                                    <select x-model="values.assigned_to" @change="save('assigned_to')" class="rounded-xl border-slate-200 bg-white text-sm font-bold text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :class="stateClass('assigned_to')">
                                        <option value="">Unassigned</option>
                                        <template x-for="member in window.ticketListConfig.teamMembers" :key="member.value"><option :value="member.value" x-text="member.label"></option></template>
                                    </select>
                                    <span x-show="states.assigned_to === 'saving'" class="ml-2 text-xs font-black text-indigo-600">Saving…</span>
                                @else
                                    <span class="font-bold text-slate-600">{{ $ticket->assignee?->name ?? 'Unassigned' }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-top font-bold text-slate-700">{{ $ticket->client->name }}</td>
                            <td class="whitespace-nowrap px-4 py-4 align-top text-slate-600">{{ $ticket->software->name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 align-top">
                                @if ($isKielUser)
                                    <input type="date" x-model="values.start_date" @change="save('start_date')" class="rounded-xl border-slate-200 bg-white text-sm font-bold text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :class="stateClass('start_date')">
                                @else
                                    <span class="text-slate-600">{{ $ticket->start_date?->format('M j, Y') ?? 'Not set' }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-top">
                                @if ($isKielUser)
                                    <input type="date" x-model="values.due_date" @change="save('due_date')" class="rounded-xl border-slate-200 bg-white text-sm font-bold shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :class="[stateClass('due_date'), meta.due_date_overdue ? 'border-rose-300 bg-rose-50 text-rose-700' : 'text-slate-700']">
                                @else
                                    <span @class(['font-bold' => $isOverdue, 'text-rose-700' => $isOverdue, 'text-slate-600' => ! $isOverdue])>{{ $ticket->due_date?->format('M j, Y') ?? 'Not set' }}</span>
                                @endif
                                <span x-show="meta.due_date_overdue" class="ml-2 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-black text-rose-700">Overdue</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-top text-slate-600">{{ $latestSprint ? '#'.$latestSprint->sprint_no.' '.$latestSprint->name : 'No sprint' }}</td>
                            <td class="whitespace-nowrap px-4 py-4 align-top">
                                <span x-show="meta.blocked" class="rounded-full bg-rose-100 px-3 py-1 text-xs font-black uppercase tracking-wide text-rose-700">Blocked</span>
                                <span x-show="! meta.blocked" class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-emerald-700">Clear</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-top text-slate-500" x-text="labels.updated_at"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4">{{ $tickets->links() }}</div>
    @else
        <div class="p-12 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-indigo-50 text-2xl">🎫</div>
            <h3 class="mt-5 text-xl font-black text-slate-950">No tickets found</h3>
            <p class="mt-2 text-slate-500">Adjust the search or filters to find matching tickets.</p>
        </div>
    @endif
</section>

<script>
    window.ticketList = function (config) {
        window.ticketListConfig = config;
        return config;
    };

    window.ticketRow = function (config) {
        return {
            endpoint: config.endpoint,
            values: config.values,
            original: { ...config.values },
            labels: config.labels,
            meta: config.meta,
            states: {},
            errors: {},
            async save(field) {
                if (this.values[field] === this.original[field]) {
                    return;
                }

                const attemptedValue = this.values[field];
                this.states[field] = 'saving';
                this.errors[field] = '';

                try {
                    const response = await fetch(this.endpoint, {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': window.ticketListConfig.csrf,
                        },
                        body: JSON.stringify({ field, value: attemptedValue }),
                    });
                    const payload = await response.json();

                    if (! response.ok) {
                        throw payload;
                    }

                    this.applyPayload(payload.ticket);
                    this.states[field] = 'success';
                    setTimeout(() => {
                        if (this.states[field] === 'success') {
                            this.states[field] = '';
                        }
                    }, 1400);
                } catch (error) {
                    this.values[field] = this.original[field];
                    this.errors[field] = error?.errors?.[field]?.[0] || error?.message || 'Unable to save this field.';
                    this.states[field] = 'error';
                }
            },
            applyPayload(ticket) {
                this.values.title = ticket.title;
                this.values.urgency = ticket.urgency;
                this.values.assigned_to = ticket.assigned_to ? String(ticket.assigned_to) : '';
                this.values.start_date = ticket.start_date || '';
                this.values.due_date = ticket.due_date || '';
                this.values.status = ticket.status;
                this.original = { ...this.values };
                this.labels.urgency = ticket.urgency_label;
                this.labels.assignee = ticket.assignee_name;
                this.labels.start_date = ticket.start_date_label;
                this.labels.due_date = ticket.due_date_label;
                this.labels.status = ticket.status_label;
                this.labels.updated_at = ticket.updated_at;
                this.meta.due_date_overdue = ticket.due_date_overdue;
                this.meta.blocked = ticket.blocked;
            },
            stateClass(field) {
                return {
                    'ring-2 ring-indigo-200': this.states[field] === 'saving',
                    'ring-2 ring-emerald-300 bg-emerald-50': this.states[field] === 'success',
                    'ring-2 ring-rose-300 bg-rose-50': this.states[field] === 'error',
                };
            },
            badgeClass(field) {
                const value = this.values[field];
                const palette = field === 'urgency'
                    ? {
                        critical: 'bg-rose-100 text-rose-700 ring-rose-200',
                        high: 'bg-orange-100 text-orange-700 ring-orange-200',
                        medium: 'bg-amber-100 text-amber-700 ring-amber-200',
                        low: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
                    }
                    : {
                        bug_blocked: 'bg-rose-100 text-rose-700 ring-rose-200',
                        feature_blocked: 'bg-rose-100 text-rose-700 ring-rose-200',
                        bug_completed: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
                        feature_completed: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
                        in_progress: 'bg-indigo-100 text-indigo-700 ring-indigo-200',
                        rejected: 'bg-slate-200 text-slate-700 ring-slate-300',
                    };

                return palette[value] || 'bg-slate-100 text-slate-700 ring-slate-200';
            },
        };
    };
</script>
