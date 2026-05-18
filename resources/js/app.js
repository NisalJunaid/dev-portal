import './bootstrap';

import Alpine from 'alpinejs';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const parseResponse = async (response) => {
    const contentType = response.headers.get('content-type') || '';

    if (contentType.includes('application/json')) {
        return response.json();
    }

    return { html: await response.text() };
};

const errorMessage = (payload, fallback = 'Something went wrong. Please try again.') => {
    if (payload?.errors) {
        const firstField = Object.keys(payload.errors)[0];
        const firstError = payload.errors[firstField]?.[0];

        if (firstError) return firstError;
    }

    return payload?.message || fallback;
};

const request = async (url, options = {}) => {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', headers.get('Accept') || 'application/json');

    const hasBody = options.body !== undefined && !(options.body instanceof FormData);
    if (hasBody && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json');
    }

    const method = (options.method || 'GET').toUpperCase();
    if (!['GET', 'HEAD'].includes(method) && !headers.has('X-CSRF-TOKEN')) {
        headers.set('X-CSRF-TOKEN', csrfToken());
    }

    const response = await fetch(url, { ...options, headers });
    const payload = await parseResponse(response).catch(() => ({}));

    if (!response.ok) {
        const error = new Error(errorMessage(payload));
        error.response = response;
        error.payload = payload;
        throw error;
    }

    return payload;
};

const toast = (message, type = 'success') => {
    window.dispatchEvent(new CustomEvent('kiel:toast', { detail: { message, type } }));
};

const confirm = (options = {}) => new Promise((resolve) => {
    window.dispatchEvent(new CustomEvent('kiel:confirm', {
        detail: {
            title: options.title || 'Confirm action',
            message: options.message || 'Are you sure you want to continue?',
            confirmLabel: options.confirmLabel || 'Confirm',
            cancelLabel: options.cancelLabel || 'Cancel',
            tone: options.tone || 'danger',
            resolve,
        },
    }));
});

const setLoading = (element, loading = true, label = 'Saving…') => {
    if (!element) return;

    if (loading) {
        if (!element.dataset.originalHtml) element.dataset.originalHtml = element.innerHTML;
        element.disabled = true;
        element.setAttribute('aria-busy', 'true');
        element.classList.add('opacity-70', 'pointer-events-none');
        element.innerHTML = `<span class="inline-flex items-center gap-2"><span class="h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent"></span>${label}</span>`;
    } else {
        element.disabled = false;
        element.removeAttribute('aria-busy');
        element.classList.remove('opacity-70', 'pointer-events-none');
        if (element.dataset.originalHtml) {
            element.innerHTML = element.dataset.originalHtml;
            delete element.dataset.originalHtml;
        }
    }
};


const taskColumnDefinitions = [
    { key: 'ticket_no', label: 'Ticket #', min: 120, defaultWidth: 140, max: 600 },
    { key: 'title', label: 'Title', min: 260, defaultWidth: 320, max: 900 },
    { key: 'type', label: 'Type', min: 120, defaultWidth: 130, max: 600 },
    { key: 'urgency', label: 'Urgency', min: 140, defaultWidth: 150, max: 600 },
    { key: 'status', label: 'Status', min: 160, defaultWidth: 180, max: 600 },
    { key: 'assigned_to', label: 'Assigned to', min: 180, defaultWidth: 200, max: 600 },
    { key: 'client', label: 'Client', min: 180, defaultWidth: 180, max: 600 },
    { key: 'software', label: 'Software', min: 180, defaultWidth: 180, max: 600 },
    { key: 'start_date', label: 'Start date', min: 150, defaultWidth: 160, max: 600 },
    { key: 'due_date', label: 'Due date', min: 150, defaultWidth: 160, max: 600 },
    { key: 'sprint', label: 'Sprint cycle', min: 200, defaultWidth: 220, max: 700 },
    { key: 'blocked', label: 'Blocked', min: 120, defaultWidth: 130, max: 400 },
    { key: 'updated_at', label: 'Last updated', min: 180, defaultWidth: 200, max: 700 },
];
window.Kiel = { csrfToken, request, toast, confirm, setLoading, errorMessage, taskColumnsConfig: taskColumnDefinitions };
window.KielTasks = {
    dirtyViews: new Set(),
    emitTaskUpdated(ticket) { window.dispatchEvent(new CustomEvent('kiel:task-updated', { detail: { ticket } })); },
    emitUpdated(ticket) { this.emitTaskUpdated(ticket); },
    emitTaskCreated(ticket) { window.dispatchEvent(new CustomEvent('kiel:task-created', { detail: { ticket } })); },
    emitCreated(ticket) { this.emitTaskCreated(ticket); },
    emitRemoved(ticketId, reason = '') { window.dispatchEvent(new CustomEvent('kiel:task-removed', { detail: { ticketId, reason } })); },
    markDirty(view) { this.dirtyViews.add(view); },
    clearDirty(view) { this.dirtyViews.delete(view); },
    isDirty(view) { return this.dirtyViews.has(view); },
};

