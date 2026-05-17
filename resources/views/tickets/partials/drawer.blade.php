@php($mode = $mode ?? 'shell')

@if ($mode === 'content')
    @php
        $latestSprint = $ticket->sprints->sortByDesc('sprint_no')->first();
        $statusOptions = $ticket->isBug() ? \App\Models\Ticket::BUG_STATUSES : ($ticket->isFeature() ? \App\Models\Ticket::FEATURE_STATUSES : \App\Models\Ticket::STATUSES);
        $timerPayload = $currentTimer ? [
            'status' => $currentTimer->status,
            'current_duration_seconds' => $currentTimer->currentDurationSeconds(),
        ] : null;
    @endphp

    <div class="flex h-full flex-col" data-ticket-drawer-content data-ticket-id="{{ $ticket->id }}" data-drawer-refresh-url="{{ route('tickets.drawer', $ticket) }}">
        <header class="border-b border-slate-200 bg-white px-6 py-5">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-black uppercase tracking-[0.25em] text-indigo-600">{{ $ticket->ticket_no }}</p>
                    @if ($isKielUser)
                        <input data-inline-field="title" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" value="{{ $ticket->title }}" class="mt-2 w-full rounded-2xl border-transparent px-0 text-2xl font-black tracking-tight text-slate-950 focus:border-indigo-300 focus:px-3 focus:ring-indigo-500">
                    @else
                        <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ $ticket->title }}</h3>
                    @endif
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-black uppercase tracking-wide">
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">{{ $ticket->formattedStatus() }}</span>
                        <span @class(['rounded-full px-3 py-1', 'bg-rose-100 text-rose-700' => $ticket->urgency === 'critical', 'bg-orange-100 text-orange-700' => $ticket->urgency === 'high', 'bg-amber-100 text-amber-700' => $ticket->urgency === 'medium', 'bg-emerald-100 text-emerald-700' => $ticket->urgency === 'low'])>{{ $ticket->formattedUrgency() }}</span>
                    </div>
                </div>
                <button type="button" data-ticket-drawer-close class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-600 transition hover:border-indigo-200 hover:text-indigo-700">Close</button>
            </div>
        </header>

        <div class="flex-1 space-y-6 overflow-y-auto bg-slate-50/70 p-6">
            <div data-drawer-message class="hidden rounded-2xl border px-4 py-3 text-sm font-bold"></div>

            @if ($activeBlock)
                <section class="rounded-3xl border border-rose-200 bg-rose-50 p-5" data-blocked-banner>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm font-black uppercase tracking-wide text-rose-700">Blocked</p>
                        <p class="text-xs font-black text-rose-700">Total blocked: {{ gmdate('H:i:s', $totalBlockedDuration) }}</p>
                    </div>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-rose-950">{{ $activeBlock->reason }}</p>
                </section>
            @endif

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h4 class="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Details</h4>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-black text-slate-700">Status
                        @if ($isKielUser)
                            <select data-inline-field="status" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" class="mt-2 w-full rounded-2xl border-slate-200 text-sm font-bold shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($statusOptions as $status)
                                    <option value="{{ $status }}" @selected($ticket->status === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="mt-2 block rounded-2xl bg-slate-50 px-4 py-3 font-semibold text-slate-700">{{ $ticket->formattedStatus() }}</span>
                        @endif
                    </label>
                    <label class="block text-sm font-black text-slate-700">Urgency
                        @if ($isKielUser)
                            <select data-inline-field="urgency" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" class="mt-2 w-full rounded-2xl border-slate-200 text-sm font-bold shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach (\App\Models\Ticket::URGENCIES as $urgency)
                                    <option value="{{ $urgency }}" @selected($ticket->urgency === $urgency)>{{ str($urgency)->headline() }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="mt-2 block rounded-2xl bg-slate-50 px-4 py-3 font-semibold text-slate-700">{{ $ticket->formattedUrgency() }}</span>
                        @endif
                    </label>
                    <label class="block text-sm font-black text-slate-700">Assignee
                        @if ($isKielUser)
                            <select data-inline-field="assigned_to" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" class="mt-2 w-full rounded-2xl border-slate-200 text-sm font-bold shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Unassigned</option>
                                @foreach ($teamMembers as $member)
                                    <option value="{{ $member->id }}" @selected((int) $ticket->assigned_to === $member->id)>{{ $member->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="mt-2 block rounded-2xl bg-slate-50 px-4 py-3 font-semibold text-slate-700">{{ $ticket->assignee?->name ?? 'Unassigned' }}</span>
                        @endif
                    </label>
                    <div class="block text-sm font-black text-slate-700">Client
                        <span class="mt-2 block rounded-2xl bg-slate-50 px-4 py-3 font-semibold text-slate-700">{{ $ticket->client?->name ?? 'No client' }}</span>
                    </div>
                    <div class="block text-sm font-black text-slate-700">Software
                        <span class="mt-2 block rounded-2xl bg-slate-50 px-4 py-3 font-semibold text-slate-700">{{ $ticket->software?->name ?? 'No software' }}</span>
                    </div>
                    <div class="block text-sm font-black text-slate-700">Sprint
                        <span class="mt-2 block rounded-2xl bg-slate-50 px-4 py-3 font-semibold text-slate-700">{{ $latestSprint ? '#'.$latestSprint->sprint_no.' '.$latestSprint->name : 'No sprint' }}</span>
                    </div>
                    <label class="block text-sm font-black text-slate-700">Start date
                        @if ($isKielUser)
                            <input type="date" data-inline-field="start_date" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" value="{{ $ticket->start_date?->toDateString() }}" class="mt-2 w-full rounded-2xl border-slate-200 text-sm font-bold shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @else
                            <span class="mt-2 block rounded-2xl bg-slate-50 px-4 py-3 font-semibold text-slate-700">{{ $ticket->start_date?->format('M j, Y') ?? 'Not set' }}</span>
                        @endif
                    </label>
                    <label class="block text-sm font-black text-slate-700">Due date
                        @if ($isKielUser)
                            <input type="date" data-inline-field="due_date" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" value="{{ $ticket->due_date?->toDateString() }}" class="mt-2 w-full rounded-2xl border-slate-200 text-sm font-bold shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @else
                            <span class="mt-2 block rounded-2xl bg-slate-50 px-4 py-3 font-semibold text-slate-700">{{ $ticket->due_date?->format('M j, Y') ?? 'Not set' }}</span>
                        @endif
                    </label>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h4 class="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Description</h4>
                @if ($isKielUser)
                    <textarea rows="7" data-inline-field="description" data-inline-url="{{ route('tickets.inline-update', $ticket) }}" class="mt-4 w-full rounded-2xl border-slate-200 text-sm leading-6 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $ticket->description }}</textarea>
                @else
                    <p class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $ticket->description }}</p>
                @endif
            </section>

            @if ($isKielUser)
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h4 class="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Timer controls</h4>
                            <p class="mt-2 text-sm font-semibold text-slate-600">Current: {{ $timerPayload ? str($timerPayload['status'])->headline() : 'No active timer' }} · Cumulative {{ gmdate('H:i:s', $cumulativeDuration ?? 0) }}</p>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        @foreach (['start' => 'Start', 'pause' => 'Pause', 'resume' => 'Resume', 'stop' => 'Stop'] as $action => $label)
                            <button type="button" data-drawer-action="timer" data-action-url="{{ route('tickets.timer.'.$action, $ticket) }}" class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-black text-white shadow-soft transition hover:bg-indigo-700">{{ $label }}</button>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h4 class="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Block controls</h4>
                    <div class="mt-4 grid gap-3">
                        @unless ($activeBlock)
                            <form data-drawer-action-form="block" action="{{ route('tickets.block', $ticket) }}" class="space-y-3">
                                <textarea name="reason" rows="3" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Reason for blocking"></textarea>
                                <button type="submit" class="rounded-2xl bg-rose-600 px-4 py-2 text-sm font-black text-white shadow-soft">Block ticket</button>
                            </form>
                        @endunless
                        @if ($activeBlock)
                            <form data-drawer-action-form="unblock" action="{{ route('tickets.unblock', $ticket) }}" class="space-y-3 border-t border-slate-100 pt-3">
                                <textarea name="unblock_note" rows="3" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Unblock note"></textarea>
                                <button type="submit" class="rounded-2xl bg-emerald-600 px-4 py-2 text-sm font-black text-white shadow-soft">Unblock ticket</button>
                            </form>
                        @endif
                    </div>
                </section>
            @endif

            @if ($canRecommend)
                <section class="rounded-3xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm">
                    <h4 class="text-sm font-black uppercase tracking-[0.2em] text-indigo-700">Client planning</h4>
                    <p class="mt-2 text-sm font-semibold text-indigo-900">Recommend this feature for the next planning cycle.</p>
                    <button type="button" data-drawer-action="recommend" data-action-url="{{ route('features.recommend', $ticket) }}" class="mt-4 rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-soft">Recommend feature</button>
                </section>
            @endif

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h4 class="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Comments</h4>
                <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" data-drawer-comment-form class="mt-4 space-y-3">
                    @csrf
                    <textarea name="comment" rows="4" required class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Add a comment"></textarea>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        @if ($isKielUser)
                            <label class="flex items-center gap-2 text-sm font-bold text-slate-600"><input type="checkbox" name="is_internal" value="1" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">Internal</label>
                        @endif
                        <button type="submit" class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-soft">Add comment</button>
                    </div>
                </form>
                <div class="mt-5" data-drawer-comments>
                    @include('tickets.partials.comments', ['comments' => $comments, 'ticket' => $ticket, 'isKielUser' => $isKielUser])
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h4 class="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Activity timeline</h4>
                <div class="mt-5 border-l border-slate-200 pl-2" data-drawer-activity>
                    @include('tickets.partials.activity-timeline', ['activities' => $ticket->activities])
                </div>
            </section>
        </div>
    </div>
@else
    <div data-global-ticket-drawer data-ticket-drawer data-timeline-drawer class="fixed inset-0 z-50 hidden" aria-hidden="true">
        <button type="button" data-ticket-drawer-backdrop class="absolute inset-0 bg-slate-950/40 opacity-0 transition-opacity duration-200" aria-label="Close task drawer"></button>
        <aside data-ticket-drawer-panel class="absolute inset-y-0 right-0 w-full max-w-2xl translate-x-full overflow-hidden border-l border-slate-200 bg-white shadow-2xl transition-transform duration-300 ease-out">
            <div data-ticket-drawer-loading class="flex h-full items-center justify-center p-8 text-sm font-black uppercase tracking-[0.25em] text-slate-400">Loading task…</div>
            <div data-ticket-drawer-body class="h-full"></div>
        </aside>
    </div>

    <script>
        window.ticketDrawer = window.ticketDrawer || (() => {
            const csrf = @js(csrf_token());
            let root, panel, backdrop, body, loading, currentUrl;

            const bind = () => {
                root = document.querySelector('[data-global-ticket-drawer]');
                panel = root?.querySelector('[data-ticket-drawer-panel]');
                backdrop = root?.querySelector('[data-ticket-drawer-backdrop]');
                body = root?.querySelector('[data-ticket-drawer-body]');
                loading = root?.querySelector('[data-ticket-drawer-loading]');
                backdrop?.addEventListener('click', close);
                document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
                document.addEventListener('click', event => {
                    const trigger = event.target.closest('[data-ticket-drawer-url]');
                    if (!trigger) return;
                    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                    event.preventDefault();
                    open(trigger.dataset.ticketDrawerUrl);
                });
                root?.addEventListener('click', handleClick);
                root?.addEventListener('change', handleInlineChange);
                root?.addEventListener('blur', handleInlineBlur, true);
                root?.addEventListener('submit', handleSubmit);
            };

            const open = async (url) => {
                currentUrl = url;
                root?.classList.remove('hidden');
                root?.setAttribute('aria-hidden', 'false');
                requestAnimationFrame(() => {
                    panel?.classList.remove('translate-x-full');
                    backdrop?.classList.remove('opacity-0');
                    backdrop?.classList.add('opacity-100');
                });
                await load(url);
            };

            const close = () => {
                if (!root || root.classList.contains('hidden')) return;
                panel?.classList.add('translate-x-full');
                backdrop?.classList.add('opacity-0');
                backdrop?.classList.remove('opacity-100');
                window.setTimeout(() => {
                    root.classList.add('hidden');
                    root.setAttribute('aria-hidden', 'true');
                    if (body) body.innerHTML = '';
                }, 260);
            };

            const load = async (url = currentUrl) => {
                if (!url) return;
                loading?.classList.remove('hidden');
                if (body) body.innerHTML = '';
                try {
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    const payload = await response.json();
                    if (!response.ok) throw new Error(payload.message || 'Task drawer could not load.');
                    if (body) {
                        body.innerHTML = payload.html;
                        body.querySelectorAll('[data-inline-field]').forEach(element => { element.dataset.originalValue = fieldValue(element); });
                    }
                } catch (error) {
                    if (body) body.innerHTML = `<div class="p-6"><div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm font-bold text-rose-800">${error.message || 'Task drawer could not load.'}</div></div>`;
                } finally {
                    loading?.classList.add('hidden');
                }
            };

            const flash = (message, type = 'success') => {
                const box = root?.querySelector('[data-drawer-message]');
                if (!box) return;
                box.textContent = message;
                box.className = `rounded-2xl border px-4 py-3 text-sm font-bold ${type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`;
                box.classList.remove('hidden');
            };

            const fieldValue = element => element.type === 'checkbox' ? (element.checked ? '1' : '0') : element.value;
            const saveInline = async (element) => {
                if (!element.dataset.inlineField || element.dataset.originalValue === fieldValue(element)) return;
                element.dataset.originalValue = fieldValue(element);
                try {
                    const response = await fetch(element.dataset.inlineUrl, {
                        method: 'PATCH',
                        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify({ field: element.dataset.inlineField, value: fieldValue(element) }),
                    });
                    const payload = await response.json();
                    if (!response.ok) throw new Error(payload.errors?.[element.dataset.inlineField]?.[0] || payload.message || 'Unable to save field.');
                    flash(payload.message || 'Task updated.');
                    await load();
                } catch (error) {
                    flash(error.message || 'Unable to save field.', 'error');
                }
            };
            const handleInlineBlur = event => {
                if (event.target.matches('input[data-inline-field], textarea[data-inline-field]')) saveInline(event.target);
            };
            const handleInlineChange = event => {
                if (event.target.matches('select[data-inline-field], input[type="date"][data-inline-field]')) saveInline(event.target);
            };

            const handleClick = async (event) => {
                if (event.target.closest('[data-ticket-drawer-close]')) close();
                const button = event.target.closest('[data-drawer-action]');
                if (!button) return;
                const action = button.dataset.drawerAction;
                try {
                    const response = await fetch(button.dataset.actionUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(payload.message || 'Action failed.');
                    flash(payload.message || (action === 'recommend' ? 'Feature recommended.' : 'Action complete.'));
                    await load();
                } catch (error) {
                    flash(error.message || 'Action failed.', 'error');
                }
            };

            const handleSubmit = async (event) => {
                const form = event.target.closest('[data-drawer-comment-form], [data-drawer-action-form]');
                if (!form) return;
                event.preventDefault();
                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: new FormData(form),
                    });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(payload.message || 'Unable to save.');
                    flash(payload.message || 'Saved.');
                    form.reset();
                    await load();
                } catch (error) {
                    flash(error.message || 'Unable to save.', 'error');
                }
            };

            document.addEventListener('DOMContentLoaded', bind);
            if (document.readyState !== 'loading') bind();

            return { open, close, load };
        })();
    </script>
@endif
