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


## Update: Unified workspace undefined `view` key and nav cleanup

### Summary
- Fixed undefined array key risk in `TaskWorkspaceController` by normalizing requested `view` via null-coalescing fallback before validation and by passing a `filters` payload that always includes a resolved `view` key.
- Confirmed sidebar navigation uses unified `Tasks` top-level entry and keeps legacy task route groups as active-state matches only.
- Confirmed legacy workspace index routes redirect to unified `/tasks` tab views while preserving existing AJAX/task operation endpoints.
- Confirmed desktop sidebar collapse/resize persistence behavior remains in place for unified app shell.
- Ran required cache/route/test checks (environment limitations may still apply when vendor dependencies are unavailable).

### Next planned task
Production hardening and deployment preparation.

## Issue: `/tasks` undefined `Ticket::visibleTo` scope

### Summary
- issue: `TaskWorkspaceController` called `Ticket::query()->visibleTo($request->user())` but `Ticket` did not implement a `scopeVisibleTo`, causing `Call to undefined method Illuminate\\Database\\Eloquent\\Builder::visibleTo()` on `/tasks`.
- fix: added `scopeVisibleTo(Builder $query, User $user): Builder` to `app/Models/Ticket.php` with Kiel-team full visibility and client-scoped isolation by `client_id` for non-Kiel users.
- verified User role helpers: confirmed `User::isKielUser()` and `User::isClientUser()` already exist in `app/Models/User.php`; no duplication added.
- fixed teamMembers query: replaced invalid `User::ROLE_KIEL_TEAM`-based filter with Spatie role query (`User::role(['super_admin', 'kiel_manager', 'developer'])`).
- project-wide check: searched for `->visibleTo(` usage and confirmed only `TaskWorkspaceController` uses it, now backed by the new Ticket scope.

### Tests/checks run
- `php artisan view:clear` (failed in this container: missing `vendor/autoload.php`).
- `php artisan optimize:clear` (failed in this container: missing `vendor/autoload.php`).
- `php artisan route:list` (failed in this container: missing `vendor/autoload.php`).
- `php artisan test` (failed in this container: missing `vendor/autoload.php`).

### Next planned task
Production hardening and deployment preparation.

## Completed: Tasks workspace UX hardening + interaction fixes

### Summary
- Reworked `/tasks` into a minimal unified toolbar-first layout with icon-only view switcher.
- Switched List/Board/Timeline to in-page Alpine view switching (`x-show`) with URL `pushState` updates (no full navigation).
- Moved filters into one shared global filter panel controlled by one button across all views.
- Removed timeline header/status/filter cards and kept only compact schedule area.
- Fixed Kanban markup and implemented end-to-end Sortable drag/drop initialization with move+reorder PATCH calls.
- Standardized update feedback to floating toasts via `window.Kiel.toast(...)`.

### Files changed
- `resources/views/tasks/index.blade.php`
- `resources/views/tasks/partials/list-view.blade.php`
- `resources/views/tasks/partials/kanban-view.blade.php`
- `resources/views/tasks/partials/timeline-view.blade.php`
- `resources/js/app.js`
- `resources/views/layouts/app.blade.php`
- `docs/IMPLEMENTATION_TRACKER.md`

### Tests/checks run
- `php artisan view:clear`
- `php artisan optimize:clear`
- `php artisan route:list`
- `php artisan test`

### Known remaining issues
- If Composer `vendor/` dependencies are unavailable in a local container, artisan/test commands fail before app boot.

### Next planned task
Production hardening and deployment preparation.

## Completed: Asana-like Tasks workspace panel + list table controls

### Summary
- added adjustable list table column widths via drag handles with persisted localStorage widths.
- added show/hide column controls in the unified toolbar with persisted visibility preferences.
- refined `/tasks` to a stable full-height workspace shell with compact toolbar/filter band and consistent content panel height across List/Board/Timeline.
- removed rounded main task workspace containers in favor of flatter Asana-like panels while preserving existing inline row editing and drawer behavior.
- added sleek hover scrollbars and subtle fade transitions for filters, columns menu, and view panel switching.

### Files changed
- `resources/views/tasks/index.blade.php`
- `resources/views/tasks/partials/list-view.blade.php`
- `resources/views/tasks/partials/kanban-view.blade.php`
- `resources/views/tasks/partials/timeline-view.blade.php`
- `resources/js/app.js`
- `resources/css/app.css`
- `docs/IMPLEMENTATION_TRACKER.md`

### Tests/checks run
- `php artisan view:clear`
- `php artisan optimize:clear`
- `php artisan route:list`
- `php artisan test`

