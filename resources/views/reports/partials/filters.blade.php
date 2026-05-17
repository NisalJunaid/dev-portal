<section class="card">
    <form method="GET" action="{{ $action }}" class="grid gap-4 lg:grid-cols-4">
        @if (! auth()->user()->isClientUser())
            <select name="client_id" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All clients</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected(($filters['client_id'] ?? '') == $client->id)>{{ $client->name }}</option>
                @endforeach
            </select>
        @endif

        <select name="software_id" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">All software</option>
            @foreach ($softwares as $software)
                <option value="{{ $software->id }}" @selected(($filters['software_id'] ?? '') == $software->id)>{{ $software->name }}</option>
            @endforeach
        </select>

        <select name="user_id" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">All users</option>
            @foreach ($users as $filterUser)
                <option value="{{ $filterUser->id }}" @selected(($filters['user_id'] ?? '') == $filterUser->id)>{{ $filterUser->name }}</option>
            @endforeach
        </select>

        <select name="ticket_type" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">All ticket types</option>
            @foreach ($ticketTypes as $type)
                <option value="{{ $type }}" @selected(($filters['ticket_type'] ?? '') === $type)>{{ str($type)->headline() }}</option>
            @endforeach
        </select>

        <select name="status" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>
            @endforeach
        </select>

        <select name="sprint_id" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">All sprints</option>
            @foreach ($sprints as $sprint)
                <option value="{{ $sprint->id }}" @selected(($filters['sprint_id'] ?? '') == $sprint->id)>{{ $sprint->name }}</option>
            @endforeach
        </select>

        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">

        <div class="flex flex-wrap gap-3 lg:col-span-4">
            <button type="submit" class="rounded-2xl bg-slate-900 px-5 py-2.5 text-sm font-black text-white">Apply filters</button>
            <a href="{{ $action }}" class="rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-black text-slate-600">Reset</a>
            @isset($exportRoute)
                @if (auth()->user()->isKielUser())
                    <a href="{{ $exportRoute }}?{{ http_build_query(request()->query()) }}" class="rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-black text-white">Export CSV</a>
                    @if ($excelAvailable)
                        <a href="{{ $exportRoute }}?{{ http_build_query([...request()->query(), 'format' => 'xlsx']) }}" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-2.5 text-sm font-black text-emerald-700">Export Excel</a>
                    @endif
                @endif
            @endisset
        </div>
    </form>
</section>
