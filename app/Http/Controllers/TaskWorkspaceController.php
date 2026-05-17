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
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskWorkspaceController extends Controller
{
    public function __construct(
        private readonly KanbanService $kanbanService,
        private readonly TimelineService $timelineService,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('view tickets'), 403);

        $validated = $request->validate([
            'view' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(Ticket::TYPES)],
            'urgency' => ['nullable', Rule::in(Ticket::URGENCIES)],
            'status' => ['nullable', Rule::in(Ticket::STATUSES)],
            'assigned_to' => ['nullable', 'string'],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'software_id' => ['nullable', 'integer', Rule::exists('softwares', 'id')],
            'blocked' => ['nullable', Rule::in(['yes', 'no'])],
            'sort' => ['nullable', Rule::in(['ticket_no', 'title', 'type', 'urgency', 'status', 'assigned_to', 'client', 'software', 'start_date', 'due_date', 'sprint', 'blocked', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $sort = $validated['sort'] ?? 'updated_at';
        $direction = $validated['direction'] ?? 'desc';
        $requestedView = $validated['view'] ?? 'list';
        $activeView = in_array($requestedView, ['list', 'board', 'timeline'], true)
            ? $requestedView
            : 'list';

        $filters = array_merge(['view' => $activeView], $validated);

        $ticketsQuery = Ticket::query()
            ->visibleTo($request->user())
            ->with(['client', 'software', 'submitter', 'assignee', 'sprints'])
            ->withExists(['activeBlock as is_blocked']);

        $ticketsQuery
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('ticket_no', 'like', '%'.$search.'%')
                        ->orWhere('title', 'like', '%'.$search.'%');
                });
            })
            ->when($validated['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($validated['urgency'] ?? null, fn ($query, string $urgency) => $query->where('urgency', $urgency))
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when(($validated['assigned_to'] ?? null) === 'unassigned', fn ($query) => $query->whereNull('assigned_to'))
            ->when(($validated['assigned_to'] ?? null) && ($validated['assigned_to'] ?? null) !== 'unassigned', fn ($query) => $query->where('assigned_to', $validated['assigned_to']))
            ->when(($validated['client_id'] ?? null) && $request->user()->isKielUser(), fn ($query, int $clientId) => $query->where('client_id', $clientId))
            ->when($validated['software_id'] ?? null, fn ($query, int $softwareId) => $query->where('software_id', $softwareId));

        $tickets = $ticketsQuery->orderBy('tickets.'.$sort, $direction)->paginate($validated['per_page'] ?? 25)->withQueryString();

        return view('tasks.index', [
            'activeView' => $activeView,
            'tickets' => $tickets,
            'isKielUser' => $request->user()->isKielUser(),
            'teamMembers' => User::query()->where('role', User::ROLE_KIEL_TEAM)->orderBy('name')->get(['id', 'name']),
            'clients' => Client::query()->when(! $request->user()->isKielUser(), fn ($q) => $q->whereKey($request->user()->client_id))->orderBy('name')->get(['id', 'name']),
            'softwares' => Software::query()->when(! $request->user()->isKielUser(), fn ($q) => $q->where('client_id', $request->user()->client_id))->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'canEditTimeline' => $this->timelineService->canEdit($request->user()),
            'assignees' => User::query()->orderBy('name')->get(['id', 'name']),
            'urgencies' => Ticket::URGENCIES,
            'statuses' => Ticket::STATUSES,
            'sprints' => Sprint::query()->latest('id')->get(['id', 'name', 'sprint_no']),
            'kanbanColumns' => $this->kanbanService->columnsFor(KanbanService::VIEW_ALL),
            'kanbanTicketsByColumn' => $this->kanbanService->groupedTickets($request->user(), KanbanService::VIEW_ALL),
            'canMove' => $request->user()->isKielUser() || $request->user()->isClientUser(),
        ]);
    }
}
