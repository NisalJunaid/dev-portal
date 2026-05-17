<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Software;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\TicketBlock;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('view reports'), 403);

        $filters = $this->filters($request);

        return view('reports.index', [
            ...$this->filterOptions($request),
            'filters' => $filters,
            'excelAvailable' => $this->excelAvailable(),
            'cards' => [
                'tracked_hours' => $this->formatHours($this->timeQuery($request, $filters)->sum('duration_seconds')),
                'tickets' => (clone $this->ticketQuery($request, $filters))->count(),
                'sprints' => (clone $this->sprintQuery($request, $filters))->count(),
                'blocked_hours' => $this->formatHours($this->blockedQuery($request, $filters)->get()->sum(fn (TicketBlock $block) => $block->currentDurationSeconds())),
            ],
        ]);
    }

    public function time(Request $request): View
    {
        abort_unless($request->user()->can('view reports'), 403);

        $filters = $this->filters($request);
        $logs = $this->timeQuery($request, $filters)
            ->with(['ticket.client', 'ticket.software', 'client', 'software', 'user'])
            ->latest('started_at')
            ->paginate(20)
            ->withQueryString();

        return view('reports.time', [
            ...$this->filterOptions($request),
            'filters' => $filters,
            'excelAvailable' => $this->excelAvailable(),
            'logs' => $logs,
            'totalSeconds' => $this->timeQuery($request, $filters)->sum('duration_seconds'),
            'isClientUser' => $request->user()->isClientUser(),
        ]);
    }

    public function tickets(Request $request): View
    {
        abort_unless($request->user()->can('view reports'), 403);

        $filters = $this->filters($request);
        $tickets = $this->ticketQuery($request, $filters)
            ->with(['client', 'software', 'submitter', 'assignee', 'activeBlock'])
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('reports.tickets', [
            ...$this->filterOptions($request),
            'filters' => $filters,
            'excelAvailable' => $this->excelAvailable(),
            'tickets' => $tickets,
            'summary' => [
                'total' => $this->ticketQuery($request, $filters)->count(),
                'bugs' => $this->ticketQuery($request, $filters)->where('type', Ticket::TYPE_BUG)->count(),
                'features' => $this->ticketQuery($request, $filters)->where('type', Ticket::TYPE_FEATURE)->count(),
                'blocked' => $this->ticketQuery($request, $filters)->whereIn('status', [Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_FEATURE_BLOCKED])->count(),
            ],
        ]);
    }

    public function sprints(Request $request): View
    {
        abort_unless($request->user()->can('view reports'), 403);

        $filters = $this->filters($request);
        $sprints = $this->sprintQuery($request, $filters)
            ->with(['client', 'software'])
            ->withCount('items')
            ->latest('started_at')
            ->paginate(20)
            ->withQueryString();

        return view('reports.sprints', [
            ...$this->filterOptions($request),
            'filters' => $filters,
            'excelAvailable' => $this->excelAvailable(),
            'sprints' => $sprints,
            'summary' => [
                'total' => $this->sprintQuery($request, $filters)->count(),
                'active' => $this->sprintQuery($request, $filters)->where('status', Sprint::STATUS_IN_PROGRESS)->count(),
                'completed' => $this->sprintQuery($request, $filters)->where('status', Sprint::STATUS_COMPLETED)->count(),
            ],
        ]);
    }

    public function blocked(Request $request): View
    {
        abort_unless($request->user()->can('view reports'), 403);

        $filters = $this->filters($request);
        $blocks = $this->blockedQuery($request, $filters)
            ->with(['ticket.client', 'ticket.software', 'blocker', 'unblocker'])
            ->latest('blocked_at')
            ->paginate(20)
            ->withQueryString();

        return view('reports.blocked', [
            ...$this->filterOptions($request),
            'filters' => $filters,
            'excelAvailable' => $this->excelAvailable(),
            'blocks' => $blocks,
            'totalSeconds' => $this->blockedQuery($request, $filters)->get()->sum(fn (TicketBlock $block) => $block->currentDurationSeconds()),
            'isClientUser' => $request->user()->isClientUser(),
        ]);
    }

    public function export(Request $request, string $type): Response
    {
        abort_unless($request->user()->isKielUser(), 403);
        abort_unless(in_array($type, ['time', 'tickets', 'sprints', 'blocked'], true), 404);

        $filters = $this->filters($request);
        [$headers, $rows] = match ($type) {
            'time' => $this->timeExportRows($request, $filters),
            'tickets' => $this->ticketExportRows($request, $filters),
            'sprints' => $this->sprintExportRows($request, $filters),
            'blocked' => $this->blockedExportRows($request, $filters),
        };

        if ($request->query('format') === 'xlsx' && $this->excelAvailable()) {
            $export = new class($headers, $rows) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
                public function __construct(private array $headers, private array $rows)
                {
                }

                public function array(): array
                {
                    return $this->rows;
                }

                public function headings(): array
                {
                    return $this->headers;
                }
            };

            return \Maatwebsite\Excel\Facades\Excel::download($export, $type.'-report-'.now()->format('Y-m-d').'.xlsx');
        }

        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $type.'-report-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function filters(Request $request): array
    {
        return $request->only(['client_id', 'software_id', 'user_id', 'ticket_type', 'status', 'date_from', 'date_to', 'sprint_id']);
    }

    private function filterOptions(Request $request): array
    {
        $user = $request->user();
        $clientQuery = Client::query()->orderBy('name');
        $softwareQuery = Software::query()->orderBy('name');
        $userQuery = User::query()->orderBy('name');
        $sprintQuery = Sprint::query()->orderByDesc('started_at')->orderByDesc('id');

        if ($user->isClientUser()) {
            $clientQuery->whereKey($user->client_id);
            $softwareQuery->where('client_id', $user->client_id);
            $userQuery->where('client_id', $user->client_id);
            $sprintQuery->where('client_id', $user->client_id);
        }

        return [
            'clients' => $clientQuery->get(),
            'softwares' => $softwareQuery->get(),
            'users' => $userQuery->get(),
            'sprints' => $sprintQuery->limit(100)->get(),
            'statuses' => Ticket::STATUSES,
            'ticketTypes' => Ticket::TYPES,
        ];
    }

    private function ticketQuery(Request $request, array $filters): Builder
    {
        $query = Ticket::query();
        $this->applyClientScope($request, $query);
        $this->applyTicketFilters($query, $filters);

        if (! empty($filters['sprint_id'])) {
            $query->whereHas('sprints', fn (Builder $sprintQuery) => $sprintQuery->whereKey($filters['sprint_id']));
        }

        return $query;
    }

    private function timeQuery(Request $request, array $filters): Builder
    {
        $query = TimeLog::query();
        $this->applyClientScope($request, $query);
        $this->applySharedFilters($query, $filters);
        $query->when($filters['user_id'] ?? null, fn (Builder $query, $userId) => $query->where('user_id', $userId));
        $query->when($filters['ticket_type'] ?? null, fn (Builder $query, $type) => $query->whereHas('ticket', fn (Builder $ticketQuery) => $ticketQuery->where('type', $type)));
        $query->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->whereHas('ticket', fn (Builder $ticketQuery) => $ticketQuery->where('status', $status)));
        $query->when($filters['sprint_id'] ?? null, fn (Builder $query, $sprintId) => $query->whereHas('ticket.sprints', fn (Builder $sprintQuery) => $sprintQuery->whereKey($sprintId)));
        $this->applyDateRange($query, $filters, 'started_at');

        return $query;
    }

    private function sprintQuery(Request $request, array $filters): Builder
    {
        $query = Sprint::query();
        $this->applyClientScope($request, $query);
        $this->applySharedFilters($query, $filters);
        $query->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status));
        $query->when($filters['sprint_id'] ?? null, fn (Builder $query, $sprintId) => $query->whereKey($sprintId));
        $query->when($filters['ticket_type'] ?? null, fn (Builder $query, $type) => $query->whereHas('tickets', fn (Builder $ticketQuery) => $ticketQuery->where('type', $type)));
        $query->when($filters['user_id'] ?? null, fn (Builder $query, $userId) => $query->where(fn (Builder $inner) => $inner->where('started_by', $userId)->orWhere('ended_by', $userId)));
        $this->applyDateRange($query, $filters, 'started_at');

        return $query;
    }

    private function blockedQuery(Request $request, array $filters): Builder
    {
        $query = TicketBlock::query()->whereHas('ticket', fn (Builder $ticketQuery) => $this->applyClientScope($request, $ticketQuery));
        $query->when($filters['client_id'] ?? null, fn (Builder $query, $clientId) => $query->whereHas('ticket', fn (Builder $ticketQuery) => $ticketQuery->where('client_id', $clientId)));
        $query->when($filters['software_id'] ?? null, fn (Builder $query, $softwareId) => $query->whereHas('ticket', fn (Builder $ticketQuery) => $ticketQuery->where('software_id', $softwareId)));
        $query->when($filters['user_id'] ?? null, fn (Builder $query, $userId) => $query->where('blocked_by', $userId));
        $query->when($filters['ticket_type'] ?? null, fn (Builder $query, $type) => $query->whereHas('ticket', fn (Builder $ticketQuery) => $ticketQuery->where('type', $type)));
        $query->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->whereHas('ticket', fn (Builder $ticketQuery) => $ticketQuery->where('status', $status)));
        $query->when($filters['sprint_id'] ?? null, fn (Builder $query, $sprintId) => $query->whereHas('ticket.sprints', fn (Builder $sprintQuery) => $sprintQuery->whereKey($sprintId)));
        $this->applyDateRange($query, $filters, 'blocked_at');

        return $query;
    }

    private function applyTicketFilters(Builder $query, array $filters): void
    {
        $this->applySharedFilters($query, $filters);
        $query->when($filters['user_id'] ?? null, fn (Builder $query, $userId) => $query->where(fn (Builder $inner) => $inner->where('submitted_by', $userId)->orWhere('assigned_to', $userId)));
        $query->when($filters['ticket_type'] ?? null, fn (Builder $query, $type) => $query->where('type', $type));
        $query->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status));
        $this->applyDateRange($query, $filters, 'submitted_at');
    }

    private function applySharedFilters(Builder $query, array $filters): void
    {
        $query->when($filters['client_id'] ?? null, fn (Builder $query, $clientId) => $query->where('client_id', $clientId));
        $query->when($filters['software_id'] ?? null, fn (Builder $query, $softwareId) => $query->where('software_id', $softwareId));
    }

    private function applyClientScope(Request $request, Builder $query): void
    {
        if ($request->user()->isClientUser()) {
            $query->where('client_id', $request->user()->client_id);
        }
    }

    private function applyDateRange(Builder $query, array $filters, string $column): void
    {
        $query->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->whereDate($column, '>=', $date));
        $query->when($filters['date_to'] ?? null, fn (Builder $query, $date) => $query->whereDate($column, '<=', $date));
    }

    private function timeExportRows(Request $request, array $filters): array
    {
        $rows = $this->timeQuery($request, $filters)->with(['ticket.client', 'ticket.software', 'client', 'software', 'user'])->get()->map(fn (TimeLog $log) => [
            $log->ticket?->ticket_no,
            $log->ticket?->title,
            $log->client?->name ?? $log->ticket?->client?->name,
            $log->software?->name ?? $log->ticket?->software?->name,
            $log->user?->name,
            $log->started_at?->toDateTimeString(),
            $log->ended_at?->toDateTimeString(),
            $this->formatHours($log->duration_seconds),
            $log->status,
        ])->all();

        return [['Ticket #', 'Ticket', 'Client', 'Software', 'User', 'Started', 'Ended', 'Hours', 'Timer status'], $rows];
    }

    private function ticketExportRows(Request $request, array $filters): array
    {
        $rows = $this->ticketQuery($request, $filters)->with(['client', 'software', 'submitter', 'assignee'])->get()->map(fn (Ticket $ticket) => [
            $ticket->ticket_no,
            $ticket->title,
            $ticket->type,
            $ticket->status,
            $ticket->client?->name,
            $ticket->software?->name,
            $ticket->submitter?->name,
            $ticket->assignee?->name,
            $ticket->submitted_at?->toDateTimeString(),
            $ticket->due_date?->toDateString(),
        ])->all();

        return [['Ticket #', 'Title', 'Type', 'Status', 'Client', 'Software', 'Submitter', 'Assignee', 'Submitted', 'Due'], $rows];
    }

    private function sprintExportRows(Request $request, array $filters): array
    {
        $rows = $this->sprintQuery($request, $filters)->with(['client', 'software'])->withCount('items')->get()->map(fn (Sprint $sprint) => [
            $sprint->sprint_no,
            $sprint->name,
            $sprint->status,
            $sprint->client?->name,
            $sprint->software?->name,
            $sprint->items_count,
            $sprint->started_at?->toDateTimeString(),
            $sprint->ended_at?->toDateTimeString(),
            $this->formatHours($sprint->duration_seconds ?? 0),
        ])->all();

        return [['Sprint #', 'Name', 'Status', 'Client', 'Software', 'Tickets', 'Started', 'Ended', 'Duration hours'], $rows];
    }

    private function blockedExportRows(Request $request, array $filters): array
    {
        $rows = $this->blockedQuery($request, $filters)->with(['ticket.client', 'ticket.software', 'blocker', 'unblocker'])->get()->map(fn (TicketBlock $block) => [
            $block->ticket?->ticket_no,
            $block->ticket?->title,
            $block->ticket?->client?->name,
            $block->ticket?->software?->name,
            $block->blocker?->name,
            $block->blocked_at?->toDateTimeString(),
            $block->unblocked_at?->toDateTimeString(),
            $this->formatHours($block->currentDurationSeconds()),
            $block->reason,
            $block->unblock_note,
        ])->all();

        return [['Ticket #', 'Ticket', 'Client', 'Software', 'Blocked by', 'Blocked at', 'Unblocked at', 'Blocked hours', 'Reason', 'Resolution'], $rows];
    }

    private function formatHours(?int $seconds): string
    {
        return number_format(($seconds ?? 0) / 3600, 2);
    }

    private function excelAvailable(): bool
    {
        return class_exists(\Maatwebsite\Excel\Facades\Excel::class)
            && interface_exists(\Maatwebsite\Excel\Concerns\FromArray::class)
            && interface_exists(\Maatwebsite\Excel\Concerns\WithHeadings::class);
    }
}
