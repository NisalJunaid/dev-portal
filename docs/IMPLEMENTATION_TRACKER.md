# Implementation Tracker

## Completed: Centralized ticket intake

### Summary
Implemented a centralized ticket intake workflow on top of the authenticated Laravel Blade portal. Client users can submit tickets against enabled software in their own client workspace, while Kiel users can review a global backlog, classify intake as bug or feature work, reject requests with formal reasons, assign ownership, edit ticket metadata, comment internally or externally, and review an activity timeline.

### Database updates
- `tickets` table added with client, software, submitter, nullable assignee, unique `ticket_no`, title, description, urgency enum, nullable type enum, status, rejection reason, submission/classification/completion timestamps, planning dates, estimates, priority/timeline ordering, parent/dependency ticket links, and timestamps.
- `ticket_comments` table added with ticket, user, nullable parent comment, comment body, `is_internal` defaulting to `false`, and timestamps.
- `ticket_activities` table added with ticket, nullable user, action, old/new values, description, and timestamps.

### Models and relationships
- `App\Models\Ticket`
  - Constants for backlog, bug pending, feature approved, and rejected statuses.
  - Constants for bug/feature types and critical/high/medium/low urgency values.
  - Relationships to client, software, submitter, assignee, parent ticket, dependency ticket, comments, and activities.
  - Date/timestamp and decimal casts for timeline and estimate fields.
- `App\Models\TicketComment`
  - Relationships to ticket, user, parent comment, and threaded replies.
  - Boolean cast for `is_internal`.
- `App\Models\TicketActivity`
  - Relationships to ticket and nullable user.
- Existing `Client`, `Software`, and `User` models now expose ticket/comment relationships.

### Services
- `TicketNumberService` generates unique ticket numbers using the required `KIEL-000001` format.
- `TicketActivityService` centralizes activity log creation and serializes old/new values consistently.

### Controllers and routes
- Added `TicketController` for:
  - Client/Kiel ticket listing.
  - Client ticket submission.
  - Ticket details.
  - Kiel global backlog.
  - Classification as bug or feature.
  - Rejection with mandatory formal reason.
  - Assignment to Kiel team members.
  - Metadata updates for urgency, dates, assignee, status, estimate, and software.
  - Threaded comments with optional Kiel-only internal visibility.
- Authenticated routes added for:
  - `GET /tickets`
  - `GET /tickets/create`
  - `POST /tickets`
  - `GET /tickets/{ticket}`
  - `PATCH /tickets/{ticket}`
  - `GET /tickets/backlog`
  - `PATCH /tickets/{ticket}/classify`
  - `PATCH /tickets/{ticket}/reject`
  - `PATCH /tickets/{ticket}/assign`
  - `POST /tickets/{ticket}/comments`

### Intake and classification rules
- Client software dropdowns only include enabled software scoped to the submitting client's workspace.
- All newly submitted tickets land in `status = backlog` and `type = null`.
- Bug classification sets `type = bug` and `status = bug_pending`.
- Feature classification sets `type = feature` and `status = feature_approved`.
- Rejection sets `status = rejected`, requires `rejection_reason`, and displays the formal reason on the ticket detail page for clients.

### Comments and activity timeline
- Comments support threaded replies through `parent_id`.
- Kiel users can mark comments internal.
- Client users cannot see internal comments or internal replies.
- Activity timeline logs:
  - Created.
  - Classified.
  - Rejected.
  - Assigned.
  - Urgency changed.
  - Status changed.
  - Dates changed.
  - Comment added.
  - Software changed as part of metadata edits.

### Views and UI
- Added Blade views for:
  - `resources/views/tickets/index.blade.php`
  - `resources/views/tickets/create.blade.php`
  - `resources/views/tickets/show.blade.php`
  - `resources/views/tickets/backlog.blade.php`
  - `resources/views/tickets/partials/comments.blade.php`
  - `resources/views/tickets/partials/activity-timeline.blade.php`
- Ticket detail uses a right-side Asana-style detail layout with:
  - Status badge.
  - Urgency badge.
  - Metadata panel.
  - Kiel action controls.
  - Comments section.
  - Visual activity timeline.
- Dashboard open ticket count now reflects scoped ticket totals.
- Sidebar route handling now points consistently at the implemented software and ticket routes.

### Verification performed
- PHP syntax check passed for application, route, database, and seeder PHP files.
- `composer install --no-interaction --no-progress` was attempted but could not install dependencies because `composer.lock` does not contain the configured Breeze and Spatie packages.
- `php artisan route:list` was attempted but could not run because `vendor/autoload.php` is unavailable until Composer dependencies can be installed.
- Full browser-based client submission, Kiel classification, rejection, comments, and activity log testing could not be executed in this environment because Laravel dependencies are unavailable.

### Next planned task
Implement bug tracking workflow and bug views.