const listSectionForTicket = (ticket = {}) => {
    if (ticket.list_section) return ticket.list_section;
    if (['bug_completed', 'feature_completed', 'task_completed'].includes(ticket.status)) return 'completed';
    if (ticket.status === 'in_progress') return 'in_progress';
    return 'backlog';
};


const bindAjaxActions = () => {
    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-ajax-action]');
        if (!form) return;

        event.preventDefault();

        if (form.dataset.confirmTitle) {
            const confirmed = await confirm({
                title: form.dataset.confirmTitle,
                message: form.dataset.confirmMessage,
                confirmLabel: form.dataset.confirmLabel,
            });
            if (!confirmed) return;
        }

        const submitter = form.querySelector('[type="submit"]');
        setLoading(submitter, true, form.dataset.savingLabel || 'Saving…');

        try {
            const payload = await request(form.action, {
                method: (form.dataset.method || form.method || 'POST').toUpperCase(),
                body: new FormData(form),
            });
            toast(payload.message || form.dataset.successMessage || 'Saved.');

            if (form.dataset.removeOnSuccess) {
                form.closest(form.dataset.removeOnSuccess)?.remove();
            }

            if (form.dataset.replaceWithStatus) {
                form.outerHTML = `<span class="badge badge-status">${payload.ticket?.status_label || 'Updated'}</span>`;
            }
        } catch (error) {
            toast(error.message || 'Unable to save.', 'error');
        } finally {
            setLoading(submitter, false);
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindAjaxActions, { once: true });
} else {
    bindAjaxActions();
}


window.Alpine = Alpine;

Alpine.data('toastCenter', () => ({
    toasts: [],
    init() {
        window.addEventListener('kiel:toast', (event) => this.push(event.detail.message, event.detail.type));
    },
    push(message, type = 'success') {
        const id = Date.now() + Math.random();
        this.toasts.push({ id, message, type });
        setTimeout(() => this.remove(id), 5000);
    },
    remove(id) {
        this.toasts = this.toasts.filter((toastItem) => toastItem.id !== id);
    },
    toneClasses(type) {
        return {
            success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
            error: 'border-rose-200 bg-rose-50 text-rose-800',
            warning: 'border-amber-200 bg-amber-50 text-amber-800',
            info: 'border-indigo-200 bg-indigo-50 text-indigo-800',
        }[type] || 'border-slate-200 bg-white text-slate-800';
    },
}));

Alpine.data('confirmModal', () => ({
    open: false,
    title: 'Confirm action',
    message: '',
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    tone: 'danger',
    resolver: null,
    init() {
        window.addEventListener('kiel:confirm', (event) => {
            Object.assign(this, event.detail);
            this.resolver = event.detail.resolve;
            this.open = true;
            this.$nextTick(() => this.$refs.confirmButton?.focus());
        });
    },
    answer(value) {
        this.open = false;
        this.resolver?.(value);
        this.resolver = null;
    },
    confirmClasses() {
        return this.tone === 'danger'
            ? 'bg-rose-600 text-white hover:bg-rose-700'
            : 'bg-indigo-600 text-white hover:bg-indigo-700';
    },
}));


