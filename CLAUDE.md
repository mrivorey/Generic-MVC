# App

PHP 8.5 MVC framework — custom routing, Bootstrap 5 dark theme, MySQL 8.0, Docker/Apache.

## Commands

```bash
docker-compose up -d              # Start app at http://localhost:8088
docker-compose down               # Stop app
docker-compose down -v            # Stop app + delete MySQL data
docker-compose exec app php scripts/generate-password.php "new-password"  # Reset password (run inside container)
```

Adminer (DB admin): http://localhost:8081 (server: mysql, user: app, password: app_secret, database: app)

No test suite, linter, or CI/CD configured. No external PHP dependencies.

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
- **Request flow**: `public/index.php` → session init → remember-me check → `Router::dispatch()` → Controller action → `view()` or `json()` response.
- **Auth**: Single hardcoded user (`config/app.php`). Session-based with optional remember-me cookie. `AuthMiddleware::requireAuth()` gates protected routes.
- **CSRF**: `CsrfMiddleware::field()` in forms, `CsrfMiddleware::verify()` on POST. Synchronizer Token Pattern with `hash_equals()`.
- **Rate limiting**: IP-based progressive lockout on login (3 attempts → 30min, doubles each time, caps at 24hr). MySQL storage in `rate_limits` table.
- **Data persistence**: MySQL 8.0 for users, tokens, rate limits. `Database::getConnection()` PDO singleton. Sessions on filesystem.
- **Frontend**: Bootstrap 5 dark theme with vanilla JS. Server-rendered views via PHP output buffering.
