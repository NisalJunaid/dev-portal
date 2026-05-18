<section class="sprint-panel p-4" id="current-sprint-panel">
@if($currentSprint)
<div class="flex items-center justify-between gap-4">
  <div>
    <div class="text-sm font-black">{{ $currentSprint->name }}</div>
    <div class="text-xs text-slate-600">{{ $currentSprint->client?->name }} · Started {{ optional($currentSprint->started_at)->format('M j, Y g:i A') }}</div>
    <span class="badge badge-status mt-2">{{ str($currentSprint->status)->headline() }}</span>
  </div>
  <div class="text-2xl font-black" x-text="formattedTimer"></div>
  <div class="grid grid-cols-4 gap-2 text-xs">
    <div>Total: {{ $currentSprintStats['total_tasks'] ?? 0 }}</div><div>Backlog: {{ $currentSprintStats['backlog_tasks'] ?? 0 }}</div><div>In Progress: {{ $currentSprintStats['in_progress_tasks'] ?? 0 }}</div>
    <div>Blocked: {{ $currentSprintStats['blocked_tasks'] ?? 0 }}</div><div>Completed: {{ $currentSprintStats['completed_tasks'] ?? 0 }}</div><div>Rejected: {{ $currentSprintStats['rejected_tasks'] ?? 0 }}</div><div>Remaining: {{ $currentSprintStats['remaining_tasks'] ?? 0 }}</div>
  </div>
  @if($isKielUser)
  <div class="flex gap-2">
    <button class="sprint-icon-button" x-show="running" @click="pauseSprint({{ $currentSprint->id }})" title="Pause sprint"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><rect x="4" y="3" width="4" height="14"/><rect x="12" y="3" width="4" height="14"/></svg><span class="sr-only">Pause sprint</span></button>
    <button class="sprint-icon-button" x-show="!running" @click="resumeSprint({{ $currentSprint->id }})" title="Resume sprint"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><polygon points="5,3 17,10 5,17"/></svg><span class="sr-only">Resume sprint</span></button>
    <button class="sprint-icon-button" @click="endSprint({{ $currentSprint->id }})" @disabled(!($currentSprintStats['can_end'] ?? false)) title="{{ ($currentSprintStats['can_end'] ?? false) ? 'End sprint' : 'All sprint tasks must be completed, rejected, or blocked before ending.' }}"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><rect x="4" y="4" width="12" height="12"/></svg><span class="sr-only">End sprint</span></button>
  </div>
  @endif
</div>
@else <p class="text-sm text-slate-500">No active sprint.</p>@endif
</section>