Alpine.store('taskColumns', {
    columns: taskColumnDefinitions,
    visible: {}, widths: {}, initialized: false,
    init() { if (this.initialized) return; this.initialized = true; const v = JSON.parse(localStorage.getItem('kiel.tasks.list.columns.visible') || '{}'); const w = JSON.parse(localStorage.getItem('kiel.tasks.list.columns.widths') || '{}'); this.columns.forEach((c) => { this.visible[c.key] = v[c.key] !== false; this.widths[c.key] = Math.max(c.min, Math.min(Number(w[c.key] || c.defaultWidth), c.max || 600)); }); },
    isVisible(key){ return this.visible[key] !== false; },
    toggle(key){ this.visible[key]=!this.isVisible(key); localStorage.setItem('kiel.tasks.list.columns.visible', JSON.stringify(this.visible)); },
    reset(){ this.columns.forEach((c)=>{ this.visible[c.key]=true; this.widths[c.key]=c.defaultWidth;}); localStorage.setItem('kiel.tasks.list.columns.visible', JSON.stringify(this.visible)); localStorage.setItem('kiel.tasks.list.columns.widths', JSON.stringify(this.widths)); },
    width(key){ return this.widths[key] || 180; }, hiddenCount(){ return this.columns.filter((c)=>!this.isVisible(c.key)).length; },
    startResize(event,key){ const c=this.columns.find((x)=>x.key===key); if(!c) return; const startX=event.clientX,startW=this.width(key); document.body.classList.add('is-column-resizing'); const move=(e)=>{ const next=Math.max(c.min, Math.min(startW+(e.clientX-startX), c.max||600)); this.widths[key]=Math.round(next); }; const up=()=>{ document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', up); document.body.classList.remove('is-column-resizing'); localStorage.setItem('kiel.tasks.list.columns.widths', JSON.stringify(this.widths)); }; document.addEventListener('mousemove', move); document.addEventListener('mouseup', up); },
});


Alpine.store('kanbanColumns', {
    columns: [], visible: {}, initialized: false,
    init(columns = []) {
        const normalized = Array.isArray(columns) ? columns : [];
        const saved = JSON.parse(localStorage.getItem('kiel.tasks.board.columns.visible') || '{}');
        this.columns = normalized;
        const nextVisible = {};
        this.columns.forEach((column) => {
            nextVisible[column.key] = saved[column.key] !== false;
        });
        this.visible = nextVisible;
        this.initialized = true;
    },
    isVisible(key) { return this.visible[key] !== false; },
    toggle(key) { this.visible[key] = !this.isVisible(key); localStorage.setItem('kiel.tasks.board.columns.visible', JSON.stringify(this.visible)); },
    reset() { this.columns.forEach((column) => { this.visible[column.key] = true; }); localStorage.setItem('kiel.tasks.board.columns.visible', JSON.stringify(this.visible)); },
    hiddenCount() { return this.columns.filter((column) => !this.isVisible(column.key)).length; },
});

Alpine.start();

const loadSortable = () => {
    if (window.Sortable) return Promise.resolve(window.Sortable);
    if (window.__kielSortablePromise) return window.__kielSortablePromise;
    window.__kielSortablePromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';
        script.onload = () => resolve(window.Sortable);
        script.onerror = reject;
        document.head.appendChild(script);
    });
    return window.__kielSortablePromise;
};

const updateKanbanEmptyStates = (board) => board.querySelectorAll('[data-kanban-column]').forEach((c) => {
    const empty = c.querySelector('[data-empty-state]');
    const hasCards = c.querySelector('[data-kanban-card]');
    if (empty) empty.classList.toggle('hidden', !!hasCards);
    const count = c.closest('div.w-full')?.querySelector('[data-column-count]');
    if (count) count.textContent = c.querySelectorAll('[data-kanban-card]').length;
});

const handleKanbanDrop = async (board, evt) => {
    const card = evt.item;
    const sourceColumn = evt.from.dataset.column;
    const destinationColumn = evt.to.dataset.column;
    const boardView = board.dataset.view || 'all';
    const movedAcrossColumns = sourceColumn !== destinationColumn;
    const destinationIds = Array.from(evt.to.querySelectorAll('[data-kanban-card]')).map((el) => Number(el.dataset.ticketId));
    const sourceIds = Array.from(evt.from.querySelectorAll('[data-kanban-card]')).map((el) => Number(el.dataset.ticketId));
    const insertBack = () => {
        if (typeof evt.oldIndex !== 'number') return;
        const siblings = evt.from.querySelectorAll('[data-kanban-card]');
        const ref = siblings[evt.oldIndex] || null;
        evt.from.insertBefore(card, ref);
    };
    try {
        if (movedAcrossColumns) {
            const payload = await window.Kiel.request(card.dataset.moveUrl, { method: 'PATCH', body: JSON.stringify({ column: destinationColumn, position: evt.newIndex ?? 0, view: boardView }) });
            if (payload?.ticket?.column) card.dataset.currentColumn = payload.ticket.column;
            if (payload?.ticket) window.KielTasks.emitTaskUpdated(payload.ticket);
        }
        await window.Kiel.request(board.dataset.reorderUrl, { method: 'PATCH', body: JSON.stringify({ column: destinationColumn, tickets: destinationIds, view: boardView }) });
        if (movedAcrossColumns && sourceIds.length > 0) {
            await window.Kiel.request(board.dataset.reorderUrl, { method: 'PATCH', body: JSON.stringify({ column: sourceColumn, tickets: sourceIds, view: boardView }) });
        }
        updateKanbanEmptyStates(board);
        window.Kiel.toast('Task moved.', 'success');
    } catch (error) {
        window.Kiel.toast(error.message || 'Unable to move ticket.', 'error');
        insertBack();
        updateKanbanEmptyStates(board);
    }
};

