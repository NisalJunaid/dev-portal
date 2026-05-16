# Implementation Tracker

## Completed: Feature request workflow and recommendation system

### Summary
Implemented a dedicated feature request workflow for tickets classified with `type = feature`. Feature requests now have their own list, recommendation queue, detail view, status transition endpoints, planning edit controls, client isolation, Kiel global review, and activity logging. This workflow is separate from centralized intake, the dedicated bug workflow, and future sprint cycle execution.

### Feature statuses
- `feature_approved`
- `recommended`
- `next_sprint`
- `in_progress`
- `feature_blocked`
- `feature_completed`

### Database and model updates
- Extended `App\Models\Ticket` with feature workflow status constants.
- Added a `FEATURE_STATUSES` status list for feature-only queries and transition validation.
- Added feature helper methods for checking whether a ticket is a feature and whether a feature is completed.
- Feature completion sets both `completed_at` and `actual_completed_at`.

### Controllers and routes
- Added `FeatureController` for the dedicated feature workflow.
- Authenticated routes added for:
  - `GET /features`
  - `GET /features/recommended`
  - `GET /features/{ticket}`
  - `PATCH /features/{ticket}`
  - `POST /features/{ticket}/recommend`
  - `POST /features/{ticket}/approve-next-sprint`
  - `POST /features/{ticket}/defer`
  - `POST /features/{ticket}/complete`
- Feature routes only expose tickets where `type = feature` and status is one of the feature workflow statuses.
- Client users can view and recommend feature tickets that belong to their own client workspace.
- Kiel users can view features globally, review the recommendation queue, edit planning fields, move approved or recommended features to `next_sprint`, defer recommendations, and complete features.
- The old placeholder `/features` route was replaced with the dedicated feature workflow route.

### Feature workflow rules
- Client users can recommend only approved features for the next planning cycle.
- Recommended features move from `feature_approved` to `recommended`.
- Kiel users can move `feature_approved` or `recommended` features to `next_sprint`.
- Kiel users can defer a `recommended` feature back to `feature_approved` with an optional reason recorded in activity.
- Kiel users can reject a recommendation from the feature detail view using the existing formal ticket rejection endpoint.
- Kiel users can mark a feature completed, which moves it to `feature_completed` and records completion timestamps.
- Kiel users can edit:
  - Title
  - Description
  - Urgency
  - Assignee
  - Start date
  - Due date
  - Estimated hours

### Views and UI
- Added Blade views for:
  - `resources/views/features/index.blade.php`
  - `resources/views/features/recommended.blade.php`
  - `resources/views/features/show.blade.php`
- Feature list view includes ticket, client/software, status, urgency, assignment, schedule, and actions.
- Feature detail view includes description, comments, activity timeline, metadata, recommendation controls, Kiel workflow controls, and Kiel planning edit form.
- Recommended feature review view gives Kiel users focused actions to move recommendations to the next sprint or defer them.

### Feature filtering
- Feature list supports:
  - Search across ticket number, title, and description
  - Filter by client for Kiel users
  - Filter by software
  - Filter by urgency
  - Filter by status
  - Filter by assignee

### Activity logging
Activity entries are recorded for:
- `recommended`
- `moved to next sprint`
- `deferred`
- `completed`
- `feature details updated`

### Client isolation
- Non-Kiel users only query feature tickets where `client_id` matches their own user record.
- Direct feature detail and workflow actions enforce the same client boundary.
- Kiel users can query globally and use the client filter on the feature list.

### Verification performed
- PHP syntax checks passed for changed application, route, and feature workflow test PHP files.
- Added `FeatureWorkflowTest` coverage for:
  - Client recommendation flow.
  - Client isolation when recommending another client's feature.
  - Kiel approval of a recommended feature to `next_sprint`.
- `php artisan test --filter=FeatureWorkflowTest` was attempted but could not run because `vendor/autoload.php` is unavailable until Composer dependencies are installed.
- `composer install --no-interaction --prefer-dist` was attempted but could not complete because `composer.lock` is out of sync with `composer.json` and does not contain required packages including `spatie/laravel-permission` and `laravel/breeze`.
- Full browser-based feature workflow testing could not be executed in this environment because Composer dependencies are unavailable and the app cannot boot.

### Next planned task
Implement sprint cycles, start sprint, complete sprint, and sprint analytics.
