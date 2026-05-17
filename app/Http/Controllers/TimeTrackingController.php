<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TimeLog;
use App\Services\TimeTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TimeTrackingController extends Controller
{
    public function __construct(private readonly TimeTrackingService $timeTrackingService)
    {
    }

    public function start(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTimerAccess($request, $ticket);

        return $this->timerResponse($ticket, $this->timeTrackingService->start($ticket, $request->user()), 'Timer started.');
    }

    public function pause(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTimerAccess($request, $ticket);

        return $this->timerResponse($ticket, $this->timeTrackingService->pause($ticket, $request->user()), 'Timer paused.');
    }

    public function resume(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTimerAccess($request, $ticket);

        return $this->timerResponse($ticket, $this->timeTrackingService->resume($ticket, $request->user()), 'Timer resumed.');
    }

    public function stop(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTimerAccess($request, $ticket);

        return $this->timerResponse($ticket, $this->timeTrackingService->stop($ticket, $request->user()), 'Timer stopped.');
    }

    private function authorizeTimerAccess(Request $request, Ticket $ticket): void
    {
        abort_unless($request->user()->can('view tickets'), Response::HTTP_FORBIDDEN);
        abort_unless($request->user()->isKielUser(), Response::HTTP_FORBIDDEN);
    }

    private function timerResponse(Ticket $ticket, TimeLog $timeLog, string $message): JsonResponse
    {
        $timeLog->refresh();

        return response()->json([
            'message' => $message,
            'timer' => [
                'id' => $timeLog->id,
                'status' => $timeLog->status,
                'started_at' => $timeLog->started_at?->toISOString(),
                'paused_at' => $timeLog->paused_at?->toISOString(),
                'resumed_at' => $timeLog->resumed_at?->toISOString(),
                'ended_at' => $timeLog->ended_at?->toISOString(),
                'duration_seconds' => $timeLog->duration_seconds,
                'current_duration_seconds' => $timeLog->currentDurationSeconds(),
            ],
            'ticket' => [
                'id' => $ticket->id,
                'cumulative_duration_seconds' => $this->timeTrackingService->cumulativeDurationForTicket($ticket),
            ],
        ]);
    }
}
