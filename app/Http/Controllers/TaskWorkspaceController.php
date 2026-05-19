<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Software;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\User;
use App\Services\KanbanService;
use App\Services\TimelineService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskWorkspaceController extends Controller
{
    private const SCOPES = ['current_sprint', 'all', 'unsprinted', 'completed_sprints', 'sprint'];
    public function __construct(
        private readonly KanbanService $kanbanService,
        private readonly TimelineService $timelineService,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('view tickets'), 403);
        return view('tasks.index', $this->workspaceData($request));
    }

    public function partial(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('view tickets'), 403);
        $data = $this->workspaceData($request);
        $partial = match ($data['activeView']) {
            'board' => 'tasks.partials.kanban-view',
            default => 'tasks.partials.list-view',
        };

        return response()->json([
            'html' => view($partial, $partial === 'tasks.partials.kanban-view'
                ? ['activeView' => 'all', 'columns' => $data['kanbanColumns'], 'ticketsByColumn' => $data['kanbanTicketsByColumn'], 'canMove' => $data['canMove'], 'workType' => $data['workType']]
: ['tickets' => $data['tickets'], 'sort' => $data['sort'], 'direction' => $data['direction'], 'teamMembers' => $data['teamMembers'], 'isKielUser' => $data['isKielUser'], 'listSections' => $data['listSections'], 'workType' => $data['workType']]
            )->render(),
            'board_columns' => collect($data['kanbanColumns'])->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'work_type' => $data['workType'],
        ]);
    }

    private function workspaceData(Request $request): array
    {
        $validated = $request->validate([
            'view' => ['nullable', 'string'], 'search' => ['nullable', 'string', 'max:255'],
            'work_type' => ['nullable', Rule::in(['triage', 'tasks', 'bugs'])],
            'urgency' => ['nullable', Rule::in(Ticket::URGENCIES)], 'status' => ['nullable', Rule::in(Ticket::STATUSES)], 'assigned_to' => ['nullable', 'string'],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')], 'software_id' => ['nullable', 'integer', Rule::exists('softwares', 'id')],
            'blocked' => ['nullable', Rule::in(['yes', 'no'])], 'sort' => ['nullable', Rule::in(['ticket_no', 'title', 'type', 'urgency', 'status', 'assigned_to', 'client', 'software', 'start_date', 'due_date', 'sprint', 'blocked', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'scope' => ['nullable', Rule::in(self::SCOPES)],
            'sprint_id' => ['nullable', 'integer', Rule::exists('sprints', 'id')],
        ]);
        $sort = $validated['sort'] ?? 'updated_at'; $direction = $validated['direction'] ?? 'desc';
        $selectedScope = $validated['scope'] ?? 'current_sprint';
        $selectedSprintId = $validated['sprint_id'] ?? null;
        $workType = $validated['work_type'] ?? 'triage';
        $requestedView = $validated['view'] ?? 'list';
        $activeView = in_array($requestedView, ['list', 'board', 'timeline'], true) ? $requestedView : 'list';
        $filters = array_merge(['view' => $activeView, 'scope' => $selectedScope, 'work_type' => $workType], $validated);

        $activeSprints = Sprint::query()->with('client')->where('status', Sprint::STATUS_IN_PROGRESS)
            ->when(($validated['client_id'] ?? null) && $request->user()->isKielUser(), fn ($q, $cid) => $q->where('client_id', $cid))
            ->when(! $request->user()->isKielUser(), fn ($q) => $q->where('client_id', $request->user()->client_id))
            ->latest('started_at')->get();
        $currentSprintIds = $activeSprints->pluck('id')->all();
        $currentSprint = $activeSprints->first();

        if ($selectedScope === 'sprint' && ! $selectedSprintId) {
            $selectedScope = 'current_sprint';
        }

        $tickets = Ticket::query()->visibleTo($request->user())->notArchived()->with(['client', 'software', 'submitter', 'assignee', 'sprints', 'parent', 'children', 'children.assignee', 'children.client', 'children.software', 'children.sprints'])->withExists(['activeBlock as is_blocked'])
            ->when($validated['search'] ?? null, fn ($q, string $search) => $q->where(fn ($q) => $q->where('ticket_no', 'like', '%'.$search.'%')->orWhere('title', 'like', '%'.$search.'%')))
            ->when($workType === 'triage', fn ($q) => $q->where(function ($inner) {
                $inner->whereNull('type')->orWhere('status', Ticket::STATUS_TRIAGE_PENDING);
            }))
            ->when($workType === 'bugs', fn ($q) => $q->where('type', Ticket::TYPE_BUG))
            ->when($workType === 'tasks', fn ($q) => $q->where('type', Ticket::TYPE_TASK))
            ->when($validated['urgency'] ?? null, fn ($q, string $urgency) => $q->where('urgency', $urgency))
            ->when($validated['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when(($validated['assigned_to'] ?? null) === 'unassigned', fn ($q) => $q->whereNull('assigned_to'))
            ->when(($validated['assigned_to'] ?? null) && ($validated['assigned_to'] ?? null) !== 'unassigned', fn ($q) => $q->where('assigned_to', $validated['assigned_to']))
            ->when(($validated['client_id'] ?? null) && $request->user()->isKielUser(), fn ($q, int $clientId) => $q->where('client_id', $clientId))
            ->when($validated['software_id'] ?? null, fn ($q, int $softwareId) => $q->where('software_id', $softwareId))
            ->when($selectedScope === 'current_sprint' && $workType !== 'triage', fn ($q) => $q->whereHas('sprints', fn ($s) => $s->whereIn('sprints.id', $currentSprintIds ?: [0])))
            ->when($selectedScope === 'unsprinted', fn ($q) => $q->whereDoesntHave('sprints'))
            ->when($selectedScope === 'completed_sprints', fn ($q) => $q->whereHas('sprints', fn ($s) => $s->where('status', Sprint::STATUS_COMPLETED)))
            ->when($selectedScope === 'sprint' && $selectedSprintId, fn ($q) => $q->whereHas('sprints', fn ($s) => $s->where('sprints.id', $selectedSprintId)))
            ->orderBy('tickets.'.$sort, $direction)
            ->paginate($validated['per_page'] ?? 25)->withQueryString();

        $currentSprintStats = null;
        if ($currentSprint) {
            $currentSprint->load('tickets');
            $completed = $currentSprint->tickets->filter(fn (Ticket $ticket) => $ticket->isDone())->count();
            $total = $currentSprint->tickets->count();
            $backlog = $currentSprint->tickets->where('status', Ticket::STATUS_BACKLOG)->count();
            $inProgress = $currentSprint->tickets->where('status', Ticket::STATUS_IN_PROGRESS)->count();
            $blocked = $currentSprint->tickets->filter(fn (Ticket $ticket) => $ticket->isBlocked())->count();
            $rejected = $currentSprint->tickets->where('status', Ticket::STATUS_REJECTED)->count();
            $remaining = $total - $completed - $rejected;
            $currentSprintStats = [
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'incomplete_tasks' => $total - $completed,
                'backlog_tasks' => $backlog,
                'in_progress_tasks' => $inProgress,
                'blocked_tasks' => $blocked,
                'rejected_tasks' => $rejected,
                'remaining_tasks' => max(0, $remaining),
                'active_count' => $currentSprint->activeItemsCount(),
                'can_end' => $currentSprint->canEnd(),
                'started_at' => $currentSprint->started_at,
                'elapsed_seconds' => $currentSprint->elapsedSeconds(),
                'timer_status' => $currentSprint->timer_status,
            ];
        }

        return [
            'activeView' => $activeView, 'tickets' => $tickets, 'isKielUser' => $request->user()->isKielUser(),
            'teamMembers' => User::role(['super_admin', 'kiel_manager', 'developer'])->orderBy('name')->get(['id', 'name']),
            'clients' => Client::query()->when(! $request->user()->isKielUser(), fn ($q) => $q->whereKey($request->user()->client_id))->orderBy('name')->get(['id', 'name']),
            'softwares' => Software::query()->when(! $request->user()->isKielUser(), fn ($q) => $q->where('client_id', $request->user()->client_id))->orderBy('name')->get(['id', 'name']),
            'filters' => array_merge($filters, ['scope' => $selectedScope, 'sprint_id' => $selectedSprintId]), 'sort' => $sort, 'direction' => $direction, 'canEditTimeline' => $this->timelineService->canEdit($request->user()),
            'workType' => $workType,
            'assignees' => User::query()->orderBy('name')->get(['id', 'name']), 'urgencies' => Ticket::URGENCIES, 'statuses' => array_values(array_filter(Ticket::STATUSES, fn ($status) => $status !== Ticket::STATUS_FEATURE_APPROVED && $status !== Ticket::STATUS_RECOMMENDED && $status !== Ticket::STATUS_NEXT_SPRINT)),
            'sprints' => Sprint::query()->latest('id')->get(['id', 'name', 'sprint_no']), 'listSections' => Ticket::listSections($workType), 'kanbanColumns' => $this->kanbanService->columnsForWorkType($workType),
            'kanbanTicketsByColumn' => $this->kanbanService->groupedTickets($request->user(), KanbanService::VIEW_ALL, ['scope' => $selectedScope, 'sprint_id' => $selectedSprintId, 'current_sprint_ids' => $currentSprintIds, 'work_type' => $workType] + $validated), 'canMove' => $request->user()->isKielUser() || $request->user()->isClientUser(), 'currentSprint' => $currentSprint, 'activeSprints' => $activeSprints, 'currentSprintStats' => $currentSprintStats, 'currentSprintIds' => $currentSprintIds, 'selectedScope' => $selectedScope, 'selectedSprintId' => $selectedSprintId,
        ];
    }
}
