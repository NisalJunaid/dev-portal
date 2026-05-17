<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt/dist/frappe-gantt.css">
    <script src="https://cdn.jsdelivr.net/npm/frappe-gantt/dist/frappe-gantt.umd.js" defer></script>

    <style>
        .gantt .bar.timeline-urgency-critical, .gantt .timeline-urgency-critical .bar,
        .gantt .bar.timeline-urgency-critical-blocked, .gantt .timeline-urgency-critical-blocked .bar,
        .gantt .bar.timeline-urgency-critical-overdue, .gantt .timeline-urgency-critical-overdue .bar { fill: #e11d48; }

        .gantt .bar.timeline-urgency-high, .gantt .timeline-urgency-high .bar,
        .gantt .bar.timeline-urgency-high-blocked, .gantt .timeline-urgency-high-blocked .bar,
        .gantt .bar.timeline-urgency-high-overdue, .gantt .timeline-urgency-high-overdue .bar { fill: #f97316; }

        .gantt .bar.timeline-urgency-medium, .gantt .timeline-urgency-medium .bar,
        .gantt .bar.timeline-urgency-medium-blocked, .gantt .timeline-urgency-medium-blocked .bar,
        .gantt .bar.timeline-urgency-medium-overdue, .gantt .timeline-urgency-medium-overdue .bar { fill: #4f46e5; }

        .gantt .bar.timeline-urgency-low, .gantt .timeline-urgency-low .bar,
        .gantt .bar.timeline-urgency-low-blocked, .gantt .timeline-urgency-low-blocked .bar,
        .gantt .bar.timeline-urgency-low-overdue, .gantt .timeline-urgency-low-overdue .bar { fill: #0f766e; }

        .gantt .bar.timeline-urgency-critical-overdue, .gantt .timeline-urgency-critical-overdue .bar,
        .gantt .bar.timeline-urgency-high-overdue, .gantt .timeline-urgency-high-overdue .bar,
        .gantt .bar.timeline-urgency-medium-overdue, .gantt .timeline-urgency-medium-overdue .bar,
        .gantt .bar.timeline-urgency-low-overdue, .gantt .timeline-urgency-low-overdue .bar { stroke: #be123c; stroke-width: 3px; }

        .gantt .bar.timeline-urgency-critical-blocked, .gantt .timeline-urgency-critical-blocked .bar,
        .gantt .bar.timeline-urgency-high-blocked, .gantt .timeline-urgency-high-blocked .bar,
        .gantt .bar.timeline-urgency-medium-blocked, .gantt .timeline-urgency-medium-blocked .bar,
        .gantt .bar.timeline-urgency-low-blocked, .gantt .timeline-urgency-low-blocked .bar { stroke: #0f172a; stroke-width: 3px; stroke-dasharray: 6 3; }

        .gantt .bar-label { font-weight: 800; }
    </style>

    <div
        x-data="timelineView({
            dataUrl: @js(route('timeline.data')),
            dateUrlTemplate: @js(route('timeline.tasks.dates', ['ticket' => '__TICKET__'])),
            dependencyUrlTemplate: @js(route('timeline.tasks.dependency', ['ticket' => '__TICKET__'])),
            drawerUrlTemplate: @js(route('tickets.drawer', ['ticket' => '__TICKET__'])),
            ticketOptions: [],
            canEdit: @js($canEdit),
        })"
        x-init="init()"
        class="space-y-6"
        data-timeline-view
        data-can-edit="{{ $canEdit ? 'true' : 'false' }}"
    >
        <section class="card flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.25em] text-indigo-600">Asana-style planning</p>
                <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Timeline view</h2>
                <p class="mt-2 max-w-3xl text-slate-600">Plan scheduled tickets as horizontal Gantt bars, review blockers and dependencies, and drag or resize tasks to update start and due dates.</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white px-5 py-4 text-sm shadow-sm">
                <p class="font-black text-slate-900" x-text="canEdit ? 'Editing enabled' : 'Read-only timeline'"></p>
                <p class="mt-1 text-slate-500" x-text="canEdit ? 'Kiel users can drag, resize, and save dependencies.' : 'Clients can view the plan; date and dependency edits are disabled.'"></p>
            </div>
        </section>

        <section class="card space-y-4" data-timeline-filters>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h3 class="text-lg font-black text-slate-950">Filters</h3>
                    <p class="mt-1 text-sm text-slate-500">Narrow the timeline by client, software, sprint, assignee, urgency, or status.</p>
                </div>
                <button type="button" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm font-black text-slate-600 transition hover:border-indigo-200 hover:text-indigo-700" @click="clearFilters">Clear filters</button>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <label class="space-y-2 text-sm font-bold text-slate-700">
                    <span>Client</span>
                    <select x-model="filters.client_id" @change="load" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All clients</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-2 text-sm font-bold text-slate-700">
                    <span>Software</span>
                    <select x-model="filters.software_id" @change="load" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All software</option>
                        @foreach ($softwares as $software)
                            <option value="{{ $software->id }}">{{ $software->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-2 text-sm font-bold text-slate-700">
                    <span>Sprint</span>
                    <select x-model="filters.sprint_id" @change="load" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All sprints</option>
                        @foreach ($sprints as $sprint)
                            <option value="{{ $sprint->id }}">#{{ $sprint->sprint_no }} · {{ $sprint->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-2 text-sm font-bold text-slate-700">
                    <span>Assignee</span>
                    <select x-model="filters.assigned_to" @change="load" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All assignees</option>
                        <option value="unassigned">Unassigned</option>
                        @foreach ($assignees as $assignee)
                            <option value="{{ $assignee->id }}">{{ $assignee->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-2 text-sm font-bold text-slate-700">
                    <span>Urgency</span>
                    <select x-model="filters.urgency" @change="load" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All urgency</option>
                        @foreach ($urgencies as $urgency)
                            <option value="{{ $urgency }}">{{ str($urgency)->headline() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-2 text-sm font-bold text-slate-700">
                    <span>Status</span>
                    <select x-model="filters.status" @change="load" class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}">{{ str($status)->replace('_', ' ')->headline() }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </section>

        <section class="card relative overflow-hidden" data-timeline-chart-area>
            <div x-show="toast.message" x-transition class="mb-4 rounded-2xl border px-5 py-4 text-sm font-bold" :class="toast.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'" x-text="toast.message" data-timeline-toast></div>

            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-xl font-black text-slate-950">Scheduled tasks</h3>
                    <p class="mt-1 text-sm text-slate-500"><span x-text="tasks.length"></span> tasks with both start and due dates.</p>
                </div>
                <div class="flex items-center gap-2 text-xs font-black uppercase tracking-[0.15em] text-slate-500">
                    <span class="h-3 w-3 rounded-full bg-rose-600"></span> Critical
                    <span class="ml-3 h-3 w-3 rounded-full bg-orange-500"></span> High
                    <span class="ml-3 h-3 w-3 rounded-full bg-indigo-600"></span> Medium
                    <span class="ml-3 h-3 w-3 rounded-full bg-teal-700"></span> Low
                </div>
            </div>

            <div x-show="loading" x-transition.opacity class="absolute inset-0 z-10 flex items-center justify-center bg-white/75 backdrop-blur-sm" data-timeline-loading>
                <div class="rounded-3xl border border-slate-200 bg-white px-6 py-5 text-center shadow-soft">
                    <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-indigo-100 border-t-indigo-600"></div>
                    <p class="mt-3 text-sm font-black text-slate-700">Loading timeline…</p>
                </div>
            </div>

            <div x-show="!loading && tasks.length === 0" class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-12 text-center" data-timeline-empty>
                <p class="text-lg font-black text-slate-900">No scheduled tasks match these filters.</p>
                <p class="mt-2 text-sm text-slate-500">Add both a start date and due date to tickets, or clear filters to expand the timeline.</p>
            </div>

            <div x-show="tasks.length > 0" class="overflow-x-auto rounded-3xl border border-slate-200 bg-white motion-safe:transition-opacity motion-safe:duration-300" :class="loading ? 'opacity-50' : 'opacity-100'">
                <div id="timeline-gantt" class="min-h-[28rem] min-w-[720px] p-4 md:min-h-[34rem] lg:min-w-[960px]" data-timeline-chart></div>
            </div>
        </section>

        <aside x-show="drawerOpen" x-transition.opacity class="fixed inset-y-0 right-0 z-50 w-full max-w-xl border-l border-slate-200 bg-white shadow-2xl" data-timeline-drawer>
            <div class="flex h-full flex-col" x-show="selectedTask">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                    <div>
                        <p class="text-sm font-black uppercase tracking-[0.2em] text-indigo-600" x-text="selectedTask?.ticket.ticket_no"></p>
                        <h3 class="mt-2 text-2xl font-black text-slate-950" x-text="selectedTask?.ticket.title"></h3>
                    </div>
                    <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-600" @click="closeDrawer">Close</button>
                </div>
                <div class="flex-1 space-y-5 overflow-y-auto p-6 text-sm">
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase tracking-wide text-slate-700" x-text="selectedTask?.ticket.status_label"></span>
                        <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-black uppercase tracking-wide text-indigo-700" x-text="selectedTask?.ticket.urgency_label"></span>
                        <span x-show="selectedTask?.ticket.blocked" class="rounded-full bg-slate-900 px-3 py-1 text-xs font-black uppercase tracking-wide text-white">Blocked</span>
                        <span x-show="selectedTask?.ticket.overdue" class="rounded-full bg-rose-100 px-3 py-1 text-xs font-black uppercase tracking-wide text-rose-700">Overdue</span>
                    </div>
                    <dl class="grid grid-cols-2 gap-3">
                        <div class="rounded-2xl bg-slate-50 p-4"><dt class="font-black text-slate-400">Assignee</dt><dd class="mt-1 font-bold text-slate-800" x-text="selectedTask?.ticket.assignee"></dd></div>
                        <div class="rounded-2xl bg-slate-50 p-4"><dt class="font-black text-slate-400">Client</dt><dd class="mt-1 font-bold text-slate-800" x-text="selectedTask?.ticket.client"></dd></div>
                        <div class="rounded-2xl bg-slate-50 p-4"><dt class="font-black text-slate-400">Software</dt><dd class="mt-1 font-bold text-slate-800" x-text="selectedTask?.ticket.software"></dd></div>
                        <div class="rounded-2xl bg-slate-50 p-4"><dt class="font-black text-slate-400">Dependency</dt><dd class="mt-1 font-bold text-slate-800" x-text="selectedTask?.ticket.dependency_label || 'None'"></dd></div>
                        <div class="rounded-2xl bg-slate-50 p-4"><dt class="font-black text-slate-400">Start date</dt><dd class="mt-1 font-bold text-slate-800" x-text="selectedTask?.ticket.start_date"></dd></div>
                        <div class="rounded-2xl bg-slate-50 p-4"><dt class="font-black text-slate-400">Due date</dt><dd class="mt-1 font-bold text-slate-800" x-text="selectedTask?.ticket.due_date"></dd></div>
                    </dl>

                    <div class="rounded-3xl border border-slate-200 p-4" x-show="canEdit">
                        <label class="text-sm font-black text-slate-700" for="dependency-select">Dependency</label>
                        <p class="mt-1 text-xs text-slate-500">Save a predecessor ticket. Circular dependencies are rejected.</p>
                        <select id="dependency-select" x-model="dependencyDraft" class="mt-3 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">No dependency</option>
                            <template x-for="task in tasks" :key="task.id">
                                <option :value="task.id" x-show="task.id !== selectedTask?.id" x-text="task.name"></option>
                            </template>
                        </select>
                        <button type="button" class="mt-3 rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-soft disabled:opacity-50" :disabled="savingDependency" @click="saveDependency" data-save-dependency>
                            <span x-text="savingDependency ? 'Saving…' : 'Save dependency'"></span>
                        </button>
                    </div>

                    <a :href="selectedTask?.ticket.show_url" class="inline-flex rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black text-white shadow-soft">Open full ticket</a>
                </div>
            </div>
        </aside>
        <button type="button" x-show="drawerOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/30" @click="closeDrawer" aria-label="Close timeline drawer"></button>
    </div>


    <script>
        function timelineView(config) {
            return {
                dataUrl: config.dataUrl,
                dateUrlTemplate: config.dateUrlTemplate,
                dependencyUrlTemplate: config.dependencyUrlTemplate,
                drawerUrlTemplate: config.drawerUrlTemplate,
                canEdit: config.canEdit,
                gantt: null,
                tasks: [],
                filters: { client_id: '', software_id: '', sprint_id: '', assigned_to: '', urgency: '', status: '' },
                loading: true,
                drawerOpen: false,
                selectedTask: null,
                dependencyDraft: '',
                savingDependency: false,
                toast: { type: 'success', message: '' },
                init() {
                    this.waitForGantt().then(() => this.load()).catch(() => {
                        this.loading = false;
                        this.showToast('Timeline library could not load. Please refresh and try again.', 'error');
                    });
                },
                waitForGantt() {
                    return new Promise((resolve, reject) => {
                        let attempts = 0;
                        const timer = setInterval(() => {
                            attempts++;
                            if (window.Gantt) {
                                clearInterval(timer);
                                resolve();
                            } else if (attempts > 80) {
                                clearInterval(timer);
                                reject();
                            }
                        }, 100);
                    });
                },
                async load() {
                    this.loading = true;
                    const params = new URLSearchParams(Object.entries(this.filters).filter(([, value]) => value !== ''));
                    try {
                        const payload = await window.Kiel.request(`${this.dataUrl}?${params.toString()}`);
                        this.tasks = payload.tasks || [];
                        this.render();
                    } catch (error) {
                        this.showToast(error.message || 'Timeline data failed to load.', 'error');
                    } finally {
                        this.loading = false;
                    }
                },
                render() {
                    const element = document.getElementById('timeline-gantt');
                    if (!element || !window.Gantt) return;
                    element.innerHTML = '';
                    if (!this.tasks.length) return;
                    this.gantt = new window.Gantt(element, this.tasks, {
                        view_mode: 'Week',
                        date_format: 'YYYY-MM-DD',
                        readonly: !this.canEdit,
                        bar_height: 28,
                        padding: 24,
                        on_click: task => this.openDrawer(task),
                        on_date_change: (task, start, end) => this.saveDates(task, start, end),
                        custom_popup_html: task => this.popupHtml(task),
                    });
                },
                popupHtml(task) {
                    const ticket = task.ticket || {};
                    return `<div class="rounded-2xl bg-white p-4 text-sm shadow-2xl">
                        <p class="font-black text-indigo-600">${ticket.ticket_no || ''}</p>
                        <p class="mt-1 font-black text-slate-950">${ticket.title || task.name}</p>
                        <p class="mt-2 text-slate-600">${ticket.assignee || 'Unassigned'} · ${ticket.status_label || ''} · ${ticket.urgency_label || ''}</p>
                        ${ticket.blocked ? '<p class="mt-2 font-black text-slate-900">Blocked</p>' : ''}
                        ${ticket.overdue ? '<p class="mt-2 font-black text-rose-600">Overdue</p>' : ''}
                    </div>`;
                },
                async saveDates(task, start, end) {
                    if (!this.canEdit) {
                        this.showToast('This timeline is read only.', 'error');
                        this.render();
                        return;
                    }
                    try {
                        const payload = await window.Kiel.request(this.dateUrlTemplate.replace('__TICKET__', task.id), {
                            method: 'PATCH',
                            body: JSON.stringify({ start_date: this.formatDate(start), due_date: this.formatDate(end) }),
                        });
                        this.replaceTask(payload.task);
                        this.showToast('Timeline dates saved.', 'success');
                    } catch (error) {
                        this.showToast(error.message || 'Timeline date save failed.', 'error');
                        await this.load();
                    }
                },
                async saveDependency() {
                    if (!this.selectedTask || !this.canEdit) return;
                    this.savingDependency = true;
                    try {
                        const payload = await window.Kiel.request(this.dependencyUrlTemplate.replace('__TICKET__', this.selectedTask.id), {
                            method: 'PATCH',
                            body: JSON.stringify({ depends_on_ticket_id: this.dependencyDraft || null }),
                        });
                        this.replaceTask(payload.task);
                        this.openDrawer(payload.task);
                        this.render();
                        this.showToast('Timeline dependency saved.', 'success');
                    } catch (error) {
                        this.showToast(error.message || 'Dependency save failed.', 'error');
                    } finally {
                        this.savingDependency = false;
                    }
                },
                replaceTask(task) {
                    this.tasks = this.tasks.map(existing => existing.id === task.id ? task : existing);
                    this.selectedTask = this.tasks.find(existing => existing.id === task.id) || task;
                },
                openDrawer(task) {
                    this.selectedTask = task;
                    this.dependencyDraft = task?.ticket?.dependency_id ? String(task.ticket.dependency_id) : '';
                    this.drawerOpen = false;
                    window.ticketDrawer?.open(this.drawerUrlTemplate.replace('__TICKET__', task.id));
                },
                closeDrawer() {
                    this.drawerOpen = false;
                    this.selectedTask = null;
                },
                clearFilters() {
                    this.filters = { client_id: '', software_id: '', sprint_id: '', assigned_to: '', urgency: '', status: '' };
                    this.load();
                },
                formatDate(value) {
                    const date = new Date(value);
                    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
                },
                showToast(message, type = 'success') {
                    this.toast = { message, type };
                    window.Kiel?.toast(message, type);
                    setTimeout(() => {
                        if (this.toast.message === message) this.toast.message = '';
                    }, 5000);
                },
            };
        }
    </script>
