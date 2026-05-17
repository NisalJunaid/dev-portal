# Implementation Tracker

## Completed: Asana-style interactive ticket list view

### Summary
Implemented a reusable Asana-style ticket/task list view for the Tickets index with rich search, filtering, sorting, pagination, badges, blocked and overdue indicators, and no-reload inline editing for Kiel operational users.

### Reusable list UI
- Added `resources/views/tickets/partials/list-view.blade.php` as the reusable ticket list component used by `tickets.index`.
- The list shows:
  - Ticket number
  - Title
  - Type
  - Urgency
  - Status
  - Assigned to
  - Client
  - Software
  - Start date
  - Due date
  - Sprint cycle
  - Blocked indicator
  - Last updated
- Added smooth row hover styling for quick scanning.
- Added urgency and status badges with color-coded states.
- Added blocked/clear indicators per row.
- Added overdue due-date highlighting for incomplete tickets.

### Search, filters, sorting, and pagination
- Added server-side search across ticket number, title, client, software, and assignee.
- Added filters for type, urgency, status, assignee, client, software, blocked state, and page size.
- Added sortable table headers for all displayed columns, including related client/software/assignee data and sprint cycle.
- Pagination preserves the active query string so users do not lose filters or sorting while paging.

### Inline editing
- Added Alpine.js inline editing for Kiel users on:
  - Title
  - Urgency
  - Assigned user
  - Start date
  - Due date
  - Status, limited to statuses valid for the ticket type
- Inline edits use the Fetch API and do not trigger full-page reloads.
- Each editable field has a per-field saving indicator.
- Successful saves show a green success state.
- Failed saves show an error state and immediately revert the edited value to the last saved value.
- The row updates returned display data after a successful save, including badge values, blocked state, overdue state, assignee label, and last-updated timestamp.

### Endpoint and permissions
- Added authenticated route: `PATCH /tickets/{ticket}/inline-update`.
- Added `TicketController::inlineUpdate` to:
  - Check the user can view tickets and can access the ticket client scope.
  - Deny client users from inline-editing operational fields.
  - Validate the requested field name against the explicit inline-edit allowlist.
  - Validate each field value according to field-specific rules.
  - Prevent newly moving a ticket into a blocked status through inline status edits; users must still use the Block workflow with a reason.
  - Save the update in a database transaction.
  - Log ticket activity when values change.
  - Return a JSON payload with normalized values and presentation labels.

### Client restrictions
- Client users can view their scoped tickets but do not receive inline operational controls in the list.
- Client users attempting to call the inline update endpoint receive `403 Forbidden` and the ticket remains unchanged.
- Client contribution paths remain through existing comment/recommendation workflows rather than operational inline fields.

### Verification performed
- Added `TicketInlineUpdateTest` coverage for:
  - Kiel inline updates for title, urgency, assigned user, start date, due date, and status.
  - Activity logging for inline updates.
  - Invalid inline field names.
  - Invalid inline values.
  - Blocking-status protection through inline status edits.
  - Client users being forbidden from inline operational updates.
- PHP syntax checks passed for the updated ticket controller, routes, and new inline update test.
- `composer install --no-interaction --prefer-dist` was attempted but Composer reported that `composer.lock` is missing required packages currently listed in `composer.json` (`spatie/laravel-permission` and `laravel/breeze`), leaving `vendor/autoload.php` unavailable.
- `php artisan test --filter=TicketInlineUpdateTest` was attempted but could not run because `vendor/autoload.php` is unavailable until Composer dependencies are installable.
- Browser-based end-to-end inline editing could not be executed in this environment because Composer dependencies are unavailable and the Laravel app cannot boot.

### Next planned task
Implement Kanban board with drag-and-drop and reorder.
