<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketBlock;
use App\Services\TicketBlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TicketBlockController extends Controller
{
    public function __construct(private readonly TicketBlockService $ticketBlockService)
    {
    }

    public function block(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeBlockAccess($request, $ticket);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:10000'],
        ]);

        $block = $this->ticketBlockService->block($ticket, $request->user(), $validated['reason']);
        $ticket->refresh();

        return $this->blockResponse($ticket, $block, 'Ticket blocked.');
    }

    public function unblock(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeBlockAccess($request, $ticket);

        $validated = $request->validate([
            'unblock_note' => ['required', 'string', 'max:10000'],
        ]);

        $block = $this->ticketBlockService->unblock($ticket, $request->user(), $validated['unblock_note']);
        $ticket->refresh();

        return $this->blockResponse($ticket, $block, 'Ticket unblocked.');
    }

    private function authorizeBlockAccess(Request $request, Ticket $ticket): void
    {
        abort_unless($request->user()->can('view tickets'), Response::HTTP_FORBIDDEN);
        abort_unless($request->user()->isKielUser(), Response::HTTP_FORBIDDEN);
        abort_unless($ticket->type !== null, Response::HTTP_UNPROCESSABLE_ENTITY, 'Only classified tickets can be blocked.');
    }

    private function blockResponse(Ticket $ticket, TicketBlock $block, string $message): JsonResponse
    {
        $activeBlock = $this->ticketBlockService->activeBlockFor($ticket)->with('blocker')->first();

        return response()->json([
            'message' => $message,
            'ticket' => [
                'id' => $ticket->id,
                'status' => $ticket->status,
                'formatted_status' => $ticket->formattedStatus(),
                'is_blocked' => $ticket->isBlocked(),
                'total_blocked_duration_seconds' => $this->ticketBlockService->totalBlockedDurationForTicket($ticket),
            ],
            'block' => $this->serializeBlock($block),
            'active_block' => $activeBlock ? $this->serializeBlock($activeBlock) : null,
        ]);
    }

    private function serializeBlock(TicketBlock $block): array
    {
        return [
            'id' => $block->id,
            'reason' => $block->reason,
            'blocked_at' => $block->blocked_at?->toISOString(),
            'blocked_by' => $block->blocker?->name,
            'unblocked_at' => $block->unblocked_at?->toISOString(),
            'unblocked_by' => $block->unblocker?->name,
            'unblock_note' => $block->unblock_note,
            'duration_seconds' => $block->duration_seconds,
            'current_duration_seconds' => $block->currentDurationSeconds(),
        ];
    }
}
