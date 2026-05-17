<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt/dist/frappe-gantt.css">
<script src="https://cdn.jsdelivr.net/npm/frappe-gantt/dist/frappe-gantt.umd.js" defer></script>

<style>
.gantt .bar.timeline-urgency-critical,.gantt .timeline-urgency-critical .bar,.gantt .bar.timeline-urgency-critical-blocked,.gantt .timeline-urgency-critical-blocked .bar,.gantt .bar.timeline-urgency-critical-overdue,.gantt .timeline-urgency-critical-overdue .bar{fill:#e11d48}
.gantt .bar.timeline-urgency-high,.gantt .timeline-urgency-high .bar,.gantt .bar.timeline-urgency-high-blocked,.gantt .timeline-urgency-high-blocked .bar,.gantt .bar.timeline-urgency-high-overdue,.gantt .timeline-urgency-high-overdue .bar{fill:#f97316}
.gantt .bar.timeline-urgency-medium,.gantt .timeline-urgency-medium .bar,.gantt .bar.timeline-urgency-medium-blocked,.gantt .timeline-urgency-medium-blocked .bar,.gantt .bar.timeline-urgency-medium-overdue,.gantt .timeline-urgency-medium-overdue .bar{fill:#4f46e5}
.gantt .bar.timeline-urgency-low,.gantt .timeline-urgency-low .bar,.gantt .bar.timeline-urgency-low-blocked,.gantt .timeline-urgency-low-blocked .bar,.gantt .bar.timeline-urgency-low-overdue,.gantt .timeline-urgency-low-overdue .bar{fill:#0f766e}
.gantt .bar-label{font-weight:800}
</style>

<div x-data="timelineView({ dataUrl: @js(route('timeline.data')), dateUrlTemplate: @js(route('timeline.tasks.dates', ['ticket' => '__TICKET__'])), dependencyUrlTemplate: @js(route('timeline.tasks.dependency', ['ticket' => '__TICKET__'])), drawerUrlTemplate: @js(route('tickets.drawer', ['ticket' => '__TICKET__'])), canEdit: @js($canEdit) })" x-init="init()" class="h-full min-h-0" data-timeline-view>
<section class="asana-panel relative flex h-full min-h-0 flex-col overflow-hidden rounded-none" data-timeline-chart-area>
<div class="mb-3 flex items-center justify-end"><p class="text-sm text-slate-500"><span x-text="tasks.length"></span> tasks</p></div>
<div x-show="loading" x-transition.opacity class="absolute inset-0 z-10 flex items-center justify-center bg-white/75"><p class="text-sm font-black text-slate-700">Loading timeline…</p></div>
<div x-show="!loading && tasks.length===0" class="flex-1 min-h-0 flex items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center text-sm font-bold text-slate-600">No scheduled tasks match these filters.</div>
<div x-show="tasks.length > 0" class="flex-1 min-h-0 overflow-auto border border-slate-200 bg-white sleek-scrollbar" :class="loading ? 'opacity-50' : 'opacity-100'"><div id="timeline-gantt" class="h-full min-h-[40rem] min-w-[960px] p-4" data-timeline-chart></div></div>
</section>
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
                    const params=new URLSearchParams(window.location.search);['client_id','software_id','sprint_id','assigned_to','urgency','status'].forEach(k=>{this.filters[k]=params.get(k)||''}); this.waitForGantt().then(() => this.load()).catch(() => {
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