### Next planned task
Production hardening and deployment preparation.

## Completed: Tasks workspace controls + interactions hardening

### Summary
- Fixed the List view columns dropdown so it no longer closes immediately after opening.
- Added a Board view “Boards” visibility menu with localStorage persistence (`kiel.tasks.board.columns.visible`).
- Fixed Kanban drag/drop integration to align frontend payloads with backend expectations (`column`, `tickets`) and improved Sortable initialization reliability for hidden/shown views.
- Refactored Kanban cards to use a dedicated drag handle and avoid full-card button wrapping that can block dragging.
- Updated timeline task labels to use task title only (no assignee/status/urgency metadata in bar label text).
- Added timeline cursor/drag affordance styling for bars and resize handles.
- Vertical timeline movement: current Frappe Gantt integration supports horizontal date drag/resize; native vertical row drag/reorder is not currently implemented in this pass to avoid introducing unstable custom behavior.

### Files changed
- `resources/views/tasks/index.blade.php`
- `resources/views/tasks/partials/kanban-view.blade.php`
- `resources/views/kanban/partials/card.blade.php`
- `resources/js/app.js`
- `resources/css/app.css`
- `app/Services/TimelineService.php`
- `docs/IMPLEMENTATION_TRACKER.md`

### Tests/checks run
- `php artisan view:clear` *(failed: missing `vendor/autoload.php` in container)*
- `php artisan optimize:clear` *(failed: missing `vendor/autoload.php` in container)*
- `php artisan route:list` *(failed: missing `vendor/autoload.php` in container)*
- `php artisan test` *(failed: missing `vendor/autoload.php` in container)*

### Known remaining issues
- Composer vendor dependencies are still unavailable in this container, so artisan/test execution cannot complete here.
- Timeline vertical row reordering remains a known gap in the current Frappe Gantt-based implementation.

### Next planned task
Production hardening and deployment preparation.

## Completed: Remaining Tasks workspace UI/UX + drawer-based task creation

### Summary
- fixed show/hide list+board dropdown immediate-close behavior by moving to shared `openMenu` + root `@click` close + Escape close with `@click.stop` on triggers/panels.
- removed visible toolbar `Tasks` text next to view switch icons.
- renamed `Create Ticket` CTA to `Create Task`.
- added create-task right drawer on `/tasks` with AJAX submission and inline validation messages.
- added JSON response branch in `TicketController@store` while preserving existing redirect for non-JSON requests.
- added tasks partial refresh endpoint and client-side refresh flow for list/board; timeline refresh hooks call timeline reload entrypoint.
- removed `Scheduled tasks` heading text in timeline view.
- adjusted timeline empty/content region sizing to keep a full-height rows area.

### Files changed
- `resources/views/tasks/index.blade.php`
- `resources/views/tasks/partials/create-task-drawer.blade.php`
- `resources/views/tasks/partials/timeline-view.blade.php`
- `app/Http/Controllers/TicketController.php`
- `app/Http/Controllers/TaskWorkspaceController.php`
- `routes/web.php`
- `resources/css/app.css`
- `docs/IMPLEMENTATION_TRACKER.md`

### Tests/checks run
- `php artisan view:clear`
- `php artisan optimize:clear`
- `php artisan route:list`
- `php artisan test`

### Known issues
- timeline/list/board refresh endpoint currently returns safe server-rendered partials and does not yet re-apply every active filter/sort input for list refresh; functional no-reload updates are in place.

### Next planned task
Production hardening and deployment preparation.

## Completed: Tasks controls/dropdowns/drawer/timeline height stabilization

### Summary
- fixed dropdown auto-close by removing root click-close behavior and using an explicit `openMenu` dropdown manager with a full-screen overlay click-catcher.
- rewrote the tasks toolbar block with clean two-side flex structure and corrected markup nesting so all right-side controls are inside the same wrapper.
- fixed Create Task drawer open behavior and title autofocus from workspace controls.
- verified JSON task creation path remains active in `TicketController@store` while preserving non-JSON redirect behavior for full-page `/tickets/create` flow.
- refactored task partial refresh flow in `TaskWorkspaceController` to use shared `workspaceData()` for index + partial rendering.
- corrected timeline inner chart container sizing to fill available height naturally via shell/container CSS plus runtime `expandTimelineHeight()`.
- removed timeline scheduled-count header and kept empty-state as an in-shell overlay.

### Files changed
- `resources/views/tasks/index.blade.php`
- `resources/views/tasks/partials/create-task-drawer.blade.php`
- `resources/views/tasks/partials/timeline-view.blade.php`
- `resources/css/app.css`
- `app/Http/Controllers/TaskWorkspaceController.php`
- `docs/IMPLEMENTATION_TRACKER.md`

