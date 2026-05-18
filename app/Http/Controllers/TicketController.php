<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Software;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\TicketActivityService;
use App\Services\TicketBlockService;
use App\Services\TicketNumberService;
use App\Services\TimeTrackingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketNumberService $ticketNumberService,
        private readonly TicketActivityService $ticketActivityService,
        private readonly TimeTrackingService $timeTrackingService,
        private readonly TicketBlockService $ticketBlockService,
    ) {
    }

    public function index(Request $request): \Illuminate\Http\RedirectResponse
    {
        abort_unless($request->user()->can('view tickets'), 403);

        return redirect()->route('tasks.index', array_merge($request->query(), ['view' => 'list']));
    }

    public function legacyIndexData(Request $request): View
    {
        abort_unless($request->user()->can('view tickets'), 403);

        $validated = $request->validate([
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

        $ticketsQuery = $this->visibleTickets($request)
            ->with(['client', 'software', 'submitter', 'assignee', 'sprints'])
            ->withExists(['activeBlock as is_blocked'])
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('ticket_no', 'like', '%'.$search.'%')
                        ->orWhere('title', 'like', '%'.$search.'%')
                        ->orWhereHas('client', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('software', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('assignee', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($validated['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($validated['urgency'] ?? null, fn ($query, string $urgency) => $query->where('urgency', $urgency))
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when(($validated['assigned_to'] ?? null) === 'unassigned', fn ($query) => $query->whereNull('assigned_to'))
            ->when(($validated['assigned_to'] ?? null) && ($validated['assigned_to'] ?? null) !== 'unassigned', fn ($query) => $query->where('assigned_to', $validated['assigned_to']))
            ->when(($validated['client_id'] ?? null) && $request->user()->isKielUser(), fn ($query, int $clientId) => $query->where('client_id', $clientId))
            ->when($validated['software_id'] ?? null, fn ($query, int $softwareId) => $query->where('software_id', $softwareId))
            ->when(($validated['blocked'] ?? null) === 'yes', fn ($query) => $query->whereIn('status', [Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_FEATURE_BLOCKED]))
            ->when(($validated['blocked'] ?? null) === 'no', fn ($query) => $query->whereNotIn('status', [Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_FEATURE_BLOCKED]));

        match ($sort) {
            'client' => $ticketsQuery->join('clients', 'tickets.client_id', '=', 'clients.id')->orderBy('clients.name', $direction)->select('tickets.*'),
            'software' => $ticketsQuery->join('softwares', 'tickets.software_id', '=', 'softwares.id')->orderBy('softwares.name', $direction)->select('tickets.*'),
            'assigned_to' => $ticketsQuery->leftJoin('users as assignees', 'tickets.assigned_to', '=', 'assignees.id')->orderBy('assignees.name', $direction)->select('tickets.*'),
            'sprint' => $ticketsQuery->orderBy(Sprint::select('sprint_no')->join('sprint_items', 'sprints.id', '=', 'sprint_items.sprint_id')->whereColumn('sprint_items.ticket_id', 'tickets.id')->latest('sprints.sprint_no')->limit(1), $direction),
            'blocked' => $ticketsQuery->orderByRaw("case when tickets.status in (?, ?) then 1 else 0 end {$direction}", [Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_FEATURE_BLOCKED]),
            default => $ticketsQuery->orderBy('tickets.'.$sort, $direction),
        };

        $tickets = $ticketsQuery
            ->orderBy('tickets.id')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return view('tickets.index', [
            'tickets' => $tickets,
            'isKielUser' => $request->user()->isKielUser(),
            'teamMembers' => $this->teamMembers(),
            'clients' => $request->user()->isKielUser() ? Client::orderBy('name')->get() : collect(),
            'softwares' => $this->availableSoftware($request)->get(),
            'filters' => $validated,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function backlog(Request $request): View
    {
        abort_unless($request->user()->isKielUser(), 403);

        return view('tickets.backlog', [
            'tickets' => Ticket::query()
                ->with(['client', 'software', 'submitter', 'assignee'])
                ->where('status', Ticket::STATUS_BACKLOG)
                ->latest('submitted_at')
                ->paginate(12),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('view tickets'), 403);

        return view('tickets.create', [
            'softwares' => $this->availableSoftware($request)->get(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->can('view tickets'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'software_id' => ['required', Rule::exists('softwares', 'id')->where('is_enabled', true)],
            'urgency' => ['required', Rule::in(Ticket::URGENCIES)],
            'type' => ['nullable', Rule::in([Ticket::TYPE_TASK])],
            'parent_ticket_id' => ['nullable', Rule::exists('tickets', 'id')],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $software = Software::with('client')->findOrFail($validated['software_id']);
        abort_unless($request->user()->canAccessClient($software->client), 403);

        $isTask = ($validated['type'] ?? null) === Ticket::TYPE_TASK;
        if ($isTask) {
            abort_unless($request->user()->isKielUser(), 403);
        }

        $ticket = DB::transaction(function () use ($request, $validated, $software, $isTask) {
            $ticket = Ticket::create([
                'client_id' => $software->client_id,
                'software_id' => $software->id,
                'submitted_by' => $request->user()->id,
                'ticket_no' => $this->ticketNumberService->next(),
                'title' => $validated['title'],
                'description' => $validated['description'],
                'urgency' => $validated['urgency'],
                'type' => $isTask ? Ticket::TYPE_TASK : null,
                'status' => Ticket::STATUS_BACKLOG,
                'submitted_at' => now(),
                'parent_ticket_id' => $validated['parent_ticket_id'] ?? null,
                'assigned_to' => $isTask ? ($validated['assigned_to'] ?? null) : null,
                'start_date' => $isTask ? ($validated['start_date'] ?? null) : null,
                'due_date' => $isTask ? ($validated['due_date'] ?? null) : null,
                'estimated_hours' => $isTask ? ($validated['estimated_hours'] ?? null) : null,
            ]);

            $this->ticketActivityService->log($ticket, 'created', $isTask ? 'Task created.' : 'Ticket submitted to the centralized intake backlog.', $request->user());

            return $ticket;
        });

        if ($request->expectsJson()) {
            $ticket->load(['client', 'software', 'assignee', 'sprints']);

            return response()->json([
                'message' => $ticket->ticket_no.' created successfully.',
                'ticket' => $this->inlineTicketPayload($ticket),
                'drawer_url' => route('tickets.drawer', $ticket),
            ], 201);
        }

        return redirect()->route('tickets.show', $ticket)->with('status', $ticket->ticket_no.' submitted successfully.');
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $this->authorizeTicketAccess($request, $ticket);

        $ticket->load(['client', 'software', 'submitter', 'assignee', 'activities.user', 'activeBlock.blocker']);
        $comments = $this->visibleComments($request, $ticket);
        $currentTimer = $request->user()->isKielUser()
            ? $this->timeTrackingService->activeTimerFor($ticket, $request->user())->latest()->first()
            : null;
        $cumulativeDuration = $request->user()->isKielUser()
            ? $this->timeTrackingService->cumulativeDurationForTicket($ticket)
            : null;
        $blockHistory = $request->user()->isKielUser()
            ? $ticket->blocks()->with(['blocker', 'unblocker'])->latest('blocked_at')->get()
            : collect();
        $totalBlockedDuration = $this->ticketBlockService->totalBlockedDurationForTicket($ticket);

        return view('tickets.show', [
            'ticket' => $ticket,
            'comments' => $comments,
            'teamMembers' => $this->teamMembers(),
            'softwares' => Software::with('client')->where('is_enabled', true)->orderBy('name')->get(),
            'isKielUser' => $request->user()->isKielUser(),
            'currentTimer' => $currentTimer,
            'cumulativeDuration' => $cumulativeDuration,
            'activeBlock' => $ticket->activeBlock,
            'blockHistory' => $blockHistory,
            'totalBlockedDuration' => $totalBlockedDuration,
        ]);
    }


    public function drawer(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($request, $ticket);

        $ticket->load(['client', 'software', 'submitter', 'assignee', 'activities.user', 'activeBlock.blocker', 'sprints']);
        $comments = $this->visibleComments($request, $ticket);
        $currentTimer = $request->user()->isKielUser()
            ? $this->timeTrackingService->activeTimerFor($ticket, $request->user())->latest()->first()
            : null;
        $cumulativeDuration = $request->user()->isKielUser()
            ? $this->timeTrackingService->cumulativeDurationForTicket($ticket)
            : null;
        $activeBlock = $ticket->activeBlock;
        $totalBlockedDuration = $this->ticketBlockService->totalBlockedDurationForTicket($ticket);

        return response()->json([
            'html' => view('tickets.partials.drawer-content', [
                'ticket' => $ticket,
                'comments' => $comments,
                'teamMembers' => $this->teamMembers(),
                'isKielUser' => $request->user()->isKielUser(),
                'currentTimer' => $currentTimer,
                'cumulativeDuration' => $cumulativeDuration,
                'activeBlock' => $activeBlock,
                'totalBlockedDuration' => $totalBlockedDuration,
                'canRecommend' => ! $request->user()->isKielUser()
                    && $ticket->isFeature()
                    && $ticket->status === Ticket::STATUS_FEATURE_APPROVED,
            ])->render(),
            'ticket' => $this->inlineTicketPayload($ticket),
        ]);
    }

    public function classify(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeKielTicketAccess($request, $ticket);

        $validated = $request->validate([
            'type' => ['required', Rule::in(Ticket::TYPES)],
        ]);

        $oldType = $ticket->type;
        $oldStatus = $ticket->status;
        $newStatus = $validated['type'] === Ticket::TYPE_BUG ? Ticket::STATUS_BUG_PENDING : Ticket::STATUS_FEATURE_APPROVED;

        $ticket->update([
            'type' => $validated['type'],
            'status' => $newStatus,
            'rejection_reason' => null,
            'classified_at' => now(),
        ]);

        $this->ticketActivityService->log($ticket, 'classified', 'Ticket classified as '.str($validated['type'])->headline().'.', $request->user(), $oldType, $validated['type']);

        if ($oldStatus !== $newStatus) {
            $this->ticketActivityService->log($ticket, 'status changed', 'Ticket status changed during classification.', $request->user(), $oldStatus, $newStatus);
        }

        return back()->with('status', 'Ticket classified successfully.');
    }

    public function reject(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeKielTicketAccess($request, $ticket);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:10000'],
        ]);

        $oldStatus = $ticket->status;

        $ticket->update([
            'status' => Ticket::STATUS_REJECTED,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $this->ticketActivityService->log($ticket, 'rejected', 'Ticket rejected with a formal reason.', $request->user(), $oldStatus, Ticket::STATUS_REJECTED);

        if ($oldStatus !== Ticket::STATUS_REJECTED) {
            $this->ticketActivityService->log($ticket, 'status changed', 'Ticket status changed to rejected.', $request->user(), $oldStatus, Ticket::STATUS_REJECTED);
        }

        return back()->with('status', 'Ticket rejected and reason published to the client.');
    }

    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeKielTicketAccess($request, $ticket);

        $validated = $request->validate([
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
        ]);

        $oldAssignee = $ticket->assignee?->name;
        $ticket->update(['assigned_to' => $validated['assigned_to'] ?? null]);
        $ticket->load('assignee');

        $this->ticketActivityService->log($ticket, 'assigned', 'Ticket assignment updated.', $request->user(), $oldAssignee, $ticket->assignee?->name);

        return back()->with('status', 'Ticket assignment updated.');
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeKielTicketAccess($request, $ticket);

        $validated = $request->validate([
            'software_id' => ['required', Rule::exists('softwares', 'id')->where('is_enabled', true)],
            'urgency' => ['required', Rule::in(Ticket::URGENCIES)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'status' => ['required', Rule::in(Ticket::STATUSES)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $software = Software::findOrFail($validated['software_id']);
        $oldValues = $ticket->only(['software_id', 'urgency', 'assigned_to', 'status', 'start_date', 'due_date', 'estimated_hours']);

        if (in_array($validated['status'], [Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_FEATURE_BLOCKED], true) && ! $ticket->isBlocked()) {
            throw ValidationException::withMessages([
                'status' => 'Use the Block button and provide a reason to block tickets.',
            ]);
        }

        $updates = [
            'client_id' => $software->client_id,
            'software_id' => $software->id,
            'urgency' => $validated['urgency'],
            'assigned_to' => $validated['assigned_to'] ?? null,
            'status' => $validated['status'],
            'start_date' => $validated['start_date'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'estimated_hours' => $validated['estimated_hours'] ?? null,
        ];

        if ($ticket->type === Ticket::TYPE_BUG && $validated['status'] === Ticket::STATUS_BUG_COMPLETED) {
            $updates['completed_at'] = $ticket->completed_at ?? now();
            $updates['actual_completed_at'] = $ticket->actual_completed_at ?? now();
        } elseif ($ticket->status === Ticket::STATUS_BUG_COMPLETED && $validated['status'] !== Ticket::STATUS_BUG_COMPLETED) {
            $updates['completed_at'] = null;
            $updates['actual_completed_at'] = null;
        }

        $ticket->update($updates);

        if ($oldValues['urgency'] !== $ticket->urgency) {
            $this->ticketActivityService->log($ticket, 'urgency changed', 'Ticket urgency updated.', $request->user(), $oldValues['urgency'], $ticket->urgency);
        }

        if ((int) $oldValues['assigned_to'] !== (int) $ticket->assigned_to) {
            $this->ticketActivityService->log($ticket, 'assigned', 'Ticket assignment updated.', $request->user(), $oldValues['assigned_to'], $ticket->assigned_to);
        }

        if ($oldValues['status'] !== $ticket->status) {
            $this->ticketActivityService->log($ticket, 'status changed', 'Ticket status updated.', $request->user(), $oldValues['status'], $ticket->status);

            if (in_array($ticket->status, [Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_FEATURE_BLOCKED], true)) {
                $this->timeTrackingService->pauseRunningTimersForBlockedTicket($ticket, $request->user());
            }
        }

        if ($this->datesChanged($oldValues, $ticket)) {
            $this->ticketActivityService->log($ticket, 'dates changed', 'Ticket schedule or estimate updated.', $request->user(), $oldValues, $ticket->only(['start_date', 'due_date', 'estimated_hours']));
        }

        if ((int) $oldValues['software_id'] !== (int) $ticket->software_id) {
            $this->ticketActivityService->log($ticket, 'software changed', 'Ticket software assignment updated.', $request->user(), $oldValues['software_id'], $ticket->software_id);
        }

        return back()->with('status', 'Ticket details updated.');
    }


    public function inlineUpdate(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($request, $ticket);

        if (! $request->user()->isKielUser()) {
            return response()->json([
                'message' => 'Clients cannot inline edit operational ticket fields. Please use comments or recommendation workflows.',
            ], 403);
        }

        $validated = $request->validate([
            'field' => ['required', Rule::in(['title', 'description', 'urgency', 'assigned_to', 'start_date', 'due_date', 'status'])],
            'value' => ['nullable'],
        ]);

        $field = $validated['field'];
        $value = $validated['value'] ?? null;
        $rules = $this->inlineUpdateRules($field, $ticket);

        $fieldValidator = Validator::make(['value' => $value], ['value' => $rules]);

        if ($fieldValidator->fails()) {
            throw ValidationException::withMessages([
                $field => $fieldValidator->errors()->first('value'),
            ]);
        }

        if ($field === 'status' && in_array($value, [Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_FEATURE_BLOCKED], true) && ! $ticket->isBlocked()) {
            throw ValidationException::withMessages([
                'status' => 'Use the Block button and provide a reason to block tickets.',
            ]);
        }

        if ($field === 'due_date' && $ticket->start_date && $value && $value < $ticket->start_date->toDateString()) {
            throw ValidationException::withMessages([
                'due_date' => 'The due date must be after or equal to the start date.',
            ]);
        }

        if ($field === 'start_date' && $ticket->due_date && $value && $value > $ticket->due_date->toDateString()) {
            throw ValidationException::withMessages([
                'start_date' => 'The start date must be before or equal to the due date.',
            ]);
        }

        $oldValue = $ticket->{$field};
        $normalizedValue = $value === '' ? null : $value;

        if ($field === 'status' && $normalizedValue === Ticket::STATUS_NEXT_SPRINT && $ticket->isBug()) {
            throw ValidationException::withMessages([
                'status' => 'Bugs cannot be moved to Next Sprint.',
            ]);
        }

        if ($field === 'status' && $normalizedValue === Ticket::STATUS_NEXT_SPRINT && $ticket->isTask()) {
            return $this->moveTaskToNextSprintFeature($request, $ticket);
        }

        DB::transaction(function () use ($ticket, $field, $normalizedValue, $oldValue, $request) {
            $updates = [$field => $normalizedValue];

            if ($field === 'status') {
                if ($ticket->type === Ticket::TYPE_BUG && $normalizedValue === Ticket::STATUS_BUG_COMPLETED) {
                    $updates['completed_at'] = $ticket->completed_at ?? now();
                    $updates['actual_completed_at'] = $ticket->actual_completed_at ?? now();
                } elseif ($ticket->status === Ticket::STATUS_BUG_COMPLETED && $normalizedValue !== Ticket::STATUS_BUG_COMPLETED) {
                    $updates['completed_at'] = null;
                    $updates['actual_completed_at'] = null;
                }
            }

            $ticket->update($updates);

            if ((string) $oldValue !== (string) $ticket->{$field}) {
                $this->ticketActivityService->log(
                    $ticket,
                    $this->inlineActivityAction($field),
                    'Ticket '.str($field)->replace('_', ' ')->headline()->lower().' updated inline.',
                    $request->user(),
                    $oldValue,
                    $ticket->{$field}
                );
            }
        });

        $ticket->refresh()->load(['client', 'software', 'assignee', 'sprints']);

        return response()->json([
            'message' => 'Ticket updated.',
            'ticket' => $this->inlineTicketPayload($ticket),
        ]);
    }

    public function comment(Request $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        $this->authorizeTicketAccess($request, $ticket);

        $validated = $request->validate([
            'parent_id' => ['nullable', Rule::exists('ticket_comments', 'id')->where('ticket_id', $ticket->id)],
            'comment' => ['required', 'string', 'max:10000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        $isInternal = $request->user()->isKielUser() && (bool) ($validated['is_internal'] ?? false);

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'comment' => $validated['comment'],
            'is_internal' => $isInternal,
        ]);

        $this->ticketActivityService->log($ticket, 'comment added', $isInternal ? 'Internal comment added.' : 'Comment added.', $request->user());

        if ($request->expectsJson()) {
            $ticket->refresh()->load('activities.user');

            return response()->json([
                'message' => 'Comment added.',
                'comments_html' => view('tickets.partials.comments', [
                    'comments' => $this->visibleComments($request, $ticket),
                    'ticket' => $ticket,
                    'isKielUser' => $request->user()->isKielUser(),
                ])->render(),
                'activity_html' => view('tickets.partials.activity-timeline', [
                    'activities' => $ticket->activities,
                ])->render(),
            ]);
        }

        return back()->with('status', 'Comment added.');
    }

    private function inlineUpdateRules(string $field, Ticket $ticket): array
    {
        return match ($field) {
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'urgency' => ['required', Rule::in(Ticket::URGENCIES)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in($ticket->isBug() ? Ticket::BUG_STATUSES : ($ticket->isFeature() ? Ticket::FEATURE_STATUSES : array_values(array_unique(array_merge(Ticket::TASK_STATUSES, [Ticket::STATUS_FEATURE_BLOCKED, Ticket::STATUS_REJECTED, Ticket::STATUS_NEXT_SPRINT])))))],
        };
    }

    private function inlineActivityAction(string $field): string
    {
        return match ($field) {
            'title' => 'title changed',
            'description' => 'description changed',
            'urgency' => 'urgency changed',
            'assigned_to' => 'assigned',
            'start_date', 'due_date' => 'dates changed',
            'status' => 'status changed',
        };
    }

    private function inlineTicketPayload(Ticket $ticket): array
    {
        $latestSprint = $ticket->sprints->sortByDesc('sprint_no')->first();

        return [
            'id' => $ticket->id,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'urgency' => $ticket->urgency,
            'urgency_label' => $ticket->formattedUrgency(),
            'assigned_to' => $ticket->assigned_to,
            'assignee_name' => $ticket->assignee?->name ?? 'Unassigned',
            'start_date' => $ticket->start_date?->toDateString(),
            'start_date_label' => $ticket->start_date?->format('M j, Y') ?? 'Not set',
            'due_date' => $ticket->due_date?->toDateString(),
            'due_date_label' => $ticket->due_date?->format('M j, Y') ?? 'Not set',
            'due_date_overdue' => $ticket->due_date !== null && $ticket->due_date->isPast() && ! in_array($ticket->status, [Ticket::STATUS_BUG_COMPLETED, Ticket::STATUS_FEATURE_COMPLETED], true),
            'status' => $ticket->status,
            'status_label' => $ticket->formattedStatus(),
            'type' => $ticket->type,
            'list_section' => $ticket->listSectionKey(),
            'blocked' => $ticket->isBlocked(),
            'sprint_cycle' => $latestSprint ? '#'.$latestSprint->sprint_no.' '.$latestSprint->name : 'No sprint',
            'updated_at' => $ticket->updated_at?->format('M j, Y g:i A'),
        ];
    }

    private function moveTaskToNextSprintFeature(Request $request, Ticket $ticket): JsonResponse
    {
        $feature = null;

        DB::transaction(function () use ($request, $ticket, &$feature) {
            $ticket->sprints()->detach();

            if ($ticket->sourceFeature) {
                $feature = $ticket->sourceFeature;
                $feature->update([
                    'status' => Ticket::STATUS_NEXT_SPRINT,
                    'assigned_to' => $ticket->assigned_to,
                    'urgency' => $ticket->urgency,
                ]);
                $this->ticketActivityService->log($feature, 'status changed', 'Feature returned to next sprint backlog.', $request->user());

                $ticket->update([
                    'status' => Ticket::STATUS_REJECTED,
                    'rejection_reason' => 'Returned to feature request backlog for future sprint.',
                ]);
            } else {
                $ticket->update([
                    'type' => Ticket::TYPE_FEATURE,
                    'status' => Ticket::STATUS_NEXT_SPRINT,
                    'is_generated_task' => false,
                    'generated_from_sprint_id' => null,
                    'source_feature_id' => null,
                ]);
                $feature = $ticket;
            }

            $this->ticketActivityService->log($ticket, 'status changed', 'Task moved back to feature requests for next sprint.', $request->user());
        });

        $ticket->refresh()->load(['client', 'software', 'assignee', 'sprints']);
        if ($feature) {
            $feature->refresh()->load(['client', 'software', 'assignee', 'sprints']);
        }

        return response()->json([
            'message' => 'Task moved back to feature requests for next sprint.',
            'ticket' => $this->inlineTicketPayload($ticket),
            'removed_from_tasks' => true,
            'feature' => $feature ? $this->inlineTicketPayload($feature) : null,
        ]);
    }

    private function visibleTickets(Request $request)
    {
        $query = Ticket::query();

        if (! $request->user()->isKielUser()) {
            $query->where('client_id', $request->user()->client_id);
        }

        return $query;
    }

    private function availableSoftware(Request $request)
    {
        $query = Software::query()->with('client')->where('is_enabled', true)->orderBy('name');

        if (! $request->user()->isKielUser()) {
            $query->where('client_id', $request->user()->client_id);
        }

        return $query;
    }

    private function authorizeTicketAccess(Request $request, Ticket $ticket): void
    {
        abort_unless($request->user()->can('view tickets'), 403);
        abort_unless($request->user()->isKielUser() || $ticket->client_id === $request->user()->client_id, 403);
    }

    private function authorizeKielTicketAccess(Request $request, Ticket $ticket): void
    {
        $this->authorizeTicketAccess($request, $ticket);
        abort_unless($request->user()->isKielUser(), 403);
    }

    private function visibleComments(Request $request, Ticket $ticket)
    {
        $comments = $ticket->comments()
            ->with('user')
            ->when(! $request->user()->isKielUser(), fn ($query) => $query->where('is_internal', false))
            ->oldest()
            ->get();

        return $this->threadComments($comments->groupBy(fn (TicketComment $comment) => $comment->parent_id ?: 'root'));
    }

    private function threadComments($commentsByParent, string|int $parentId = 'root')
    {
        return ($commentsByParent[$parentId] ?? collect())
            ->map(function (TicketComment $comment) use ($commentsByParent) {
                $comment->setRelation('replies', $this->threadComments($commentsByParent, $comment->id));

                return $comment;
            })
            ->values();
    }

    private function teamMembers()
    {
        return User::role(['super_admin', 'kiel_manager', 'developer'])->orderBy('name')->get();
    }

    private function datesChanged(array $oldValues, Ticket $ticket): bool
    {
        return (string) $oldValues['start_date'] !== (string) $ticket->start_date
            || (string) $oldValues['due_date'] !== (string) $ticket->due_date
            || (string) $oldValues['estimated_hours'] !== (string) $ticket->estimated_hours;
    }
}
