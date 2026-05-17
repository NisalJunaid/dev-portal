@php
    $timerPayload = $currentTimer ? [
        'id' => $currentTimer->id,
        'status' => $currentTimer->status,
        'started_at' => $currentTimer->started_at?->toISOString(),
        'paused_at' => $currentTimer->paused_at?->toISOString(),
        'resumed_at' => $currentTimer->resumed_at?->toISOString(),
        'ended_at' => $currentTimer->ended_at?->toISOString(),
        'duration_seconds' => $currentTimer->duration_seconds,
        'current_duration_seconds' => $currentTimer->currentDurationSeconds(),
    ] : null;
@endphp

<section
    class="card"
    x-data="ticketTimerPanel({
        timer: @js($timerPayload),
        cumulativeSeconds: @js($cumulativeDuration ?? 0),
        routes: {
            start: @js(route('tickets.timer.start', $ticket)),
            pause: @js(route('tickets.timer.pause', $ticket)),
            resume: @js(route('tickets.timer.resume', $ticket)),
            stop: @js(route('tickets.timer.stop', $ticket)),
        },
            })"
    x-init="init()"
>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.25em] text-indigo-600">Kiel internal</p>
            <h3 class="mt-1 text-lg font-black text-slate-950">Timer</h3>
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide" :class="statusBadgeClass()" x-text="statusLabel()"></span>
    </div>

    <div class="mt-5 rounded-2xl bg-slate-950 p-5 text-white">
        <p class="text-xs font-bold uppercase tracking-[0.25em] text-slate-400">Current session</p>
        <p class="mt-2 font-mono text-4xl font-black tracking-tight" x-text="formatDuration(displaySeconds)">00:00:00</p>
        <p class="mt-4 text-xs font-bold uppercase tracking-[0.25em] text-slate-400">Ticket cumulative</p>
        <p class="mt-1 font-mono text-lg font-black" x-text="formatDuration(cumulativeSeconds)">00:00:00</p>
    </div>

    <template x-if="message">
        <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800" x-text="message"></div>
    </template>
    <template x-if="error">
        <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800" x-text="error"></div>
    </template>

    <div class="mt-5 grid grid-cols-2 gap-3">
        <button type="button" class="rounded-2xl bg-indigo-600 px-4 py-3 text-sm font-black text-white shadow-soft disabled:cursor-not-allowed disabled:opacity-45" :disabled="saving || hasActiveTimer()" @click="send('start')">
            <span x-show="savingAction !== 'start'">Start</span>
            <span x-show="savingAction === 'start'">Starting…</span>
        </button>
        <button type="button" class="rounded-2xl bg-amber-500 px-4 py-3 text-sm font-black text-white shadow-soft disabled:cursor-not-allowed disabled:opacity-45" :disabled="saving || !isRunning()" @click="send('pause')">
            <span x-show="savingAction !== 'pause'">Pause</span>
            <span x-show="savingAction === 'pause'">Pausing…</span>
        </button>
        <button type="button" class="rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-black text-white shadow-soft disabled:cursor-not-allowed disabled:opacity-45" :disabled="saving || !isPaused()" @click="send('resume')">
            <span x-show="savingAction !== 'resume'">Resume</span>
            <span x-show="savingAction === 'resume'">Resuming…</span>
        </button>
        <button type="button" class="rounded-2xl bg-rose-600 px-4 py-3 text-sm font-black text-white shadow-soft disabled:cursor-not-allowed disabled:opacity-45" :disabled="saving || !hasActiveTimer()" @click="send('stop')">
            <span x-show="savingAction !== 'stop'">Stop</span>
            <span x-show="savingAction === 'stop'">Stopping…</span>
        </button>
    </div>
</section>

<script>
    window.ticketTimerPanel = function (config) {
        return {
            timer: config.timer,
            cumulativeSeconds: config.cumulativeSeconds,
            displaySeconds: config.timer?.current_duration_seconds ?? 0,
            routes: config.routes,
                        saving: false,
            savingAction: null,
            message: '',
            error: '',
            interval: null,
            init() {
                this.tick();
                this.interval = setInterval(() => this.tick(), 1000);
            },
            isRunning() {
                return this.timer?.status === 'running';
            },
            isPaused() {
                return this.timer?.status === 'paused';
            },
            hasActiveTimer() {
                return ['running', 'paused'].includes(this.timer?.status);
            },
            tick() {
                if (!this.timer) {
                    this.displaySeconds = 0;
                    return;
                }

                if (!this.isRunning()) {
                    this.displaySeconds = this.timer.current_duration_seconds ?? this.timer.duration_seconds ?? 0;
                    return;
                }

                const runningSince = new Date(this.timer.resumed_at || this.timer.started_at).getTime();
                const elapsed = Math.max(0, Math.floor((Date.now() - runningSince) / 1000));
                this.displaySeconds = (this.timer.duration_seconds ?? 0) + elapsed;
            },
            async send(action) {
                if (action === 'stop') {
                    const confirmed = await window.Kiel.confirm({
                        title: 'Stop timer?',
                        message: 'This will complete the active timer and add elapsed time to the ticket.',
                        confirmLabel: 'Stop timer',
                    });
                    if (!confirmed) return;
                }
                this.saving = true;
                this.savingAction = action;
                this.message = '';
                this.error = '';

                try {
                    const data = await window.Kiel.request(this.routes[action], { method: 'POST' });

                    this.timer = data.timer.status === 'completed' ? null : data.timer;
                    this.cumulativeSeconds = data.ticket.cumulative_duration_seconds;
                    this.displaySeconds = this.timer?.current_duration_seconds ?? 0;
                    this.message = data.message;
                    window.Kiel?.toast(data.message || 'Timer updated.');
                } catch (e) {
                    this.error = e.message || 'Unable to update the timer.';
                    window.Kiel?.toast(this.error, 'error');
                } finally {
                    this.saving = false;
                    this.savingAction = null;
                }
            },
            formatDuration(totalSeconds) {
                const total = Math.max(0, Number(totalSeconds || 0));
                const hours = Math.floor(total / 3600).toString().padStart(2, '0');
                const minutes = Math.floor((total % 3600) / 60).toString().padStart(2, '0');
                const seconds = Math.floor(total % 60).toString().padStart(2, '0');

                return `${hours}:${minutes}:${seconds}`;
            },
            statusLabel() {
                if (!this.timer) {
                    return 'Not started';
                }

                return this.timer.status;
            },
            statusBadgeClass() {
                if (this.isRunning()) {
                    return 'bg-emerald-100 text-emerald-700';
                }

                if (this.isPaused()) {
                    return 'bg-amber-100 text-amber-700';
                }

                return 'bg-slate-100 text-slate-700';
            },
        };
    };
</script>
