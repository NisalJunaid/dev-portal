# Implementation Tracker

## Completed: Dashboards and reporting/export

### Summary
Implemented role-aware Kiel and client dashboards plus a reporting center with filterable time tracking, ticket, sprint summary, and blocked time reports. CSV export routes are available to Kiel users, with Excel export buttons conditionally shown when `maatwebsite/excel` is installed.

### Dashboard
- Reworked `GET /dashboard` through `DashboardController` to render different dashboard content for Kiel users and client users.
- Kiel dashboard now shows total tickets, open bugs, open features, blocked tickets, active sprints, overdue tasks, and time tracked this week.
- Kiel dashboard includes tickets-by-client, tickets-by-software, and recent activity tables/lists.
- Client dashboard now shows my submitted tickets, approved features, recommended features, current sprint progress, blocked tickets requiring attention, and recently completed items.
- Client dashboard data is scoped to the authenticated user's organization.

### Reports
- Added `ReportController` for reports index, time tracking report, tickets report, sprint summary report, blocked time report, and exports.
- Added report routes:
  - `GET /reports`
  - `GET /reports/time`
  - `GET /reports/tickets`
  - `GET /reports/sprints`
  - `GET /reports/blocked`
  - `GET /reports/export/time`
  - `GET /reports/export/tickets`
  - `GET /reports/export/sprints`
  - `GET /reports/export/blocked`
- Added shared report filter panel supporting client, software, user, ticket type, status, date range, and sprint filters.
- Added summary cards, report tables, export buttons, and empty states for all report pages.

### Permissions and visibility
- All report pages require the existing `view reports` permission.
- Kiel users can export reports.
- Client users can view reports limited to their own organization only.
- Client report views hide internal timer owner/blocker details and do not show export controls.
- Client users are forbidden from report export endpoints.

### Export
- CSV export is implemented for all report types.
- Excel export controls are conditionally displayed when `maatwebsite/excel` is available.

### Verification performed
- Added feature coverage for report filtering, CSV export, client visibility restrictions, client export denial, and dashboard metric visibility.
- PHP syntax checks passed for application, route, and test PHP files.
- Full Laravel/browser execution could not run in this container because Composer dependencies are not installed (`vendor/autoload.php` is unavailable), and `composer install` could not proceed because the lock file is out of sync with `composer.json`.

### Next planned task
Implement notifications, polish UI states, and final interaction consistency.
