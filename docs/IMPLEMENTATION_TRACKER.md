# Implementation Tracker

## Completed: Dedicated bug tracking workflow

### Summary
Implemented a dedicated bug fix workflow for tickets classified with `type = bug`. Bug fixes now have their own list and Kanban views, status transition endpoints, authorization checks, completion timestamp handling, and activity logging. The bug workflow is separate from centralized intake and future feature sprint workflows.

### Bug statuses
- `bug_pending`
- `bug_blocked`
- `bug_completed`

### Database and model updates
- Extended `App\Models\Ticket` with bug blocked and bug completed status constants.
- Added a `BUG_STATUSES` status list for bug-only queries and transition validation.
- Added helper methods for checking whether a ticket is a bug and whether a bug is completed.
- Bug completion sets both `completed_at` and `actual_completed_at`.

### Controllers and routes
- Added `BugController` for the dedicated bug workflow.
- Authenticated routes added for:
  - `GET /bugs`
  - `GET /bugs/{ticket}`
  - `POST /bugs/{ticket}/pending`
  - `POST /bugs/{ticket}/complete`
  - `POST /bugs/{ticket}/block`
- Bug routes only expose tickets where `type = bug` and status is one of the bug workflow statuses.
- Client users can view their own client bug tickets.
- Bug status updates require the explicit `update bugs` permission.
- Kiel roles receive bug update permission through the role seeder.

### Bug workflow rules
- Pending, blocked, and completed are the only allowed bug board statuses.
- Status transition requests validate:
  - The user can view bug tickets.
  - The ticket is a bug.
  - The ticket is visible to the current user.
  - The user has explicit bug update permission.
  - The requested transition is in the allowed bug transition map.
- Bug completion:
  - Sets `status = bug_completed`.
  - Sets `completed_at`.
  - Sets `actual_completed_at`.
  - Logs bug status activity.
- Reopening or moving a completed bug back to pending/blocked clears completion timestamps.

### Views and UI
- Added Blade views for:
  - `resources/views/bugs/index.blade.php`
  - `resources/views/bugs/show.blade.php`
- Bug Fixes view is separate from the feature sprint workflow and centralized intake.
- Bug list view includes ticket, client/software, status, urgency, assignment, and submission date.
- Bug Kanban view includes columns:
  - Pending
  - Blocked
  - Completed
- Bug detail view includes description, comments, activity timeline, metadata, and authorized status controls.

### Drag-and-drop behavior
- Bug Kanban uses SortableJS for drag-and-drop.
- Dragging a bug card between columns calls the matching secure Laravel endpoint.
- Drag updates are performed without a full-page reload.
- UI includes:
  - Smooth drag animation.
  - Saving indicator per target column.
  - Error toast when update fails.
  - Invalid/failed drops revert to the original column.
- Client users without explicit update permission do not get drag-enabled cards.

### Verification performed
- PHP syntax checks passed for changed application, route, and seeder PHP files.
- `npm run build` was attempted but could not complete because local Node dependencies are incomplete; Vite cannot resolve `tailwindcss` from `postcss.config.js`.
- `php artisan route:list --path=bugs` was attempted but could not run because `vendor/autoload.php` is unavailable until Composer dependencies are installed.
- Full browser-based bug lifecycle and drag/drop testing could not be executed in this environment because Composer dependencies are unavailable and the app cannot boot.

### Next planned task
Implement feature request workflow and recommendation system.
