<?php

namespace App\Http\Controllers;

use App\Models\Software;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\TicketActivityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class BugController extends Controller
{
    private const TRANSITIONS = [
        Ticket::STATUS_BUG_PENDING => [Ticket::STATUS_BUG_PENDING, Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_BUG_COMPLETED],
        Ticket::STATUS_BUG_BLOCKED => [Ticket::STATUS_BUG_PENDING, Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_BUG_COMPLETED],
        Ticket::STATUS_BUG_COMPLETED => [Ticket::STATUS_BUG_PENDING, Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_BUG_COMPLETED],
    ];

    public function __construct(private readonly TicketActivityService $ticketActivityService)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('view bugs'), Response::HTTP_FORBIDDEN);

        $view = $request->query('view') === 'kanban' ? 'kanban' : 'list';
        $query = $this->visibleBugs($request)
            ->with(['client', 'software', 'submitter', 'assignee'])
            ->orderByRaw("CASE status WHEN 'bug_pending' THEN 1 WHEN 'bug_blocked' THEN 2 WHEN 'bug_completed' THEN 3 ELSE 4 END")
            ->latest('submitted_at');

        $bugs = $view === 'kanban'
            ? $query->get()->groupBy('status')
            : $query->paginate(12)->withQueryString();

        return view('bugs.index', [
            'bugs' => $bugs,
            'view' => $view,
            'columns' => $this->kanbanColumns(),
            'canUpdateBugs' => $request->user()->can('update bugs'),
            'isKielUser' => $request->user()->isKielUser(),
        ]);
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $this->authorizeBugAccess($request, $ticket);

        $ticket->load(['client', 'software', 'submitter', 'assignee', 'activities.user']);

        return view('bugs.show', [
            'ticket' => $ticket,
            'comments' => $this->visibleComments($request, $ticket),
            'teamMembers' => User::role(['super_admin', 'kiel_manager', 'developer'])->orderBy('name')->get(),
            'softwares' => Software::with('client')->where('is_enabled', true)->orderBy('name')->get(),
            'canUpdateBugs' => $request->user()->can('update bugs'),
            'isKielUser' => $request->user()->isKielUser(),
        ]);
    }

    public function pending(Request $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        return $this->transition($request, $ticket, Ticket::STATUS_BUG_PENDING);
    }

    public function complete(Request $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        return $this->transition($request, $ticket, Ticket::STATUS_BUG_COMPLETED);
    }

    public function block(Request $request, Ticket $ticket): JsonResponse|RedirectResponse
    {
        return $this->transition($request, $ticket, Ticket::STATUS_BUG_BLOCKED);
    }

    private function transition(Request $request, Ticket $ticket, string $newStatus): JsonResponse|RedirectResponse
    {
        $this->authorizeBugAccess($request, $ticket);
        abort_unless($request->user()->can('update bugs'), Response::HTTP_FORBIDDEN);
        abort_unless($this->canTransition($ticket->status, $newStatus), Response::HTTP_UNPROCESSABLE_ENTITY, 'That bug status transition is not allowed.');

        $oldStatus = $ticket->status;

        DB::transaction(function () use ($request, $ticket, $oldStatus, $newStatus) {
            $updates = ['status' => $newStatus];

            if ($newStatus === Ticket::STATUS_BUG_COMPLETED) {
                $updates['completed_at'] = now();
                $updates['actual_completed_at'] = now();
            } elseif ($oldStatus === Ticket::STATUS_BUG_COMPLETED) {
                $updates['completed_at'] = null;
                $updates['actual_completed_at'] = null;
            }

            $ticket->update($updates);

            if ($oldStatus !== $newStatus) {
                $description = $newStatus === Ticket::STATUS_BUG_COMPLETED
                    ? 'Bug marked completed with completion timestamps.'
                    : 'Bug status updated.';

                $this->ticketActivityService->log($ticket, 'bug status changed', $description, $request->user(), $oldStatus, $newStatus);
            }
        });

        $ticket->refresh();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Bug status updated.',
                'ticket' => [
                    'id' => $ticket->id,
                    'status' => $ticket->status,
                    'formatted_status' => $ticket->formattedStatus(),
                    'completed_at' => $ticket->completed_at?->toISOString(),
                    'actual_completed_at' => $ticket->actual_completed_at?->toISOString(),
                ],
            ]);
        }

        return back()->with('status', 'Bug status updated.');
    }

    private function visibleBugs(Request $request)
    {
        $query = Ticket::query()
            ->where('type', Ticket::TYPE_BUG)
            ->whereIn('status', Ticket::BUG_STATUSES);

        if (! $request->user()->isKielUser()) {
            $query->where('client_id', $request->user()->client_id);
        }

        return $query;
    }

    private function authorizeBugAccess(Request $request, Ticket $ticket): void
    {
        abort_unless($request->user()->can('view bugs'), Response::HTTP_FORBIDDEN);
        abort_unless($ticket->isBug(), Response::HTTP_NOT_FOUND);
        abort_unless(in_array($ticket->status, Ticket::BUG_STATUSES, true), Response::HTTP_NOT_FOUND);
        abort_unless($request->user()->isKielUser() || $ticket->client_id === $request->user()->client_id, Response::HTTP_FORBIDDEN);
    }

    private function canTransition(string $currentStatus, string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$currentStatus] ?? [], true);
    }

    private function kanbanColumns(): array
    {
        return [
            Ticket::STATUS_BUG_PENDING => 'Pending',
            Ticket::STATUS_BUG_BLOCKED => 'Blocked',
            Ticket::STATUS_BUG_COMPLETED => 'Completed',
        ];
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
