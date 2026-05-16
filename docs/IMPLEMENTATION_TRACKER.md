# Implementation Tracker

## Completed: Client and software/product management

### Summary
Implemented full client and software/product management on top of the authenticated Laravel Blade portal. Kiel users can now create and edit clients, disable clients, create software/products for active clients, and enable or disable products. Client users can access a scoped software catalog that only shows enabled products belonging to their own client.

### Database updates
- `clients` table now matches the management schema: `id`, `name`, nullable `description`, `status`, and timestamps.
- `softwares` table added with `id`, `client_id`, `name`, nullable `description`, `is_enabled` defaulting to `true`, and timestamps.

### Models and relationships
- `App\Models\Client`
  - Fillable `name`, `description`, and `status`.
  - Status constants for `active` and `inactive`.
  - `hasMany` relationship to `Software`.
  - `hasMany` relationship to `User`.
- `App\Models\Software`
  - Fillable `client_id`, `name`, `description`, and `is_enabled`.
  - Boolean cast for `is_enabled`.
  - `belongsTo` relationship to `Client`.

### Authorization and permissions
- Added `manage clients` and `manage software` permissions.
- Kiel roles (`super_admin`, `kiel_manager`, and `developer`) can manage clients and software/products.
- Client roles (`client_admin` and `client_user`) can view software/products but cannot create, edit, enable, or disable them.
- Client software listing is scoped to the authenticated user's own client and only includes enabled products.
- Added `SoftwarePolicy` and expanded `ClientPolicy` for create, update, disable, and toggle authorization.

### Controllers and routes
- `ClientController` handles client listing, create, store, show, edit, update, and disable flows.
- `SoftwareController` handles software listing, create, store, edit, update, and enable/disable toggle flows.
- Authenticated resource routes added for:
  - `GET /clients`
  - `GET /clients/create`
  - `POST /clients`
  - `GET /clients/{client}`
  - `GET /clients/{client}/edit`
  - `PUT/PATCH /clients/{client}`
  - `PATCH /clients/{client}/disable`
  - `GET /softwares`
  - `GET /softwares/create`
  - `POST /softwares`
  - `GET /softwares/{software}/edit`
  - `PUT/PATCH /softwares/{software}`
  - `PATCH /softwares/{software}/toggle`

### Views and UI
- Added smooth Blade views for:
  - `resources/views/clients/index.blade.php`
  - `resources/views/clients/create.blade.php`
  - `resources/views/clients/edit.blade.php`
  - `resources/views/clients/show.blade.php`
  - `resources/views/softwares/index.blade.php`
  - `resources/views/softwares/create.blade.php`
  - `resources/views/softwares/edit.blade.php`
- Added shared form partials for client and software inputs.
- Client and software pages include cards, tables, status badges, empty states, and Alpine-powered confirmation modals before disabling clients or toggling product availability.
- Updated sidebar navigation to point to the implemented software catalog routes.

### Seed data
- Role seeding now includes client/software management permissions.
- Demo software/products seeded for the Acme Health sample client, including one enabled product visible to client users and one disabled product hidden from client users.

### Verification performed
- PHP syntax check passed for application, route, database, and seeder PHP files.
- `composer install --no-interaction --no-progress` was attempted but could not install dependencies because `composer.lock` does not yet contain the previously configured Breeze and Spatie packages.
- `composer update --no-interaction --no-progress` was attempted but blocked by a Packagist HTTP 403 CONNECT tunnel error.
- Full Laravel CRUD end-to-end testing could not be executed in this environment because `vendor/autoload.php` is unavailable until Composer dependencies can be installed.

### Next planned task
Implement ticket intake, backlog, classification, rejection, comments, and activity timeline.
