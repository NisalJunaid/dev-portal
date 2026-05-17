<?php

namespace App\Http\Controllers;

use App\Models\Software;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\TicketActivityService;
use App\Services\TicketBlockService;
use App\Services\TicketNumberService;
use App\Services\TimeTrackingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('view tickets'), 403);

        $tickets = $this->visibleTickets($request)
            ->with(['client', 'software', 'submitter', 'assignee'])
            ->latest('submitted_at')
            ->paginate(12);

        return view('tickets.index', [
            'tickets' => $tickets,
            'isKielUser' => $request->user()->isKielUser(),
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

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('view tickets'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'software_id' => ['required', Rule::exists('softwares', 'id')->where('is_enabled', true)],
            'urgency' => ['required', Rule::in(Ticket::URGENCIES)],
        ]);

        $software = Software::with('client')->findOrFail($validated['software_id']);
        abort_unless($request->user()->canAccessClient($software->client), 403);

        $ticket = DB::transaction(function () use ($request, $validated, $software) {
            $ticket = Ticket::create([
                'client_id' => $software->client_id,
                'software_id' => $software->id,
                'submitted_by' => $request->user()->id,
                'ticket_no' => $this->ticketNumberService->next(),
                'title' => $validated['title'],
                'description' => $validated['description'],
                'urgency' => $validated['urgency'],
                'type' => null,
                'status' => Ticket::STATUS_BACKLOG,
                'submitted_at' => now(),
            ]);

            $this->ticketActivityService->log($ticket, 'created', 'Ticket submitted to the centralized intake backlog.', $request->user());

            return $ticket;
        });

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

    public function comment(Request $request, Ticket $ticket): RedirectResponse
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

        return back()->with('status', 'Comment added.');
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
