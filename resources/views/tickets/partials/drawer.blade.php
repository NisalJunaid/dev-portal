<div data-global-ticket-drawer data-ticket-drawer data-timeline-drawer class="fixed inset-0 z-50 hidden" aria-hidden="true">
        <button type="button" data-ticket-drawer-backdrop class="absolute inset-0 bg-slate-950/40 opacity-0 transition-opacity duration-200" aria-label="Close task drawer"></button>
        <aside data-ticket-drawer-panel class="absolute inset-y-0 right-0 w-full max-w-2xl translate-x-full overflow-hidden border-l border-slate-200 bg-white shadow-2xl transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]">
            <div data-ticket-drawer-loading class="flex h-full items-center justify-center p-8 text-sm font-black uppercase tracking-[0.25em] text-slate-400">Loading task…</div>
            <div data-ticket-drawer-body class="h-full"></div>
        </aside>
    </div>

    <script>
        window.ticketDrawer = window.ticketDrawer || (() => {
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
                    const payload = await window.Kiel.request(url);
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
                window.Kiel?.toast(message, type);
            };

            const fieldValue = element => element.type === 'checkbox' ? (element.checked ? '1' : '0') : element.value;
            const saveInline = async (element) => {
                if (!element.dataset.inlineField || element.dataset.originalValue === fieldValue(element)) return;
                element.dataset.originalValue = fieldValue(element);
                try {
                    element.classList.add('saving-state');
                    const payload = await window.Kiel.request(element.dataset.inlineUrl, {
                        method: 'PATCH',
                        body: JSON.stringify({ field: element.dataset.inlineField, value: fieldValue(element) }),
                    });
                    flash(payload.message || 'Task updated.');
                    await load();
                } catch (error) {
                    flash(error.message || 'Unable to save field.', 'error');
                } finally {
                    element.classList.remove('saving-state');
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
                const openChild = event.target.closest('[data-ticket-open]');
                if (openChild) { event.preventDefault(); open(openChild.dataset.ticketOpen); return; }
                const addSubFeature = event.target.closest('[data-add-subfeature]');
                if (addSubFeature) { event.preventDefault(); window.dispatchEvent(new CustomEvent('kiel:add-subfeature', { detail: { parent_ticket_id: addSubFeature.dataset.parentTicketId } })); return; }
                const addSubtask = event.target.closest('[data-add-subtask]');
                if (addSubtask) { event.preventDefault(); window.dispatchEvent(new CustomEvent('kiel:add-subtask', { detail: { parent_ticket_id: addSubtask.dataset.parentTicketId } })); return; }
                const button = event.target.closest('[data-drawer-action]');
                if (!button) return;
                const action = button.dataset.drawerAction;
                if (button.dataset.confirmTitle) {
                    const confirmed = await window.Kiel.confirm({
                        title: button.dataset.confirmTitle,
                        message: button.dataset.confirmMessage,
                        confirmLabel: button.dataset.confirmLabel,
                    });
                    if (!confirmed) return;
                }
                window.Kiel.setLoading(button, true, button.dataset.savingLabel || 'Saving…');
                try {
                    const payload = await window.Kiel.request(button.dataset.actionUrl, { method: 'POST' });
                    flash(payload.message || (action === 'recommend' ? 'Feature recommended.' : 'Action complete.'));
                    await load();
                } catch (error) {
                    flash(error.message || 'Action failed.', 'error');
                } finally {
                    window.Kiel.setLoading(button, false);
                }
            };

            const handleSubmit = async (event) => {
                const form = event.target.closest('[data-drawer-comment-form], [data-drawer-action-form]');
                if (!form) return;
                event.preventDefault();
                if (form.dataset.confirmTitle) {
                    const confirmed = await window.Kiel.confirm({
                        title: form.dataset.confirmTitle,
                        message: form.dataset.confirmMessage,
                        confirmLabel: form.dataset.confirmLabel,
                    });
                    if (!confirmed) return;
                }
                const submitter = form.querySelector('[type="submit"]');
                window.Kiel.setLoading(submitter, true, 'Saving…');
                try {
                    const payload = await window.Kiel.request(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                    });
                    flash(payload.message || 'Saved.');
                    form.reset();
                    await load();
                } catch (error) {
                    flash(error.message || 'Unable to save.', 'error');
                } finally {
                    window.Kiel.setLoading(submitter, false);
                }
            };

            document.addEventListener('DOMContentLoaded', bind);
            if (document.readyState !== 'loading') bind();

            return { open, close, load };
        })();
    </script>
