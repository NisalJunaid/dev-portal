<div class="space-y-4">
    @forelse ($activities as $activity)
        <div class="relative pl-7">
            <span class="absolute left-0 top-1.5 h-3 w-3 rounded-full bg-indigo-500 ring-4 ring-indigo-50"></span>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-black text-slate-950">{{ str($activity->action)->headline() }}</p>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $activity->created_at->format('M j, Y g:i A') }}</p>
                </div>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $activity->description }}</p>
                <p class="mt-2 text-xs text-slate-400">{{ $activity->user?->name ?? 'System' }}</p>
                @if ($activity->old_value || $activity->new_value)
                    <div class="mt-3 grid gap-2 rounded-2xl bg-slate-50 p-3 text-xs text-slate-500 sm:grid-cols-2">
                        <p><strong class="text-slate-700">From:</strong> {{ $activity->old_value ?? '—' }}</p>
                        <p><strong class="text-slate-700">To:</strong> {{ $activity->new_value ?? '—' }}</p>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="rounded-2xl bg-slate-50 p-6 text-center text-sm font-semibold text-slate-500">No activity recorded yet.</div>
    @endforelse
</div>
