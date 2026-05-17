<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Software;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\TicketActivityService;
use App\Services\TicketBlockService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class FeatureController extends Controller
{
    public function __construct(
        private readonly TicketActivityService $ticketActivityService,
        private readonly TicketBlockService $ticketBlockService,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('view features'), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', Rule::exists('clients', 'id')],
            'software_id' => ['nullable', Rule::exists('softwares', 'id')],
            'urgency' => ['nullable', Rule::in(Ticket::URGENCIES)],
            'status' => ['nullable', Rule::in(Ticket::FEATURE_STATUSES)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
        ]);

        $query = $this->visibleFeatures($request)
            ->with(['client', 'software', 'submitter', 'assignee'])
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('ticket_no', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(($validated['client_id'] ?? null) && $request->user()->isKielUser(), fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->when($validated['software_id'] ?? null, fn ($query, $softwareId) => $query->where('software_id', $softwareId))
            ->when($validated['urgency'] ?? null, fn ($query, $urgency) => $query->where('urgency', $urgency))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['assigned_to'] ?? null, fn ($query, $assigneeId) => $query->where('assigned_to', $assigneeId))
            ->orderByRaw("CASE status WHEN 'recommended' THEN 1 WHEN 'feature_approved' THEN 2 WHEN 'next_sprint' THEN 3 WHEN 'in_progress' THEN 4 WHEN 'feature_blocked' THEN 5 WHEN 'feature_completed' THEN 6 ELSE 7 END")
            ->latest('submitted_at');

        return view('features.index', [
            'features' => $query->paginate(12)->withQueryString(),
            'clients' => Client::orderBy('name')->get(),
            'softwares' => $this->filterSoftware($request)->get(),
            'assignees' => $this->teamMembers(),
            'isKielUser' => $request->user()->isKielUser(),
            'filters' => $validated,
        ]);
    }

    public function recommended(Request $request): View
    {
        abort_unless($request->user()->isKielUser(), Response::HTTP_FORBIDDEN);
        abort_unless($request->user()->can('view features'), Response::HTTP_FORBIDDEN);

        return view('features.recommended', [
            'features' => Ticket::query()
                ->with(['client', 'software', 'submitter', 'assignee'])
                ->where('type', Ticket::TYPE_FEATURE)
                ->where('status', Ticket::STATUS_RECOMMENDED)
                ->orderByRaw("CASE urgency WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
                ->latest('updated_at')
                ->paginate(12),
        ]);
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $this->authorizeFeatureAccess($request, $ticket);

        $ticket->load(['client', 'software', 'submitter', 'assignee', 'activities.user', 'activeBlock.blocker']);

        return view('features.show', [
            'ticket' => $ticket,
            'comments' => $this->visibleComments($request, $ticket),
            'teamMembers' => $this->teamMembers(),
            'softwares' => Software::with('client')->where('is_enabled', true)->orderBy('name')->get(),
            'isKielUser' => $request->user()->isKielUser(),
            'activeBlock' => $ticket->activeBlock,
            'totalBlockedDuration' => $this->ticketBlockService->totalBlockedDurationForTicket($ticket),
        ]);
    }

    public function recommend(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeFeatureAccess($request, $ticket);
        abort_unless(in_array($ticket->status, [Ticket::STATUS_FEATURE_APPROVED], true), Response::HTTP_UNPROCESSABLE_ENTITY, 'Only approved features can be recommended.');

        $oldStatus = $ticket->status;

        $ticket->update(['status' => Ticket::STATUS_RECOMMENDED]);

        $this->ticketActivityService->log($ticket, 'recommended', 'Feature recommended for the next planning cycle.', $request->user(), $oldStatus, Ticket::STATUS_RECOMMENDED);

        return back()->with('status', 'Feature recommended for the next planning cycle.');
    }

    public function approveNextSprint(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeKielFeatureAccess($request, $ticket);
        abort_unless(in_array($ticket->status, [Ticket::STATUS_FEATURE_APPROVED, Ticket::STATUS_RECOMMENDED], true), Response::HTTP_UNPROCESSABLE_ENTITY, 'Only approved or recommended features can move to the next sprint.');

        $oldStatus = $ticket->status;

        $ticket->update(['status' => Ticket::STATUS_NEXT_SPRINT]);

        $this->ticketActivityService->log($ticket, 'moved to next sprint', 'Feature moved into the next sprint planning queue.', $request->user(), $oldStatus, Ticket::STATUS_NEXT_SPRINT);

        return back()->with('status', 'Feature moved to the next sprint.');
    }

    public function defer(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeKielFeatureAccess($request, $ticket);
        abort_unless($ticket->status === Ticket::STATUS_RECOMMENDED, Response::HTTP_UNPROCESSABLE_ENTITY, 'Only recommended features can be deferred.');

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:10000'],
        ]);
        $oldStatus = $ticket->status;

        $ticket->update(['status' => Ticket::STATUS_FEATURE_APPROVED]);

        $description = 'Feature recommendation deferred back to approved requests.';
        if (! empty($validated['reason'])) {
            $description .= ' Reason: '.$validated['reason'];
        }

        $this->ticketActivityService->log($ticket, 'deferred', $description, $request->user(), $oldStatus, Ticket::STATUS_FEATURE_APPROVED);

        return back()->with('status', 'Recommendation deferred.');
    }

    public function complete(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeKielFeatureAccess($request, $ticket);
        abort_if($ticket->isBlocked(), Response::HTTP_UNPROCESSABLE_ENTITY, 'Use the Unblock button and provide a note before completing blocked features.');

        $oldStatus = $ticket->status;

        $ticket->update([
            'status' => Ticket::STATUS_FEATURE_COMPLETED,
            'completed_at' => now(),
            'actual_completed_at' => now(),
        ]);

        $this->ticketActivityService->log($ticket, 'completed', 'Feature marked completed with completion timestamps.', $request->user(), $oldStatus, Ticket::STATUS_FEATURE_COMPLETED);

        return back()->with('status', 'Feature marked completed.');
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeKielFeatureAccess($request, $ticket);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'urgency' => ['required', Rule::in(Ticket::URGENCIES)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $oldValues = $ticket->only(['title', 'description', 'urgency', 'assigned_to', 'start_date', 'due_date', 'estimated_hours']);

        DB::transaction(function () use ($request, $ticket, $validated, $oldValues) {
            $ticket->update([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'urgency' => $validated['urgency'],
                'assigned_to' => $validated['assigned_to'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'estimated_hours' => $validated['estimated_hours'] ?? null,
            ]);

            $newValues = $ticket->only(['title', 'description', 'urgency', 'assigned_to', 'start_date', 'due_date', 'estimated_hours']);

            if ($oldValues !== $newValues) {
                $this->ticketActivityService->log($ticket, 'feature details updated', 'Feature planning details updated.', $request->user(), $oldValues, $newValues);
            }
        });

        return back()->with('status', 'Feature details updated.');
    }

    private function visibleFeatures(Request $request)
    {
        $query = Ticket::query()
            ->where('type', Ticket::TYPE_FEATURE)
            ->whereIn('status', Ticket::FEATURE_STATUSES);

        if (! $request->user()->isKielUser()) {
            $query->where('client_id', $request->user()->client_id);
        }

        return $query;
    }

    private function authorizeFeatureAccess(Request $request, Ticket $ticket): void
    {
        abort_unless($request->user()->can('view features'), Response::HTTP_FORBIDDEN);
        abort_unless($ticket->isFeature(), Response::HTTP_NOT_FOUND);
        abort_unless(in_array($ticket->status, Ticket::FEATURE_STATUSES, true), Response::HTTP_NOT_FOUND);
        abort_unless($request->user()->isKielUser() || $ticket->client_id === $request->user()->client_id, Response::HTTP_FORBIDDEN);
    }

    private function authorizeKielFeatureAccess(Request $request, Ticket $ticket): void
    {
        $this->authorizeFeatureAccess($request, $ticket);
        abort_unless($request->user()->isKielUser(), Response::HTTP_FORBIDDEN);
    }

    private function filterSoftware(Request $request)
    {
        $query = Software::query()->with('client')->where('is_enabled', true)->orderBy('name');

        if (! $request->user()->isKielUser()) {
            $query->where('client_id', $request->user()->client_id);
        }

        return $query;
    }

    private function teamMembers()
    {
        return User::role(['super_admin', 'kiel_manager', 'developer'])->orderBy('name')->get();
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
}
