<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Services\TicketActivityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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

        $data = $this->dashboardData($request);

        return view('sprints.index', $data);
    }

    public function dashboardSections(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('view sprints'), Response::HTTP_FORBIDDEN);

        $data = $this->dashboardData($request);

        return response()->json([
            'current_sprint_html' => view('sprints.partials.current-sprint', $data)->render(),
            'approved_features_html' => view('sprints.partials.approved-features-table', $data)->render(),
            'future_features_html' => view('sprints.partials.future-features-table', $data)->render(),
            'history_html' => view('sprints.partials.history-table', $data)->render(),
            'stats' => $data['currentSprintStats'] ?? null,
        ]);
    }

    private function dashboardData(Request $request): array
    {
        $validated = $request->validate([
            'client_id' => ['nullable', Rule::exists('clients', 'id')],
            'status' => ['nullable', Rule::in(Sprint::STATUSES)],
        ]);

        $isKielUser = $request->user()->isKielUser();
        $clients = Client::orderBy('name')->get();

        $selectedClientId = null;
        if (! $isKielUser) {
            $selectedClientId = $request->user()->client_id;
        } elseif (! empty($validated['client_id'])) {
            $selectedClientId = (int) $validated['client_id'];
        } else {
            $approvedClientIds = Ticket::query()->where('type', Ticket::TYPE_FEATURE)->where('status', Ticket::STATUS_NEXT_SPRINT)->distinct()->pluck('client_id')->filter();
            if ($approvedClientIds->count() === 1) {
                $selectedClientId = (int) $approvedClientIds->first();
            }
        }

        $featureQuery = Ticket::query()->with(['software', 'submitter', 'assignee', 'parent', 'generatedTasks'])->where('type', Ticket::TYPE_FEATURE)
            ->when($selectedClientId, fn ($q) => $q->where('client_id', $selectedClientId));

        if (! $isKielUser) {
            $featureQuery->where('client_id', $request->user()->client_id);
        }

        $approvedFeatures = (clone $featureQuery)
            ->where('status', Ticket::STATUS_NEXT_SPRINT)
            ->whereDoesntHave('generatedTasks', fn ($q) => $q->whereHas('generatedFromSprint', fn ($s) => $s->where('status', Sprint::STATUS_COMPLETED)))
            ->latest('updated_at')
            ->get();
        $futureFeatures = (clone $featureQuery)
            ->whereIn('status', [Ticket::STATUS_FEATURE_APPROVED, Ticket::STATUS_RECOMMENDED])
            ->latest('updated_at')
            ->get();

        $canStartSprint = (bool) $selectedClientId && $approvedFeatures->isNotEmpty();

        $sprintQuery = Sprint::query()
            ->with(['client', 'software', 'starter', 'ender'])
            ->withCount('items')
            ->when(! $isKielUser, fn ($q) => $q->where('client_id', $request->user()->client_id))
            ->when(($validated['status'] ?? null), fn ($q, $status) => $q->where('status', $status))
            ->when($selectedClientId, fn ($q) => $q->where('client_id', $selectedClientId))
            ->latest('started_at')
            ->latest('created_at');

        $activeSprints = Sprint::query()
            ->with('client')
            ->where('status', Sprint::STATUS_IN_PROGRESS)
            ->when(! $isKielUser, fn ($q) => $q->where('client_id', $request->user()->client_id))
            ->when($isKielUser && $selectedClientId, fn ($q) => $q->where('client_id', $selectedClientId))
            ->latest('started_at')
            ->get();

        $currentSprint = $activeSprints->first();

        $currentSprintStats = null;
        if ($currentSprint) {
            $currentSprint->load('tickets');
            $total = $currentSprint->tickets->count();
            $completed = $currentSprint->tickets->filter(fn (Ticket $ticket) => in_array($ticket->status, [Ticket::STATUS_BUG_COMPLETED, Ticket::STATUS_FEATURE_COMPLETED, Ticket::STATUS_TASK_COMPLETED], true))->count();
            $inProgress = $currentSprint->tickets->where('status', Ticket::STATUS_IN_PROGRESS)->count();
            $backlog = $currentSprint->tickets->where('status', Ticket::STATUS_BACKLOG)->count();
            $blocked = $currentSprint->tickets->filter(fn (Ticket $ticket) => $ticket->isBlocked())->count();
            $rejected = $currentSprint->tickets->where('status', Ticket::STATUS_REJECTED)->count();
            $active = $currentSprint->tickets->filter(fn (Ticket $ticket) => $ticket->isActiveForSprint())->count();

            $currentSprintStats = [
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'in_progress_tasks' => $inProgress,
                'backlog_tasks' => $backlog,
                'blocked_tasks' => $blocked,
                'rejected_tasks' => $rejected,
                'remaining_tasks' => $active,
                'active_count' => $active,
                'can_end' => $active === 0,
                'elapsed_seconds' => $currentSprint->elapsedSeconds(),
                'timer_status' => $currentSprint->timer_status,
            ];
        }

        return [
            'sprints' => $sprintQuery->where('status', Sprint::STATUS_COMPLETED)->paginate(12)->withQueryString(),
            'approvedFeatures' => $approvedFeatures,
            'futureFeatures' => $futureFeatures,
            'currentSprint' => $currentSprint,
            'activeSprints' => $activeSprints,
            'currentSprintStats' => $currentSprintStats,
            'clients' => $clients,
            'softwares' => $isKielUser ? \App\Models\Software::where('is_enabled', true)->orderBy('name')->get() : \App\Models\Software::where('is_enabled', true)->where('client_id', $request->user()->client_id)->orderBy('name')->get(),
            'filters' => array_merge($validated, ['client_id' => $selectedClientId]),
            'isKielUser' => $isKielUser,
            'selectedClientId' => $selectedClientId,
            'canStartSprint' => $canStartSprint,
        ];
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
            'activeSprints' => Sprint::query()->where('status', Sprint::STATUS_IN_PROGRESS)->with('client')->get()->keyBy('client_id'),
        ]);
    }

    public function show(Request $request, Sprint $sprint): View
    {
        $this->authorizeSprintAccess($request, $sprint);
        $sprint->load(['client', 'software', 'starter', 'ender', 'activities.user', 'tickets' => fn ($query) => $query->with(['client', 'software', 'assignee'])->orderByPivot('position')]);
        $completedTickets = $sprint->tickets->filter(fn (Ticket $ticket) => $ticket->isDone())->values();
        $incompleteTickets = $sprint->tickets->reject(fn (Ticket $ticket) => $ticket->isDone())->values();
        $ticketActivities = TicketActivity::query()->with(['ticket', 'user'])->whereIn('ticket_id', $sprint->tickets->pluck('id'))->latest()->limit(100)->get();

        return view('sprints.show', compact('sprint', 'completedTickets', 'incompleteTickets', 'ticketActivities') + ['isKielUser' => $request->user()->isKielUser()]);
    }

    public function start(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->isKielUser(), Response::HTTP_FORBIDDEN);
        abort_unless($request->user()->can('view sprints'), Response::HTTP_FORBIDDEN);
        $validated = $request->validate(['client_id' => ['required', Rule::exists('clients', 'id')]]);

        $sprint = DB::transaction(function () use ($request, $validated) {
            $client = Client::query()->lockForUpdate()->findOrFail($validated['client_id']);
            if (Sprint::query()->where('client_id', $client->id)->where('status', Sprint::STATUS_IN_PROGRESS)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['client_id' => 'This client already has a sprint in progress.']);
            }

            $featureTickets = Ticket::query()->where('client_id', $client->id)->where('type', Ticket::TYPE_FEATURE)->where('status', Ticket::STATUS_NEXT_SPRINT)->orderByRaw('priority_order is null')->orderBy('priority_order')->orderBy('created_at')->lockForUpdate()->get();
            $bugTickets = Ticket::query()->where('client_id', $client->id)->where('type', Ticket::TYPE_BUG)->whereIn('status', [Ticket::STATUS_BUG_PENDING, Ticket::STATUS_BUG_BLOCKED])->orderByRaw('priority_order is null')->orderBy('priority_order')->orderBy('created_at')->lockForUpdate()->get();

            if ($featureTickets->isEmpty() && $bugTickets->isEmpty()) {
                throw ValidationException::withMessages(['client_id' => 'No sprint-ready feature or bug tickets are ready for this client.']);
            }

            $sprintNo = ((int) Sprint::query()->where('client_id', $client->id)->max('sprint_no')) + 1;
            $startedAt = now();
            $softwareIds = $featureTickets->pluck('software_id')->merge($bugTickets->pluck('software_id'))->filter()->unique()->values();
            $sprint = Sprint::create(['client_id' => $client->id,'software_id' => $softwareIds->count() === 1 ? $softwareIds->first() : null,'sprint_no' => $sprintNo,'name' => 'Sprint Cycle '.$sprintNo.' - '.$startedAt->format('M j, Y'),'status' => Sprint::STATUS_IN_PROGRESS,'started_at' => $startedAt,'started_by' => $request->user()->id,'timer_status' => Sprint::TIMER_RUNNING,'paused_at' => null,'accumulated_paused_seconds' => 0]);
            $sprint->activities()->create(['user_id' => $request->user()->id,'action' => 'started','description' => 'Sprint started with '.$featureTickets->count().' generated implementation tasks and '.$bugTickets->count().' bugs.']);

            $position = 1;
            foreach ($featureTickets as $ticket) {
                $generatedTask = Ticket::create(['client_id' => $ticket->client_id,'software_id' => $ticket->software_id,'submitted_by' => $request->user()->id,'assigned_to' => $ticket->assigned_to,'ticket_no' => app(\App\Services\TicketNumberService::class)->next(),'title' => $ticket->title,'description' => $ticket->description,'urgency' => $ticket->urgency,'type' => Ticket::TYPE_TASK,'status' => Ticket::STATUS_BACKLOG,'submitted_at' => now(),'start_date' => $ticket->start_date,'due_date' => $ticket->due_date,'estimated_hours' => $ticket->estimated_hours,'source_feature_id' => $ticket->id,'generated_from_sprint_id' => $sprint->id,'is_generated_task' => true]);
                $sprint->items()->create(['ticket_id' => $generatedTask->id, 'position' => $position++]);
                $this->ticketActivityService->log($generatedTask, 'created from feature for sprint', 'Implementation task generated for '.$sprint->name.'.', $request->user());
            }
            foreach ($bugTickets as $bugTicket) {
                $sprint->items()->create(['ticket_id' => $bugTicket->id, 'position' => $position++]);
            }

            return $sprint;
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Sprint started successfully.','sprint' => ['id' => $sprint->id,'name' => $sprint->name,'status' => $sprint->status,'timer_status' => $sprint->timer_status,'elapsed_seconds' => $sprint->elapsedSeconds(),'show_url' => route('sprints.show', $sprint)]]);
        }

        return redirect()->route('sprints.show', $sprint)->with('status', 'Sprint started.');
    }

    public function complete(Request $request, Sprint $sprint): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->isKielUser(), Response::HTTP_FORBIDDEN);
        $this->authorizeSprintAccess($request, $sprint);
        abort_unless($sprint->status === Sprint::STATUS_IN_PROGRESS, Response::HTTP_UNPROCESSABLE_ENTITY);
        $sprint->load('tickets');
        $activeItems = $sprint->tickets->filter(fn (Ticket $ticket) => $ticket->isActiveForSprint())->values();
        if ($activeItems->isNotEmpty()) {
            $message = 'Sprint cannot be ended until all sprint tasks are completed, rejected, or blocked.';
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'active_count' => $activeItems->count(),
                    'active_items' => $activeItems->take(5)->pluck('ticket_no')->values(),
                ], 422);
            }

            throw ValidationException::withMessages(['sprint' => $message]);
        }

        DB::transaction(function () use ($request, $sprint) {
            $completedCount = $sprint->tickets->whereIn('status', [Ticket::STATUS_BUG_COMPLETED, Ticket::STATUS_FEATURE_COMPLETED, Ticket::STATUS_TASK_COMPLETED])->count();
            $blockedCount = $sprint->tickets->whereIn('status', [Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_FEATURE_BLOCKED])->count();
            $rejectedCount = $sprint->tickets->where('status', Ticket::STATUS_REJECTED)->count();
            $sprint->update(['status' => Sprint::STATUS_COMPLETED,'ended_at' => now(),'duration_seconds' => $sprint->elapsedSeconds(),'timer_status' => Sprint::TIMER_COMPLETED,'ended_by' => $request->user()->id]);
            $sprint->activities()->create(['user_id' => $request->user()->id,'action' => 'completed','description' => 'Sprint completed with '.$completedCount.' completed, '.$blockedCount.' blocked, and '.$rejectedCount.' rejected items.']);
        });

        return $request->expectsJson() ? response()->json(['message' => 'Sprint completed.', 'timer_status' => Sprint::TIMER_COMPLETED]) : redirect()->route('sprints.show', $sprint)->with('status', 'Sprint completed.');
    }

    public function pause(Request $request, Sprint $sprint): JsonResponse
    { abort_unless($request->user()->isKielUser(), Response::HTTP_FORBIDDEN); $this->authorizeSprintAccess($request, $sprint); abort_unless($sprint->status===Sprint::STATUS_IN_PROGRESS && $sprint->isRunningTimer(), Response::HTTP_UNPROCESSABLE_ENTITY); $sprint->update(['timer_status'=>Sprint::TIMER_PAUSED,'paused_at'=>now()]); return response()->json(['message'=>'Sprint paused.','elapsed_seconds'=>$sprint->elapsedSeconds(),'timer_status'=>$sprint->timer_status]); }
    public function resume(Request $request, Sprint $sprint): JsonResponse
    { abort_unless($request->user()->isKielUser(), Response::HTTP_FORBIDDEN); $this->authorizeSprintAccess($request, $sprint); abort_unless($sprint->status===Sprint::STATUS_IN_PROGRESS && $sprint->isPausedTimer(), Response::HTTP_UNPROCESSABLE_ENTITY); $paused = $sprint->paused_at ? $sprint->paused_at->diffInSeconds(now()) : 0; $sprint->update(['timer_status'=>Sprint::TIMER_RUNNING,'paused_at'=>null,'accumulated_paused_seconds'=>((int)$sprint->accumulated_paused_seconds)+$paused]); return response()->json(['message'=>'Sprint resumed.','elapsed_seconds'=>$sprint->elapsedSeconds(),'timer_status'=>$sprint->timer_status]); }
    public function end(Request $request, Sprint $sprint): JsonResponse|RedirectResponse { return $this->complete($request, $sprint); }
    private function authorizeSprintAccess(Request $request, Sprint $sprint): void
    { abort_unless($request->user()->can('view sprints'), Response::HTTP_FORBIDDEN); abort_unless($request->user()->isKielUser() || $sprint->client_id===$request->user()->client_id, Response::HTTP_FORBIDDEN); }
}
