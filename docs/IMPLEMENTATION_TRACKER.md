# Implementation Tracker

## Completed: Backend-only Kiel time tracking

### Summary
Implemented backend-backed Kiel time tracking for tickets with an internal timer panel on the ticket detail page. Kiel team members can now start, pause, resume, and stop independent timer sessions on a ticket without full page reloads, while client users remain unable to access timer data.

### Time log lifecycle
- `running`
- `paused`
- `completed`

### Database and model updates
- Added `time_logs` table with ticket, user, client, software, start/pause/resume/end timestamps, accumulated duration, lifecycle status, timestamps, and reporting indexes.
- Added `App\Models\TimeLog` with status constants, casts, active/completed scopes, ticket/user/client/software relationships, and live current-duration calculation.
- Added `Ticket hasMany TimeLog`.
- Added `User hasMany TimeLog`.
- Added `Client hasMany TimeLog`.
- Added `Software hasMany TimeLog`.

### Service behavior
- Added `TimeTrackingService` to own timer rules and duration calculations.
- Starting a timer creates a running time log tied to the ticket, user, client, and software.
- Pausing a timer adds elapsed running seconds to `duration_seconds`, clears active resume state, and marks the log paused.
- Resuming a timer records the latest resume timestamp and returns the log to running.
- Stopping a timer finalizes elapsed running time, records `ended_at`, and marks the log completed.
- Prevents a user from starting more than one active timer for the same ticket.
- Allows different Kiel team members to track separate sessions on the same ticket.
- Calculates cumulative ticket time across completed and active logs.
- Exposes a report query filterable by client, software, user, and started-at date range.
- Automatically pauses running timers when a ticket is moved into a blocked status.

### Controllers and routes
- Added `TimeTrackingController` with JSON responses for AJAX timer operations.
- Authenticated routes added for:
  - `POST /tickets/{ticket}/timer/start`
  - `POST /tickets/{ticket}/timer/pause`
  - `POST /tickets/{ticket}/timer/resume`
  - `POST /tickets/{ticket}/timer/stop`
- Timer routes are restricted to Kiel users.
- Client users receive forbidden responses for timer route access.

### Ticket detail UI
- Kiel users see a timer panel on `tickets.show`.
- Client users do not see the timer panel or internal timer data.
- Timer panel includes Start, Pause, Resume, and Stop actions.
- Timer panel uses Alpine.js for live running duration.
- Timer actions use `fetch` with JSON responses, CSRF headers, saving states, success messages, and error messages.
- Ticket cumulative time updates after timer actions without a full page reload.

### Activity logging
- Logs ticket activity for:
  - `timer started`
  - `timer paused`
  - `timer resumed`
  - `timer stopped`
- Automatic blocked-ticket timer pauses are logged as timer pause activity with an automatic-pause description.

### Verification performed
- Added `TimeTrackingWorkflowTest` coverage for:
  - Full start, pause, resume, and stop timer lifecycle.
  - Duration accumulation across paused and resumed segments.
  - Duplicate active timer prevention for the same user and ticket.
  - Separate team member sessions on the same ticket.
  - Cumulative ticket duration calculation.
  - Report query filtering by client, software, user, and date range.
  - Client users being unable to access timer routes or see the timer panel.
  - Blocking a bug automatically pausing running timers.
- PHP syntax checks passed for the time log model, time tracking service, time tracking controller, touched ticket and bug controllers, migration, routes, and time tracking workflow test.
- `composer install --no-interaction --prefer-dist` was attempted but Composer reported that `composer.lock` is missing required packages currently listed in `composer.json` (`spatie/laravel-permission` and `laravel/breeze`).
- `php artisan test --filter=TimeTrackingWorkflowTest` was attempted but could not run because `vendor/autoload.php` is unavailable until Composer dependencies are installable.
- Browser-based timer panel testing could not be executed in this environment because Composer dependencies are unavailable and the app cannot boot.

### Next planned task
Implement blocked/unblocked workflow with automatic timer pause and transparency.
