# Implementation Tracker

## Completed: Sprint cycle management

### Summary
Implemented sprint cycle management for feature delivery. Kiel users can now start a sprint for a selected client from that client's `next_sprint` feature queue, move those tickets into active delivery, complete the sprint, and review completed versus incomplete sprint analytics.

### Sprint statuses
- `planned`
- `in_progress`
- `completed`

### Database and model updates
- Added `sprints` table with client, optional software, sprint number, name, lifecycle status, start/end timestamps, duration, starter/ender user references, and timestamps.
- Added `sprint_items` table to connect tickets to sprints with optional ordering positions.
- Added `sprint_activities` table so sprint-level start and completion events have their own activity history.
- Added `App\Models\Sprint` with status constants, client/software/user relationships, item relationship, ticket many-to-many relationship through `sprint_items`, activity relationship, and display helpers.
- Added `App\Models\SprintItem` for sprint ticket membership.
- Added `App\Models\SprintActivity` for sprint lifecycle audit entries.
- Added `Client hasMany Sprints`.
- Added `Software hasMany Sprints`.
- Added `Ticket belongsToMany Sprints through sprint_items`.

### Controllers and routes
- Added `SprintController` for sprint list, start form, detail, start action, and complete action.
- Authenticated routes added for:
  - `GET /sprints`
  - `GET /sprints/start`
  - `GET /sprints/{sprint}`
  - `POST /sprints/start`
  - `POST /sprints/{sprint}/complete`
- Replaced the old placeholder `/sprints` route with the dedicated sprint workflow.
- Sprint visibility is scoped by client for non-Kiel users and global for Kiel users.
- Sprint start and completion actions are restricted to Kiel users.

### Start sprint behavior
- Kiel selects a client from the start sprint page.
- System finds all feature tickets for that client with status `next_sprint`.
- System blocks starting a second overlapping sprint for a client that already has an `in_progress` sprint.
- System creates a new sprint with a client-scoped incrementing `sprint_no`.
- Sprint name uses the format `Sprint Cycle {number} - {date}`.
- If all selected tickets belong to one software record, the sprint stores that `software_id`; otherwise the sprint software remains nullable for multi-software cycles.
- All selected next sprint tickets are copied into `sprint_items` with positions.
- All selected tickets move to `in_progress`.
- The sprint moves to `in_progress` and records `started_at` plus `started_by`.
- Activity is logged on the sprint and each ticket.

### Continuous planning
- Clients can continue recommending approved feature requests while a sprint is in progress.
- Recommended and future `next_sprint` items are not blocked by an active sprint; they remain outside the current sprint until Kiel starts the next cycle.

### Complete sprint behavior
- Completing a sprint records `ended_at`, calculates `duration_seconds`, sets status to `completed`, and records `ended_by`.
- Sprint completion logs a sprint activity summary with completed and incomplete item counts.
- Completed items are summarized from sprint tickets with status `feature_completed`.
- Incomplete items remain visible on the sprint detail page for Kiel review and are not automatically hidden or overwritten.

### Views and UI
- Added Blade views for:
  - `resources/views/sprints/index.blade.php`
  - `resources/views/sprints/start.blade.php`
  - `resources/views/sprints/show.blade.php`
- Sprint list supports client and status filtering.
- Sprint start page shows each client's ready `next_sprint` feature count and disables clients with no ready features or an active sprint.
- Sprint detail page shows:
  - Sprint name
  - Client
  - Software
  - Start time
  - End time
  - Duration
  - Completed tickets
  - Incomplete tickets for Kiel review
  - Sprint activity history
  - Ticket activity history for sprint items

### Verification performed
- Added `SprintWorkflowTest` coverage for:
  - Kiel starting a sprint from `next_sprint` feature tickets end-to-end.
  - Sprint items being created in order.
  - Ticket statuses moving to `in_progress`.
  - Future recommendations remaining outside the active sprint.
  - Sprint and ticket activity logging on start.
  - Kiel completing a sprint.
  - Completed analytics showing completed and incomplete tickets on the sprint detail page.
- PHP syntax checks passed for sprint models, controller, migrations, routes, and sprint workflow tests.
- `php artisan test --filter=SprintWorkflowTest` was attempted but could not run because `vendor/autoload.php` is unavailable until Composer dependencies are installed.
- Full browser-based sprint workflow testing could not be executed in this environment because Composer dependencies are unavailable and the app cannot boot.

### Next planned task
Implement time tracking start, pause, resume, stop, and reporting data.
