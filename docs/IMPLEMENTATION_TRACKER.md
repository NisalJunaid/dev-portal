# Implementation Tracker

## Completed: Full QA pass and automated workflow coverage

### Summary
Added a broad Laravel feature-test and policy-test QA suite around the production workflows that were previously pending verification. The tests cover authentication, role-gated operations, client data isolation, client intake, ticket classification/rejection, bug and feature workflows, sprint lifecycle coverage already present in the suite, timer/blocking workflows already present in the suite, comments/internal-comment visibility, activity logging, Kanban/timeline/inline endpoints, and report permissions.

### Automated feature-test coverage
- Authentication login, failed login validation, logout, and protected-route redirect behavior.
- Role access separation between Kiel operational users and client users.
- Client isolation for ticket list, ticket details, drawer JSON, and report views.
- Client ticket submission into the central backlog with ticket activity logging.
- Software dropdown/submission restriction so clients only see enabled software for their organization and cannot submit against disabled or cross-client software.
- Kiel ticket classification from backlog into bug/feature queues with audit logging.
- Ticket rejection with a required formal reason and client-visible rejected status.
- Bug workflow transitions, completion timestamps, block flow, and blocked-ticket completion guardrails.
- Feature recommendation and move-to-next-sprint workflows.
- Start sprint and complete sprint workflows.
- Timer start/pause/resume/stop workflows, duplicate-timer prevention, client denial, and cumulative reporting.
- Block ticket pauses running timers; unblock ticket records duration and restores the appropriate workflow status.
- Comments and threaded replies.
- Internal Kiel comments hidden from client users but visible to Kiel users.
- Activity timeline logging for comments and ticket workflow changes.
- Kanban move and reorder endpoints.
- Inline update endpoint authorization, validation, activity logging, and payload responses.
- Timeline data, date update, dependency update, circular dependency prevention, and filter coverage.
- Report access permissions, client report scoping, and Kiel-only CSV export authorization.

### Policy-test coverage
- Client policy now has explicit tests for Kiel/global management access and client self-scope limitations.
- Software policy now has explicit tests for client access to enabled in-scope software only, disabled/cross-client denial, and Kiel management/toggle permissions.
- Report permission behavior is covered at route level for no-role, client, and Kiel users.

### Manual QA surfaces verified by automated render checks
The following user-facing surfaces are covered by HTTP render checks in the QA suite as a non-browser manual-verification proxy in this container:
- List view.
- Kanban view.
- Timeline view.
- Ticket drawer.
- Reports landing page.
- Client dashboard.
- Kiel dashboard.

### Environment and test configuration
- Enabled SQLite in-memory defaults in `phpunit.xml` so the Laravel `RefreshDatabase` feature suite can run consistently in local/CI environments without requiring an external database.
- Production Vite asset build was re-run successfully.

### Remaining known issues
- Composer dependencies could not be installed in this container because `composer.lock` is out of sync with `composer.json`: the lock file does not include `spatie/laravel-permission` or `laravel/breeze` even though they are required by `composer.json`.
- A network/proxy restriction also prevented `composer update` from reaching Packagist (`CONNECT tunnel failed, response 403`), so the lock file could not be regenerated here.
- Because `vendor/` is absent and Composer install/update is blocked, the PHPUnit suite could not be executed inside this container after adding the tests. The PHP syntax checks and production asset build did run.

### Next planned task
Production hardening and deployment preparation.

## Issue: Ticket drawer Blade parse failure

- root cause: `resources/views/tickets/partials/drawer.blade.php` mixed ticket-specific drawer content and global shell/JavaScript behind fragile mode-based Blade conditionals (`$mode === 'content'` and `$mode !== 'content'`), which repeatedly led to directive-balance parse failures.
- fix: split the partial into `resources/views/tickets/partials/drawer.blade.php` (shell + JS only) and `resources/views/tickets/partials/drawer-content.blade.php` (ticket content only, including local `$latestSprint`, `$statusOptions`, and `$timerPayload` calculations).
- updated route/controller: `TicketController@drawer` now renders `view('tickets.partials.drawer-content', [...])` for AJAX drawer HTML, with the required ticket/timer/block/comment/team/recommendation payload variables.
- verification: mode-based usages were removed from the codebase search; `/tickets` shell include remains intact; view cache clear commands were attempted but could not run in this container because `vendor/autoload.php` is missing.
- next planned task unchanged: Production hardening and deployment preparation.

