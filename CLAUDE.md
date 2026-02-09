# App

PHP 8.5 MVC framework — custom routing, Bootstrap 5 dark theme, MySQL 8.0, Docker/Apache. Multi-user with many-to-many RBAC, REST API with bearer token auth, server-side form builder, flash messages, validation.

## Commands

```bash
docker-compose up -d              # Start app at http://localhost:8088
docker-compose down               # Stop app
docker-compose down -v            # Stop app + delete MySQL data
docker-compose exec app php scripts/generate-password.php "new-password"  # Reset password (run inside container)
docker-compose exec app vendor/bin/phpunit --testdox  # Run test suite
```

Adminer (DB admin): http://localhost:8081 (server: mysql, user: app, password: app_secret, database: app)

## Codebase Reference

Detailed codebase map is in `.claude/codebase-map.md`. **Search it with Grep before exploring the codebase.** Do not read the entire file. Examples:
- Find a controller: `Grep pattern="CONTROLLER.*Home" path=".claude/codebase-map.md"`
- Find a model: `Grep pattern="MODEL.*User" path=".claude/codebase-map.md"`
- Find a route: `Grep pattern="ROUTE.*login" path=".claude/codebase-map.md"`
- Find a view: `Grep pattern="VIEW.*home" path=".claude/codebase-map.md"`
- Find a partial: `Grep pattern="PARTIAL.*navbar" path=".claude/codebase-map.md"`
- Find a pattern: `Grep pattern="PATTERN.*Auth" path=".claude/codebase-map.md"`
- Find config: `Grep pattern="CONFIG.*app" path=".claude/codebase-map.md"`
- Find storage: `Grep pattern="STORAGE" path=".claude/codebase-map.md"`
- Find database: `Grep pattern="DATABASE" path=".claude/codebase-map.md"`

After making structural changes (new files, models, routes, views), update `.claude/codebase-map.md`.

## Essential Patterns

- **Namespace**: `App\` maps to `src/` via Composer PSR-4 autoloading.
- **Request flow**: `public/index.php` → session init → remember-me check → `require routes.php` → `Router::dispatch()` → middleware pipeline → Controller action → `view()` or `json()` response.
- **Routing**: Static fluent API — `Router::get('/path', [Controller::class, 'action'])->name('name')->middleware('auth')`. Groups with `Router::group(['prefix' => '/admin', 'middleware' => ['auth', 'role:admin']], fn() => ...)`. Method spoofing via `_method` POST field for PUT/PATCH/DELETE.
- **Auth**: Multi-user, session-based with RBAC. `AuthMiddleware::setAuthenticated($user)` stores user_id, username, user_roles (array of slugs), user_name in session. `AuthMiddleware::requireAuth()` gates protected routes. `AuthMiddleware::requireRole('admin')` checks role membership. `AuthMiddleware::requirePermission('users.edit')` checks specific permission across all user roles.
- **RBAC**: Many-to-many roles via `user_roles` pivot table. Users can hold multiple roles. `requireRole(string ...$allowedRoles)` checks if user has **any** of the allowed roles (no level hierarchy). Permissions via `role_permissions` pivot. Admin role has all permissions implicitly. Middleware aliases: `role:admin`, `role:admin,editor`, `permission:users.edit`.
- **CSRF**: `CsrfMiddleware::field()` in forms (or auto-included by `FormBuilder::open()`), middleware alias `csrf` on routes. Synchronizer Token Pattern with `hash_equals()`.
- **Rate limiting**: IP-based progressive lockout on login (3 attempts → 30min, doubles each time, caps at 24hr). MySQL storage in `rate_limits` table.
- **Flash messages**: `Flash::set('success', 'message')` → survives one redirect → auto-rendered by `partials/flash-messages.php` as Bootstrap 5 dismissible alerts. Types: success, error, warning, info. Old input: `Flash::setOldInput($data)`, `Flash::old('field')`.
- **Validation**: `Validator::make($data, $rules)->validate()` → `fails()`, `errors()`, `validated()`. Rules: required, string, email, min:N, max:N, numeric, integer, confirmed, unique:table,column[,except_id], in:val1,val2. Errors stored in `$_SESSION['_validation_errors']`.
- **Form Builder**: `Form::open(['action' => '/path', 'method' => 'PUT'])` auto-includes CSRF + method spoofing. Field methods generate Bootstrap 5 markup with validation error states and old input. `Form::text()`, `email()`, `password()`, `textarea()`, `select()`, `checkbox()`, `radio()`, `submit()`.
- **Base Model**: Abstract `Model` class with static CRUD. Subclass sets `$table` and `$fillable`. Methods: `find()`, `findBy()`, `where()`, `all()`, `create()`, `update()`, `delete()`, `query()`.
- **API**: REST API at `/api/v1/` with bearer token auth. `Authorization: Bearer app_xxx` header. API keys managed at `/profile/api-keys`. Rate limited at 60 req/min per key. JSON responses via `ApiController::success()` / `error()`.
- **Data persistence**: MySQL 8.0 for users, roles, permissions, tokens, rate limits, API keys. `Database::getConnection()` PDO singleton. Sessions on filesystem.
- **Frontend**: Bootstrap 5 dark theme with vanilla JS. Server-rendered views via PHP output buffering.

## Database Schema

Tables: `users`, `roles`, `permissions`, `role_permissions`, `user_roles`, `remember_tokens`, `rate_limits`, `api_keys`. Full schema in `database/init.sql`. Migrations in `database/migrations/`.

Default credentials: username `admin`, password `password`.

## Key Routes

| Route | Auth | Description |
|-------|------|-------------|
| `GET /` | public | Home page |
| `GET/POST /login` | no | Login form + handler |
| `GET /logout` | no | Logout |
| `GET /profile` | auth | User profile |
| `GET/POST /change-password` | auth | Change password |
| `GET/POST /profile/api-keys` | auth | API key management |
| `POST /profile/api-keys/{id}/revoke` | auth | Revoke API key |
| `GET/POST /admin/users` | admin | User list + create |
| `GET /admin/users/create` | admin | Create user form |
| `GET /admin/users/{id}/edit` | admin | Edit user form |
| `PUT /admin/users/{id}` | admin | Update user |
| `POST /admin/users/{id}/delete` | admin | Delete user |
| `GET/POST /admin/roles` | admin | Role list + create |
| `GET /admin/roles/create` | admin | Create role form |
| `GET /admin/roles/{id}/edit` | admin | Edit role form |
| `PUT /admin/roles/{id}` | admin | Update role |
| `POST /admin/roles/{id}/delete` | admin | Delete role |
| `GET /api/v1/user` | api_auth | Current user info (JSON) |