window.KielKanban = {
    async initAll(force = false) {
        await loadSortable().catch(() => window.Kiel.toast('Kanban library failed to load.', 'error'));
        document.querySelectorAll('[data-kanban-board]').forEach((board) => {
            if (board.dataset.canMove !== 'true' || !window.Sortable) return;
            board.querySelectorAll('[data-kanban-column]').forEach((column) => {
                if (!column.offsetParent) return;
                if (force && column._kielSortable) {
                    column._kielSortable.destroy();
                    column._kielSortable = null;
                    column.dataset.sortableInitialized = '0';
                }
                if (column.dataset.sortableInitialized === '1') return;
                column._kielSortable = window.Sortable.create(column, {
                    group: `kiel-kanban-${board.dataset.view || 'all'}`, animation: 180, draggable: '[data-kanban-card]', handle: '[data-kanban-drag-handle]',
                    ghostClass: 'kanban-card-ghost', chosenClass: 'kanban-card-chosen', dragClass: 'kanban-card-drag',
                    fallbackOnBody: true, forceFallback: false, swapThreshold: 0.65, emptyInsertThreshold: 24,
                    onStart: (evt) => { evt.item.classList.add('is-dragging'); document.body.classList.add('is-kanban-dragging'); },
                    onEnd: (evt) => { evt.item.classList.remove('is-dragging'); document.body.classList.remove('is-kanban-dragging'); handleKanbanDrop(board, evt); },
                });
                column.dataset.sortableInitialized = '1';
            });
            updateKanbanEmptyStates(board);
        });
    },
};

window.KielTimeline = { initAll() { document.querySelectorAll('[data-timeline-view]').forEach(() => {}); } };

