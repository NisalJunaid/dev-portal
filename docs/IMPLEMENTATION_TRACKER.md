# Implementation Tracker

## Completed: Asana-style Kanban board

### Summary
Implemented a dedicated Asana-style Kanban board using SortableJS for drag-and-drop planning across all tickets, bugs, features, and sprint task views. The board supports status moves, same-column priority reordering, autosave feedback, invalid-transition rejection with visual revert behavior, toast messaging, and a right-side ticket details drawer.

### Kanban views
- Added a new `Kanban` navigation item and authenticated `/kanban` page.
- Added board tabs for:
  - All tickets
  - Bugs
  - Features
  - Sprint tasks
- Added board-specific columns:
  - All tickets: Backlog, Recommended, Next Sprint, In Progress, Blocked, Completed, Rejected
  - Bugs: Pending, Blocked, Completed
  - Features: Approved, Recommended, Next Sprint, In Progress, Blocked, Completed
  - Sprint tasks: Approved, Recommended, Next Sprint, In Progress, Blocked, Completed

### Cards and drawer
- Each Kanban card now shows:
  - Ticket number
  - Title
  - Urgency
  - Assignee
  - Due date
  - Client
  - Software
  - Blocked badge when applicable
- Clicking a card opens a right-side drawer with ticket details and a link to the full ticket page.

### Drag-and-drop behavior
- Added SortableJS-powered drag between columns.
- Added SortableJS-powered reorder within the same column.
- Added “Saving…” badges while move and reorder requests are in flight.
- Added success and error toasts.
- Invalid status transitions are rejected by the API and the client-side card is visually reverted to its original location.

### Endpoints and service layer
- Added authenticated endpoint: `PATCH /kanban/tickets/{ticket}/move`.
- Added authenticated endpoint: `PATCH /kanban/tickets/reorder`.
- Added `KanbanService` to:
  - Build board columns and visible ticket queries per view.
  - Validate board-compatible status transitions.
  - Enforce Kiel/client permission rules.
  - Update ticket status.
  - Update `priority_order`.
  - Log Kanban move/reorder activity.
  - Return normalized updated ticket JSON for UI refreshes.

### Permission rules
- Kiel users can move tickets to statuses allowed by the active board and ticket type.
- Client users can recommend eligible feature tickets by moving approved features to Recommended.
- Client users are blocked from moving bugs or internal workflow statuses.
- Client users remain scoped to tickets for their own client.

### Verification performed
- Added `KanbanWorkflowTest` coverage for:
  - Kanban page rendering for SortableJS columns and the right-side drawer.
  - Kiel user drag/status move.
  - Kiel user reorder autosave.
  - Invalid drag/status transition rejection.
  - Client user recommendation allowance.
  - Client user internal workflow rejection.
- PHP syntax checks passed for the new controller, service, views, route file, and test.
- `npm install sortablejs --save-dev` was attempted, but the registry returned `403 Forbidden`; the board uses the existing CDN SortableJS loading pattern already present in the application.
- `npm run build` passed successfully.
- `php artisan test --filter=KanbanWorkflowTest` could not run because `vendor/autoload.php` is unavailable until Composer dependencies are installable.
- Browser-based drag/drop and drawer checks could not be executed in this environment because Composer dependencies are unavailable and the Laravel app cannot boot.

### Next planned task
Implement timeline/Gantt view with deadlines and drag-resize planning.
