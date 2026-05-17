<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Software;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TimeLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if ($user->isClientUser()) {
            return $this->clientDashboard($request);
        }

        $tickets = Ticket::query();
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        return view('app.dashboard', [
            'mode' => 'kiel',
            'workspaceLabel' => 'Kiel global workspace',
            'summaryCards' => [
                ['label' => 'Total tickets', 'value' => (clone $tickets)->count(), 'tone' => 'indigo'],
                ['label' => 'Open bugs', 'value' => (clone $tickets)->where('type', Ticket::TYPE_BUG)->whereNotIn('status', [Ticket::STATUS_BUG_COMPLETED, Ticket::STATUS_REJECTED])->count(), 'tone' => 'rose'],
                ['label' => 'Open features', 'value' => (clone $tickets)->where('type', Ticket::TYPE_FEATURE)->whereNotIn('status', [Ticket::STATUS_FEATURE_COMPLETED, Ticket::STATUS_REJECTED])->count(), 'tone' => 'emerald'],
                ['label' => 'Blocked tickets', 'value' => (clone $tickets)->whereIn('status', [Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_FEATURE_BLOCKED])->count(), 'tone' => 'amber'],
                ['label' => 'Active sprints', 'value' => Sprint::where('status', Sprint::STATUS_IN_PROGRESS)->count(), 'tone' => 'sky'],
                ['label' => 'Overdue tasks', 'value' => (clone $tickets)->whereNotNull('due_date')->whereDate('due_date', '<', today())->whereNotIn('status', [Ticket::STATUS_BUG_COMPLETED, Ticket::STATUS_FEATURE_COMPLETED, Ticket::STATUS_REJECTED])->count(), 'tone' => 'red'],
                ['label' => 'Time tracked this week', 'value' => $this->formatHours(TimeLog::whereBetween('started_at', [$weekStart, $weekEnd])->sum('duration_seconds')).'h', 'tone' => 'violet'],
            ],
            'ticketsByClient' => Client::withCount(['tickets as tickets_count' => fn (Builder $query) => $query->where('status', '!=', Ticket::STATUS_REJECTED)])
                ->orderByDesc('tickets_count')
                ->limit(8)
                ->get(),
            'ticketsBySoftware' => Software::withCount(['tickets as tickets_count' => fn (Builder $query) => $query->where('status', '!=', Ticket::STATUS_REJECTED)])
                ->orderByDesc('tickets_count')
                ->limit(8)
                ->get(),
            'recentActivities' => TicketActivity::with(['ticket.client', 'user'])->latest()->limit(10)->get(),
        ]);
    }

    private function clientDashboard(Request $request): View
    {
        $user = $request->user();
        $tickets = Ticket::where('client_id', $user->client_id);
        $currentSprint = Sprint::withCount([
            'items',
            'items as completed_items_count' => fn (Builder $query) => $query->whereHas('ticket', fn (Builder $ticketQuery) => $ticketQuery->whereIn('status', [Ticket::STATUS_BUG_COMPLETED, Ticket::STATUS_FEATURE_COMPLETED])),
        ])->where('client_id', $user->client_id)->where('status', Sprint::STATUS_IN_PROGRESS)->latest('started_at')->first();

        return view('app.dashboard', [
            'mode' => 'client',
            'workspaceLabel' => $user->client?->name ?? 'Client workspace',
            'summaryCards' => [
                ['label' => 'My submitted tickets', 'value' => (clone $tickets)->where('submitted_by', $user->id)->count(), 'tone' => 'indigo'],
                ['label' => 'Approved features', 'value' => (clone $tickets)->where('type', Ticket::TYPE_FEATURE)->whereIn('status', [Ticket::STATUS_FEATURE_APPROVED, Ticket::STATUS_NEXT_SPRINT, Ticket::STATUS_IN_PROGRESS])->count(), 'tone' => 'emerald'],
                ['label' => 'Recommended features', 'value' => (clone $tickets)->where('status', Ticket::STATUS_RECOMMENDED)->count(), 'tone' => 'violet'],
                ['label' => 'Blocked requiring attention', 'value' => (clone $tickets)->whereIn('status', [Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_FEATURE_BLOCKED])->count(), 'tone' => 'amber'],
            ],
            'currentSprint' => $currentSprint,
            'blockedTickets' => (clone $tickets)->with(['software', 'activeBlock'])->whereIn('status', [Ticket::STATUS_BUG_BLOCKED, Ticket::STATUS_FEATURE_BLOCKED])->latest('updated_at')->limit(6)->get(),
            'recentlyCompleted' => (clone $tickets)->with('software')->whereIn('status', [Ticket::STATUS_BUG_COMPLETED, Ticket::STATUS_FEATURE_COMPLETED])->latest('completed_at')->limit(6)->get(),
        ]);
    }

    private function formatHours(?int $seconds): string
    {
        return number_format(($seconds ?? 0) / 3600, 2);
    }
}
