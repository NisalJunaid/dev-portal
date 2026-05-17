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
window.KielTasks = window.KielTasks || {};
window.KielTasks.emitTaskUpdated = function (ticket) {
    window.dispatchEvent(new CustomEvent('kiel:task-updated', { detail: { ticket } }));
};
window.KielTasks.emitTaskCreated = function (ticket) {
    window.dispatchEvent(new CustomEvent('kiel:task-created', { detail: { ticket } }));
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
