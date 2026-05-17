# Implementation Tracker

## Completed: Asana-style Timeline view

### Summary
Implemented a dedicated Asana-style Timeline/Gantt planning view. DHTMLX Gantt was attempted first, but the npm registry returned `403 Forbidden`; Frappe Gantt was also unavailable through npm for the same reason, so the implementation uses the Frappe Gantt CDN fallback pattern for Laravel Blade, Alpine.js, and Tailwind.

### Timeline view
- Added a new authenticated `/timeline` page backed by a real `TimelineController` instead of the previous placeholder page.
- Added a Frappe Gantt chart area that renders scheduled tickets as horizontal bars using each ticket's `start_date` and `due_date`.
- Added smooth loading state, empty state, and success/error toast messaging.
- Added urgency-based bar colors, blocked styling, and overdue styling.
- Added read-only messaging for clients and editing-enabled messaging for Kiel users.

### Filters
- Added Timeline filters for:
  - Client
  - Software
  - Sprint
  - Assignee, including Unassigned
  - Urgency
  - Status
- Client users remain scoped to their own client data when viewing timeline data and filter options.

### Task metadata and drawer
- Timeline task payloads now include:
  - Ticket number and title
  - Assignee
  - Status
  - Urgency
  - Blocked indicator
  - Overdue indicator
  - Client
  - Software
  - Dependency label
  - Full ticket URL
- Added a right-side task drawer for timeline task review.
- Added drawer controls for setting or clearing a task dependency when the user can edit the timeline.

### Endpoints and service layer
- Added authenticated endpoint: `GET /timeline`.
- Added authenticated endpoint: `GET /timeline/data`.
- Added authenticated endpoint: `PATCH /timeline/tasks/{ticket}/dates`.
- Added authenticated endpoint: `PATCH /timeline/tasks/{ticket}/dependency`.
- Added `TimelineService` to:
  - Build Frappe Gantt-compatible JSON payloads.
  - Enforce Kiel/client visibility rules.
  - Restrict date and dependency edits to Kiel users.
  - Validate and update drag/resize date changes.
  - Validate and update task dependencies.
  - Prevent circular dependencies.
  - Log timeline date and dependency activity.

### Permission rules
- Kiel users can view, drag, resize, and update dependencies.
- Client users with `view timeline` can view their own client's timeline but cannot edit dates or dependencies.
- Cross-client timeline data remains hidden from client users.

### Verification performed
- Added `TimelineWorkflowTest` coverage for:
  - Timeline page rendering, filter bar, loading state, empty state, Frappe Gantt loading, and right-side drawer.
  - Frappe Gantt JSON task formatting.
  - Filter behavior across client, software, sprint, assignee, urgency, and status.
  - Kiel drag date changes and resize deadline changes through the date update endpoint.
  - Client edit rejection.
  - Date-order validation.
  - Dependency creation.
  - Circular dependency rejection.
- PHP syntax checks passed for the Timeline controller, service, routes, and feature test.
- `npm run build` passed successfully.
- `npm install dhtmlx-gantt --save-dev` and `npm install frappe-gantt --save-dev` were attempted, but the registry returned `403 Forbidden`; the Timeline view uses the Frappe Gantt CDN fallback.
- `php artisan test --filter=TimelineWorkflowTest` could not run because `vendor/autoload.php` is unavailable until Composer dependencies are installable.
- Browser-based drag/drop and resize checks could not be executed in this environment because Composer dependencies are unavailable and the Laravel app cannot boot.

### Next planned task
Implement right-side task drawer and unified ticket detail interactions.
