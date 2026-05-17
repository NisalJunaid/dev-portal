<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Services\TicketActivityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class SprintController extends Controller
{
    public function __construct(private readonly TicketActivityService $ticketActivityService)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('view sprints'), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'client_id' => ['nullable', Rule::exists('clients', 'id')],
            'status' => ['nullable', Rule::in(Sprint::STATUSES)],
        ]);

        $query = Sprint::query()
            ->with(['client', 'software', 'starter', 'ender'])
            ->withCount('items')
            ->when(! $request->user()->isKielUser(), fn ($query) => $query->where('client_id', $request->user()->client_id))
            ->when(($validated['client_id'] ?? null) && $request->user()->isKielUser(), fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('started_at')
            ->latest('created_at');

        return view('sprints.index', [
            'sprints' => $query->paginate(12)->withQueryString(),
            'clients' => Client::orderBy('name')->get(),
            'filters' => $validated,
            'isKielUser' => $request->user()->isKielUser(),
        ]);
    }

    public function startForm(Request $request): View
    {
        abort_unless($request->user()->isKielUser(), Response::HTTP_FORBIDDEN);
        abort_unless($request->user()->can('view sprints'), Response::HTTP_FORBIDDEN);

        return view('sprints.start', [
            'clients' => Client::query()
                ->withCount(['tickets as next_sprint_features_count' => fn ($query) => $query
                    ->where('type', Ticket::TYPE_FEATURE)
                    ->where('status', Ticket::STATUS_NEXT_SPRINT)])
                ->orderBy('name')
                ->get(),
            'activeSprints' => Sprint::query()
                ->where('status', Sprint::STATUS_IN_PROGRESS)
                ->with('client')
                ->get()
                ->keyBy('client_id'),
        ]);
    }

    public function show(Request $request, Sprint $sprint): View
    {
        $this->authorizeSprintAccess($request, $sprint);

        $sprint->load([
            'client',
            'software',
            'starter',
            'ender',
            'activities.user',
            'tickets' => fn ($query) => $query->with(['client', 'software', 'assignee'])->orderByPivot('position'),
        ]);

        $completedTickets = $sprint->tickets->filter(fn (Ticket $ticket) => in_array($ticket->status, [Ticket::STATUS_FEATURE_COMPLETED, Ticket::STATUS_BUG_COMPLETED], true))->values();
        $incompleteTickets = $sprint->tickets->reject(fn (Ticket $ticket) => in_array($ticket->status, [Ticket::STATUS_FEATURE_COMPLETED, Ticket::STATUS_BUG_COMPLETED], true))->values();
        $ticketActivities = TicketActivity::query()
            ->with(['ticket', 'user'])
            ->whereIn('ticket_id', $sprint->tickets->pluck('id'))
            ->latest()
            ->limit(100)
            ->get();

        return view('sprints.show', [
            'sprint' => $sprint,
            'completedTickets' => $completedTickets,
            'incompleteTickets' => $incompleteTickets,
            'ticketActivities' => $ticketActivities,
            'isKielUser' => $request->user()->isKielUser(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isKielUser(), Response::HTTP_FORBIDDEN);
        abort_unless($request->user()->can('view sprints'), Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')],
        ]);

        $sprint = DB::transaction(function () use ($request, $validated) {
            $client = Client::query()->lockForUpdate()->findOrFail($validated['client_id']);

            $activeSprintExists = Sprint::query()
                ->where('client_id', $client->id)
                ->where('status', Sprint::STATUS_IN_PROGRESS)
                ->lockForUpdate()
                ->exists();

            if ($activeSprintExists) {
                throw ValidationException::withMessages([
                    'client_id' => 'This client already has a sprint in progress. Complete it before starting the next sprint cycle.',
                ]);
            }

            $featureTickets = Ticket::query()
                ->where('client_id', $client->id)
                ->where('type', Ticket::TYPE_FEATURE)
                ->where('status', Ticket::STATUS_NEXT_SPRINT)
                ->orderByRaw('priority_order is null')
                ->orderBy('priority_order')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            $bugTickets = Ticket::query()
                ->where('client_id', $client->id)
                ->where('type', Ticket::TYPE_BUG)
                ->whereIn('status', [Ticket::STATUS_BUG_PENDING, Ticket::STATUS_BUG_BLOCKED])
                ->orderByRaw('priority_order is null')
                ->orderBy('priority_order')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            if ($featureTickets->isEmpty() && $bugTickets->isEmpty()) {
                throw ValidationException::withMessages([
                    'client_id' => 'No sprint-ready feature or bug tickets are ready for this client.',
                ]);
            }

            $sprintNo = ((int) Sprint::query()->where('client_id', $client->id)->max('sprint_no')) + 1;
            $startedAt = now();
            $softwareIds = $featureTickets->pluck('software_id')->merge($bugTickets->pluck('software_id'))->filter()->unique()->values();

            $sprint = Sprint::create([
                'client_id' => $client->id,
                'software_id' => $softwareIds->count() === 1 ? $softwareIds->first() : null,
                'sprint_no' => $sprintNo,
                'name' => 'Sprint Cycle '.$sprintNo.' - '.$startedAt->format('M j, Y'),
                'status' => Sprint::STATUS_IN_PROGRESS,
                'started_at' => $startedAt,
                'started_by' => $request->user()->id,
            ]);

            $sprint->activities()->create([
                'user_id' => $request->user()->id,
                'action' => 'started',
                'description' => 'Sprint started with '.$featureTickets->count().' generated implementation tasks and '.$bugTickets->count().' bugs.',
            ]);

            $position = 1;
            foreach ($featureTickets->values() as $ticket) {
                $generatedTask = Ticket::create([
                    'client_id' => $ticket->client_id,
                    'software_id' => $ticket->software_id,
                    'submitted_by' => $request->user()->id,
                    'assigned_to' => $ticket->assigned_to,
                    'ticket_no' => app(\App\Services\TicketNumberService::class)->next(),
                    'title' => $ticket->title,
                    'description' => $ticket->description,
                    'urgency' => $ticket->urgency,
                    'type' => Ticket::TYPE_TASK,
                    'status' => Ticket::STATUS_BACKLOG,
                    'submitted_at' => now(),
                    'start_date' => $ticket->start_date,
                    'due_date' => $ticket->due_date,
                    'estimated_hours' => $ticket->estimated_hours,
                    'source_feature_id' => $ticket->id,
                    'generated_from_sprint_id' => $sprint->id,
                    'is_generated_task' => true,
                ]);

                $sprint->items()->create([
                    'ticket_id' => $generatedTask->id,
                    'position' => $position++,
                ]);

                $this->ticketActivityService->log($generatedTask, 'created from feature for sprint', 'Implementation task generated for '.$sprint->name.'.', $request->user());
                $this->ticketActivityService->log($ticket, 'converted to task for sprint', 'Feature converted to implementation task '.$generatedTask->ticket_no.' for '.$sprint->name.'.', $request->user());
            }

            foreach ($bugTickets->values() as $bugTicket) {
                $sprint->items()->create([
                    'ticket_id' => $bugTicket->id,
                    'position' => $position++,
                ]);
            }

            return $sprint;
        });

        return redirect()->route('sprints.show', $sprint)->with('status', 'Sprint started: approved features were converted to tasks and selected bugs were attached.');
    }

    public function complete(Request $request, Sprint $sprint): RedirectResponse
    {
        abort_unless($request->user()->isKielUser(), Response::HTTP_FORBIDDEN);
        $this->authorizeSprintAccess($request, $sprint);
        abort_unless($sprint->status === Sprint::STATUS_IN_PROGRESS, Response::HTTP_UNPROCESSABLE_ENTITY, 'Only in-progress sprints can be completed.');

        DB::transaction(function () use ($request, $sprint) {
            $sprint->load('tickets');
            $endedAt = now();
            $startedAt = $sprint->started_at ?? $endedAt;
            $completedCount = $sprint->tickets->where('status', Ticket::STATUS_FEATURE_COMPLETED)->count();
            $incompleteCount = $sprint->tickets->count() - $completedCount;

            $sprint->update([
                'status' => Sprint::STATUS_COMPLETED,
                'ended_at' => $endedAt,
                'duration_seconds' => $startedAt->diffInSeconds($endedAt),
                'ended_by' => $request->user()->id,
            ]);

            $sprint->activities()->create([
                'user_id' => $request->user()->id,
                'action' => 'completed',
                'description' => 'Sprint completed with '.$completedCount.' completed and '.$incompleteCount.' incomplete '.str('ticket')->plural($incompleteCount).'.',
            ]);
        });

        return redirect()->route('sprints.show', $sprint)->with('status', 'Sprint completed. Incomplete items remain visible for review.');
    }

    private function authorizeSprintAccess(Request $request, Sprint $sprint): void
    {
        abort_unless($request->user()->can('view sprints'), Response::HTTP_FORBIDDEN);
        abort_unless($request->user()->isKielUser() || $sprint->client_id === $request->user()->client_id, Response::HTTP_FORBIDDEN);
    }
}