window.KielTaskList = {
    updateListRowFromPayload(row, ticket) {
        if (!row || !ticket) return;
        const section = listSectionForTicket(ticket);
        row.dataset.currentStatus = ticket.status || row.dataset.currentStatus;
        row.dataset.currentSection = section;
        const statusLabel = row.querySelector('[data-list-status-label]');
        if (statusLabel && ticket.status_label) statusLabel.textContent = ticket.status_label;
        const title = row.querySelector('[data-list-title]');
        if (title && ticket.title) title.textContent = ticket.title;
        const assignee = row.querySelector('[data-list-assignee]');
        if (assignee) assignee.textContent = ticket.assignee_name || ticket.assignee?.name || 'Unassigned';
        const ticketNo = row.querySelector('[data-list-ticket-no]');
        if (ticketNo && ticket.ticket_no) ticketNo.textContent = ticket.ticket_no;
    },
    moveListRowToSection(row, sectionKey, index = null) {
        const targetBody = document.querySelector(`[data-list-section-body][data-section="${sectionKey}"]`);
        if (!targetBody || !row) return;
        targetBody.querySelector('[data-list-empty-row]')?.remove();
        const taskRows = Array.from(targetBody.querySelectorAll('[data-list-task-row]'));
        if (index === null || index >= taskRows.length) targetBody.appendChild(row);
        else targetBody.insertBefore(row, taskRows[index] || null);
        row.dataset.currentSection = sectionKey;
    },
    updateListEmptyStates() {
        document.querySelectorAll('[data-list-section-body]').forEach((body) => {
            const hasRows = body.querySelector('[data-list-task-row]');
            const emptyRow = body.querySelector('[data-list-empty-row]');
            if (hasRows && emptyRow) emptyRow.remove();
            if (!hasRows && !emptyRow) {
                body.insertAdjacentHTML('beforeend', '<tr data-list-empty-row><td colspan="5" class="px-4 py-4 text-xs text-slate-400">Drop tasks here</td></tr>');
            }
        });
    },
    updateListSectionCounts() {
        document.querySelectorAll('[data-list-section]').forEach((section) => {
            const count = section.querySelectorAll('[data-list-task-row]').length;
            section.querySelector('[data-list-section-count]')?.replaceChildren(String(count));
        });
        this.updateListEmptyStates();
    },
    restoreListRow(row, previousSection, previousIndex) {
        this.moveListRowToSection(row, previousSection, previousIndex);
        this.updateListSectionCounts();
    },
    async initAll(force = false) {
        await loadSortable().catch(() => window.Kiel.toast('List drag/drop unavailable.', 'error'));
        this.updateListSectionCounts();
        document.querySelectorAll('[data-list-section-body]').forEach((body) => {
            if (force && body._kielSortable) { body._kielSortable.destroy(); body._kielSortable = null; }
            if (body._kielSortable || !window.Sortable) return;
            body._kielSortable = window.Sortable.create(body, {
                group: 'kiel-task-list-status', animation: 160, draggable: '[data-list-task-row]', handle: '[data-list-drag-handle]',
                ghostClass: 'task-list-row-ghost', chosenClass: 'task-list-row-chosen', dragClass: 'task-list-row-drag', fallbackOnBody: true, emptyInsertThreshold: 24,
                onStart: () => document.body.classList.add('is-task-list-dragging'),
                onEnd: async (evt) => {
                    document.body.classList.remove('is-task-list-dragging');
                    const row = evt.item;
                    const to = evt.to.dataset.section;
                    const from = evt.from.dataset.section;
                    const previousIndex = evt.oldIndex;
                    if (to === from) return;
                    const type = row.dataset.ticketType;
                    const status = to === 'in_progress' ? 'in_progress' : (to === 'completed' ? (type === 'bug' ? 'bug_completed' : 'task_completed') : 'backlog');
                    try {
                        const payload = await window.Kiel.request(row.dataset.moveUrl, { method: 'PATCH', body: JSON.stringify({ field: 'status', value: status }) });
                        if (payload.removed_from_tasks) {
                            row.remove();
                            this.updateListSectionCounts();
                            window.KielTasks.emitRemoved(payload.ticket.id, 'moved_to_next_sprint');
                            return;
                        }
                        const newSection = listSectionForTicket(payload.ticket || {});
                        this.moveListRowToSection(row, newSection, evt.newIndex ?? null);
                        this.updateListRowFromPayload(row, payload.ticket);
                        this.updateListSectionCounts();
                        window.KielTasks.emitTaskUpdated(payload.ticket);
                    } catch (e) {
                        window.Kiel.toast(e.message || 'Unable to move task.', 'error');
                        this.restoreListRow(row, from, previousIndex);
                    }
                },
            });
        });
    },
};

document.addEventListener('DOMContentLoaded', () => { window.KielTaskList.initAll(); window.KielKanban?.initAll(); });
window.addEventListener('kiel:task-updated', (event) => {
    const ticket = event.detail?.ticket;
    if (!ticket?.id) return;
    document.querySelectorAll(`[data-list-task-row][data-ticket-id="${ticket.id}"]`).forEach((row) => {
        if (ticket.removed_from_tasks) { row.remove(); return; }
        const section = listSectionForTicket(ticket);
        window.KielTaskList.moveListRowToSection(row, section);
        window.KielTaskList.updateListRowFromPayload(row, ticket);
    });
    window.KielTaskList.updateListSectionCounts();
    window.KielTasks.markDirty('board');
    window.KielTasks.markDirty('timeline');
    window.KielKanban?.initAll(true);
});
window.addEventListener('kiel:task-removed', (event) => {
    document.querySelectorAll(`[data-list-task-row][data-ticket-id="${event.detail.ticketId}"]`).forEach((el) => el.remove());
    document.querySelectorAll(`[data-kanban-card][data-ticket-id="${event.detail.ticketId}"]`).forEach((el) => el.remove());
    window.KielTaskList.updateListSectionCounts();
    window.KielTasks.markDirty('board');
    window.KielTasks.markDirty('timeline');
});
