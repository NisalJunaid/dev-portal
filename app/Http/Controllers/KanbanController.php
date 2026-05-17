<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\KanbanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KanbanController extends Controller
{
    public function __construct(private readonly KanbanService $kanbanService)
    {
    }

    public function index(Request $request): \Illuminate\Http\RedirectResponse
    {
        abort_unless($request->user()->can('view tickets'), 403);

        return redirect()->route('tasks.index', array_merge($request->query(), ['view' => 'board']));
    }

    public function move(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'column' => ['required', 'string', 'max:64'],
            'position' => ['required', 'integer', 'min:0'],
            'view' => ['required', Rule::in([KanbanService::VIEW_ALL, KanbanService::VIEW_BUGS, KanbanService::VIEW_FEATURES, KanbanService::VIEW_SPRINT])],
        ]);

        return response()->json([
            'message' => 'Kanban ticket saved.',
            'ticket' => $this->kanbanService->moveTicket($ticket, $request->user(), $validated['column'], $validated['position'], $validated['view']),
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tickets' => ['required', 'array', 'min:1'],
            'tickets.*' => ['integer', 'distinct', Rule::exists('tickets', 'id')],
            'column' => ['required', 'string', 'max:64'],
            'view' => ['required', Rule::in([KanbanService::VIEW_ALL, KanbanService::VIEW_BUGS, KanbanService::VIEW_FEATURES, KanbanService::VIEW_SPRINT])],
        ]);

        return response()->json($this->kanbanService->reorder($request->user(), $validated['tickets'], $validated['column'], $validated['view']));
    }

    private function views(): array
    {
        return [
            KanbanService::VIEW_ALL => 'All tickets',
            KanbanService::VIEW_BUGS => 'Bugs',
            KanbanService::VIEW_FEATURES => 'Features',
            KanbanService::VIEW_SPRINT => 'Sprint tasks',
        ];
    }
}