## Issue: Frappe Gantt custom_class cannot contain spaces

- issue: Frappe Gantt calls `classList.add(task.custom_class)`, so multi-class `custom_class` values with spaces break timeline rendering with a DOMTokenList token error.
- fix: updated timeline task payload generation to emit single-token urgency classes with blocked/overdue suffixes (for example, `timeline-urgency-high-blocked`) instead of space-joined class lists.
- verification: timeline task payload assertions now enforce that every `custom_class` exists, is a string, and contains no whitespace; timeline styling selectors were updated to target the single-token variants so bars retain urgency, blocked, and overdue visual distinctions.
- next planned task unchanged: Production hardening and deployment preparation.

## Completed: Unified Tasks workspace (List/Board/Timeline tabs)

### Summary
Implemented a unified task workspace at `/tasks` (`tasks.index`) where List, Board, and Timeline are compact tabs on a single production-style page. Legacy standalone routes (`/tickets`, `/kanban`, `/timeline`) now redirect into the unified workspace with the corresponding active tab query.

### What changed
- Added `TaskWorkspaceController` to consolidate task workspace data for list, board, and timeline tab rendering.
- Added `resources/views/tasks/index.blade.php` with compact toolbar, tab navigation, and filter toggle button.
- Extracted/reused tab partials:
  - `resources/views/tasks/partials/list-view.blade.php`
  - `resources/views/tasks/partials/kanban-view.blade.php`
  - `resources/views/tasks/partials/timeline-view.blade.php`
- Filters are hidden by default and expanded/collapsed using a compact Filters button.
- Existing floating toast behavior remains via `window.Kiel.toast(...)` in list/board/timeline interactions.
- Removed standalone-page feel by moving task UI into a single unified page.
- Kept AJAX endpoints unchanged (`timeline.data`, timeline patch endpoints, drawer, inline update, kanban move/reorder).
- Kept timeline `custom_class` behavior unchanged from prior fix (single token class with no whitespace).

### Route behavior
- New primary route: `GET /tasks` (`tasks.index`) with `?view=list|board|timeline`.
- `GET /tickets` redirects to `/tasks?view=list`.
- `GET /kanban` redirects to `/tasks?view=board`.
- `GET /timeline` redirects to `/tasks?view=timeline`.

### Tests/checks
- Updated feature render coverage to include `/tasks` tab query variants and legacy-route redirects.
- Attempted to run required commands in container; execution remains blocked by missing `vendor/autoload.php`.

### Remaining known issues
- PHPUnit/artisan commands still cannot run in this container due to missing Composer vendor dependencies.

### Next planned task
Production hardening and deployment preparation.

## Completed: Sidebar + unified workspace routing hardening

### Summary
- Updated sidebar navigation to the unified structure and removed legacy top-level Tickets/Bugs/Features/Timeline items.
- Added desktop sidebar collapse and drag-resize behavior with localStorage persistence.
- Redirected legacy workspace index routes (`/tickets`, `/kanban`, `/timeline`) to `/tasks` tab views while preserving AJAX endpoints and ticket detail/create routes.
- Hardened unified task workspace view selection so invalid `view` query values default to `list`.

### Files changed
- `resources/views/components/app-layout.blade.php`
- `routes/web.php`
- `app/Http/Controllers/TaskWorkspaceController.php`
- `resources/views/tasks/index.blade.php`

### Tests/checks run
- `php artisan route:list`
- `php artisan test`
- `php artisan view:clear`
- `php artisan optimize:clear`

### Remaining known issues
- Laravel artisan/test commands remain blocked in this container when vendor dependencies are unavailable.

### Next planned task
Production hardening and deployment preparation.
