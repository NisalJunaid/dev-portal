# Implementation Tracker

## Completed: Blocked/unblocked workflow

### Summary
Implemented the blocked/unblocked ticket workflow with explicit block records, mandatory reasons/notes, automatic timer pauses, client-visible transparency, and Kiel-only block history on the ticket detail page.

### Database and model updates
- Added `ticket_blocks` table with ticket, blocker, reason, blocked/unblocked timestamps, unblocker, unblock note, calculated duration, timestamps, and lookup indexes.
- Added `App\Models\TicketBlock` with casts, active scope, ticket/blocker/unblocker relationships, and live duration calculation for active blocks.
- Added `Ticket hasMany TicketBlock` and active block relationship.
- Added `User` relationships for tickets blocked and unblocked by that user.

### Service behavior
- Added `TicketBlockService` to own block/unblock rules and duration calculations.
- Blocking requires a non-empty reason and only Kiel users can block tickets.
- Blocking creates an active `ticket_blocks` record and changes ticket status:
  - bug tickets become `bug_blocked`
  - feature tickets become `feature_blocked`
- Blocking automatically pauses every running timer on the ticket through the existing time-tracking service.
- Unblocking requires a non-empty unblock note.
- Unblocking sets `unblocked_at`, records `unblocked_by`, stores `unblock_note`, and calculates `duration_seconds`.
- Unblocking returns tickets to:
  - `bug_pending` for bugs
  - `in_progress` for features in an in-progress sprint
  - `feature_approved` for non-sprint features
- Timers remain paused after unblock; users must manually resume.
- Manual ticket detail status edits can no longer newly move tickets into blocked statuses without the dedicated Block workflow.

### Controllers and routes
- Added `TicketBlockController` with JSON responses for AJAX block/unblock operations.
- Authenticated routes added for:
  - `POST /tickets/{ticket}/block`
  - `POST /tickets/{ticket}/unblock`
- Block/unblock routes are restricted to Kiel users.
- Existing bug block endpoint now requires a reason and delegates to the block workflow.

### Ticket detail UI
- Ticket detail now shows a prominent blocked banner whenever a ticket is blocked.
- Clients see the blocked status, latest block reason, and total blocked duration.
- Client users still do not see internal timer data or Kiel-only block history.
- Kiel users see a Blocked workflow panel with Block and Unblock buttons.
- Block and unblock actions use modals for mandatory reason/note entry.
- Block/unblock actions use `fetch` JSON requests and update the ticket detail page without a full page reload.
- Kiel users see full block history with blocker, reason, unblocker, unblock note, timestamps, and durations.

### Activity logging
- Logs ticket activity for:
  - `blocked`
  - `unblocked`
  - `status changed` during block/unblock transitions
- Running timers paused by blocking continue to log automatic timer pause activity.

### Verification performed
- Added `TicketBlockWorkflowTest` coverage for:
  - Blocking requiring a reason.
  - Blocking a ticket with a running timer and automatically pausing the timer.
  - Client visibility of blocked status, latest reason, and total blocked duration without timer data or full block history.
  - Unblocking requiring a note, setting duration, returning sprint features to `in_progress`, and keeping timers paused.
- Updated the existing time-tracking blocked timer test to provide the now-mandatory block reason.
- PHP syntax checks passed for the ticket block model, service, controller, migration, touched ticket/user/bug controllers and models, and workflow tests.
- `composer install --no-interaction --prefer-dist` was attempted but Composer reported that `composer.lock` is missing required packages currently listed in `composer.json` (`spatie/laravel-permission` and `laravel/breeze`).
- `php artisan test --filter=TicketBlockWorkflowTest` was attempted but could not run because `vendor/autoload.php` is unavailable until Composer dependencies are installable.
- Browser-based block/unblock modal testing could not be executed in this environment because Composer dependencies are unavailable and the app cannot boot.

### Next planned task
Implement Asana-style list view with inline editing and filters.
