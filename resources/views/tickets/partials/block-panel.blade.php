@php
    $activeBlockPayload = $activeBlock ? [
        'id' => $activeBlock->id,
        'reason' => $activeBlock->reason,
        'blocked_at' => $activeBlock->blocked_at?->toISOString(),
        'blocked_by' => $activeBlock->blocker?->name,
        'current_duration_seconds' => $activeBlock->currentDurationSeconds(),
    ] : null;
@endphp

<section
    class="card"
    x-data="ticketBlockPanel({
        status: @js($ticket->status),
        formattedStatus: @js($ticket->formattedStatus()),
        activeBlock: @js($activeBlockPayload),
        totalBlockedSeconds: @js($totalBlockedDuration),
        routes: {
            block: @js(route('tickets.block', $ticket)),
            unblock: @js(route('tickets.unblock', $ticket)),
        },
        csrf: @js(csrf_token()),
    })"
>
    <div class="flex items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-black text-slate-950">Blocked workflow</h3>
            <p class="mt-1 text-xs font-bold uppercase tracking-wide text-slate-500" x-text="formattedStatus"></p>
        </div>
        <span :class="activeBlock ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'" class="rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide" x-text="activeBlock ? 'Blocked' : 'Unblocked'"></span>
    </div>

    <div x-show="activeBlock" x-cloak class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 p-4">
        <p class="text-xs font-black uppercase tracking-wide text-rose-700">Current blocker</p>
        <p class="mt-2 whitespace-pre-line text-sm font-bold leading-6 text-rose-950" x-text="activeBlock?.reason"></p>
        <p class="mt-3 text-xs font-bold text-rose-700">Blocked for <span x-text="formatDuration(activeBlock?.current_duration_seconds || 0)"></span></p>
    </div>

    <dl class="mt-5 space-y-3 text-sm">
        <div class="flex justify-between gap-4"><dt class="font-bold text-slate-500">Total blocked duration</dt><dd class="font-black text-slate-900" x-text="formatDuration(totalBlockedSeconds)"></dd></div>
    </dl>

    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        <button type="button" x-show="!activeBlock" @click="openBlockModal()" class="rounded-2xl bg-rose-600 px-4 py-2 text-sm font-black text-white shadow-soft hover:bg-rose-700">Block ticket</button>
        <button type="button" x-show="activeBlock" @click="openUnblockModal()" class="rounded-2xl bg-emerald-600 px-4 py-2 text-sm font-black text-white shadow-soft hover:bg-emerald-700">Unblock ticket</button>
    </div>

    <p x-show="message" x-text="message" class="mt-4 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800"></p>
    <p x-show="error" x-text="error" class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800"></p>

    <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
        <div @click.outside="closeModal()" class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-soft">
            <h4 class="text-xl font-black text-slate-950" x-text="modal === 'block' ? 'Block ticket' : 'Unblock ticket'"></h4>
            <p class="mt-2 text-sm text-slate-600" x-text="modal === 'block' ? 'Clients will see the blocked status and reason.' : 'The timer will stay paused until a Kiel user manually resumes it.'"></p>

            <form class="mt-5 space-y-4" @submit.prevent="submitModal()">
                <label class="block text-sm font-black text-slate-700" x-text="modal === 'block' ? 'Block reason' : 'Unblock note'"></label>
                <textarea x-model="modalText" rows="5" required class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :placeholder="modal === 'block' ? 'Explain what is blocking progress.' : 'Explain what changed and why work can continue.'"></textarea>

                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" @click="closeModal()" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-600">Cancel</button>
                    <button type="submit" :disabled="saving" class="rounded-2xl px-4 py-2 text-sm font-black text-white disabled:opacity-60" :class="modal === 'block' ? 'bg-rose-600' : 'bg-emerald-600'">
                        <span x-show="!saving" x-text="modal === 'block' ? 'Block ticket' : 'Unblock ticket'"></span>
                        <span x-show="saving">Saving…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
    window.ticketBlockPanel = function (config) {
        return {
            status: config.status,
            formattedStatus: config.formattedStatus,
            activeBlock: config.activeBlock,
            totalBlockedSeconds: config.totalBlockedSeconds || 0,
            routes: config.routes,
            csrf: config.csrf,
            modal: null,
            modalText: '',
            saving: false,
            message: '',
            error: '',
            init() {
                setInterval(() => {
                    if (this.activeBlock) {
                        this.activeBlock.current_duration_seconds = (this.activeBlock.current_duration_seconds || 0) + 1;
                        this.totalBlockedSeconds += 1;
                    }
                }, 1000);
            },
            openBlockModal() {
                this.modal = 'block';
                this.modalText = '';
                this.error = '';
            },
            openUnblockModal() {
                this.modal = 'unblock';
                this.modalText = '';
                this.error = '';
            },
            closeModal() {
                if (this.saving) return;
                this.modal = null;
                this.modalText = '';
            },
            async submitModal() {
                if (!this.modalText.trim()) {
                    this.error = this.modal === 'block' ? 'A block reason is required.' : 'An unblock note is required.';
                    return;
                }

                const action = this.modal;
                this.saving = true;
                this.message = '';
                this.error = '';

                try {
                    const body = action === 'block'
                        ? { reason: this.modalText }
                        : { unblock_note: this.modalText };
                    const response = await fetch(this.routes[action], {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        body: JSON.stringify(body),
                    });
                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'Unable to update blocked state.');
                    }

                    this.status = data.ticket.status;
                    this.formattedStatus = data.ticket.formatted_status;
                    this.activeBlock = data.active_block;
                    this.totalBlockedSeconds = data.ticket.total_blocked_duration_seconds;
                    this.message = data.message;
                    window.dispatchEvent(new CustomEvent('ticket-block-updated', { detail: data }));
                    this.closeModal();
                } catch (e) {
                    this.error = e.message || 'Unable to update blocked state.';
                } finally {
                    this.saving = false;
                }
            },
            formatDuration(totalSeconds) {
                const total = Math.max(0, Number(totalSeconds || 0));
                const days = Math.floor(total / 86400);
                const hours = Math.floor((total % 86400) / 3600).toString().padStart(2, '0');
                const minutes = Math.floor((total % 3600) / 60).toString().padStart(2, '0');
                const seconds = Math.floor(total % 60).toString().padStart(2, '0');

                return `${days > 0 ? `${days}d ` : ''}${hours}:${minutes}:${seconds}`;
            },
        };
    };
</script>
