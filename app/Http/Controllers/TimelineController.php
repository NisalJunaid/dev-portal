<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Software;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TimelineService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TimelineController extends Controller
{
    public function __construct(private readonly TimelineService $timelineService)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('view timeline'), 403);

        return view('timeline.index', [
            'canEdit' => $this->timelineService->canEdit($request->user()),
            'clients' => $this->clients($request->user()),
            'softwares' => $this->softwares($request->user()),
            'sprints' => $this->sprints($request->user()),
            'assignees' => $this->assignees($request->user()),
            'urgencies' => Ticket::URGENCIES,
            'statuses' => Ticket::STATUSES,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('view timeline'), 403);

        $validated = $this->validatedFilters($request);

        return response()->json($this->timelineService->ganttPayload($request->user(), $validated));
    }

    public function dates(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        return response()->json([
            'message' => 'Timeline dates saved.',
            'task' => $this->timelineService->updateDates($ticket, $request->user(), $validated['start_date'], $validated['due_date']),
        ]);
    }

    public function dependency(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'depends_on_ticket_id' => ['nullable', 'integer', Rule::exists('tickets', 'id')],
        ]);

        return response()->json([
            'message' => 'Timeline dependency saved.',
            'task' => $this->timelineService->updateDependency($ticket, $request->user(), $validated['depends_on_ticket_id'] ?? null),
        ]);
    }

    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'software_id' => ['nullable', 'integer', Rule::exists('softwares', 'id')],
            'sprint_id' => ['nullable', 'integer', Rule::exists('sprints', 'id')],
            'assigned_to' => ['nullable', 'string', 'max:32'],
            'urgency' => ['nullable', Rule::in(Ticket::URGENCIES)],
            'status' => ['nullable', Rule::in(Ticket::STATUSES)],
        ]);

        if (($validated['assigned_to'] ?? null) && $validated['assigned_to'] !== 'unassigned') {
            if (! ctype_digit((string) $validated['assigned_to']) || ! User::query()->whereKey($validated['assigned_to'])->exists()) {
                throw ValidationException::withMessages(['assigned_to' => 'The selected assignee is invalid.']);
            }
        }

        return $validated;
    }

    private function clients(User $user)
    {
        return Client::query()
            ->when(! $user->isKielUser(), fn ($query) => $query->whereKey($user->client_id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function softwares(User $user)
    {
        return Software::query()
            ->when(! $user->isKielUser(), fn ($query) => $query->where('client_id', $user->client_id))
            ->orderBy('name')
            ->get(['id', 'client_id', 'name']);
    }

    private function sprints(User $user)
    {
        return Sprint::query()
            ->when(! $user->isKielUser(), fn ($query) => $query->where('client_id', $user->client_id))
            ->latest('id')
            ->get(['id', 'client_id', 'software_id', 'name', 'sprint_no', 'status']);
    }

    private function assignees(User $user)
    {
        return User::query()
            ->when(! $user->isKielUser(), fn ($query) => $query->where('client_id', $user->client_id))
            ->whereHas('assignedTickets', fn ($query) => $query->when(! $user->isKielUser(), fn ($ticketQuery) => $ticketQuery->where('client_id', $user->client_id)))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