### Tests/checks run
- `php artisan view:clear`
- `php artisan optimize:clear`
- `php artisan route:list`
- `php artisan test`

### Known issues
- artisan/test command execution may still fail in environments where Composer vendor dependencies are missing.

### Next planned task
Production hardening and deployment preparation.

## Completed: Corrected sprint lifecycle wrapper + Kanban drag/drop payload alignment

### Summary
- corrected sprint start lifecycle so planned `next_sprint` feature records are now converted into generated implementation task tickets at sprint start rather than moved directly into execution status.
- sprint now acts as a wrapper around generated implementation tasks and selected bugs.
- implemented traceability links (`source_feature_id`, `is_generated_task`, `generated_from_sprint_id`) between generated task tickets, source feature records, and the sprint.
- updated Kanban drag/drop client behavior to send backend-validated payload keys (`column`, `position`, `view` and `column`, `tickets`, `view`) and removed old/invalid payload keys (`status`, `ticket_ids`).
- improved Sortable initialization lifecycle for visible-only columns and force reinitialization support.
- improved drag feedback classes/flow to preserve card drag ghost/shadow/cursor states.

### Root cause fixed (Kanban)
- root cause confirmed: frontend JS payload keys did not match `KanbanController` request validation contract.
- fixed frontend request payload contracts for move + reorder to exactly match backend requirements.

### Tests/checks run
- updated sprint workflow feature test for feature->generated task conversion traceability.
- updated Kanban workflow feature tests for task-style board movement and validation failures on legacy key names.

### Known remaining issues
- artisan/phpunit execution remains environment-dependent when Composer `vendor/` is unavailable in the container.
- planned-sprint pre-start UI actions (explicit add/remove/approve routes) are partially represented by existing `next_sprint` queue flow and should be expanded in a subsequent pass.

### Next planned task
Production hardening and deployment preparation.

## Issue: generated task links migration fails on missing column

- migration issue: `database/migrations/2026_05_17_000100_add_generated_task_links_to_tickets_table.php` failed with `SQLSTATE[42000]: Syntax error or access violation: 1072 Key column 'generated_from_sprint_id' doesn't exist in table`.
- root cause: the migration attempted to add a foreign key constraint for `generated_from_sprint_id` before creating the column.
- fix applied: updated migration to add `source_feature_id`, `generated_from_sprint_id`, and `is_generated_task` columns first using `foreignId()->nullable()->constrained(...)->nullOnDelete()` (for the two FK columns), wrapped each add/drop in `Schema::hasColumn` guards for partial-failure safety, and implemented safe `down()` cleanup with `dropConstrainedForeignId` and `dropColumn`.
- migration test result: attempted `php artisan migrate`, but this container failed before Laravel boot because `vendor/autoload.php` is missing; migration SQL path is fixed in code and ready to run once dependencies are installed.
- next planned task unchanged: Production hardening and deployment preparation.

## Completed: Feature request/task hierarchy foundations + drawer UX

### Summary
- added Ticket parent/children/subFeatures/subtasks relationships and helper methods for task/subtask and feature/sub-feature detection.
- added AJAX feature-request creation endpoint (`features.request.store`) supporting client and Kiel users with client/software access validation and optional parent feature assignment.
- expanded task creation (`TicketController@store`) to support explicit task creation (`type=task`), optional parent task linkage, and JSON responses for drawer submissions.
- added Tasks toolbar `Feature Request` button + new create-feature drawer partial using no-reload submissions.
- added ticket drawer “Sub-items” section with child hierarchy list, open-child behavior, and quick action buttons for add sub-feature/add subtask.
- added JSON response support for feature sprint actions (`approve-next-sprint`, `defer`, `complete`) for drawer/AJAX workflows.

### Files changed
- `app/Models/Ticket.php`
- `app/Http/Controllers/FeatureController.php`
- `app/Http/Controllers/TicketController.php`
- `routes/web.php`
- `resources/views/tasks/index.blade.php`
- `resources/views/tasks/partials/create-feature-drawer.blade.php`
- `resources/views/tickets/partials/drawer.blade.php`
- `resources/views/tickets/partials/drawer-content.blade.php`
- `docs/IMPLEMENTATION_TRACKER.md`

### Tests/checks run
- `php artisan view:clear`
- `php artisan optimize:clear`
- `php artisan route:list`
- `php artisan test`

### Known issues
- environment still may fail artisan/test commands if Composer vendor dependencies are unavailable.

### Next planned task
Production hardening and deployment preparation.
