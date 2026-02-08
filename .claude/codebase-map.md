# Codebase Map - App
# Search this file with Grep. Do not read the entire file.
# Format: [TAG] Name | path | details

## CONTROLLERS

[CONTROLLER] BaseController | src/Controllers/BaseController.php | abstract base | view(), json(), redirect(), isAuthenticated(), getSessionUser(), escape() | loads config/app.php
[CONTROLLER] AuthController | src/Controllers/AuthController.php | extends BaseController | showLogin(), login(), logout(), showChangePassword(), changePassword() | uses User, RememberToken, AuthMiddleware, CsrfMiddleware, RateLimitMiddleware
[CONTROLLER] HomeController | src/Controllers/HomeController.php | extends BaseController | index() | public (passes auth status to view) | renders home view

## CORE

[CORE] Database | src/Core/Database.php | PDO singleton | getConnection() | reads config/app.php database section | ERRMODE_EXCEPTION, FETCH_ASSOC, EMULATE_PREPARES=false | MySQL via pdo_mysql

## MODELS

[MODEL] User | src/Models/User.php | single-user auth | authenticate(username, password), updatePassword(current, new), getUsername() | MySQL users table | ARGON2ID hashing
[MODEL] RememberToken | src/Models/RememberToken.php | remember-me tokens | create(username), validate(rawToken), delete(rawToken), clearAll() | MySQL remember_tokens table | SHA256 hash, 90-day lifetime

## MIDDLEWARE

[MIDDLEWARE] AuthMiddleware | src/Middleware/AuthMiddleware.php | static class | check(), requireAuth(), setAuthenticated(), checkRememberToken(), setRememberToken(), clearRememberCookie(), deleteRememberToken(), logout() | session keys: authenticated, username, login_time
[MIDDLEWARE] CsrfMiddleware | src/Middleware/CsrfMiddleware.php | static class | token(), field(), validate(), verify(), regenerate(), clear() | Synchronizer Token Pattern | session key: _csrf_token | 32-byte random token
[MIDDLEWARE] RateLimitMiddleware | src/Middleware/RateLimitMiddleware.php | static class | check(ip), isLocked(ip), recordAttempt(ip), clear(ip) | MySQL rate_limits table | 3 attempts, 30min base lockout, progressive doubling, 24hr cap

## ROUTING

[ROUTING] Router | src/Routing/Router.php | simple URL routing | __construct(routes), addRoute(method, path, handler), dispatch(method, uri), pathToPattern(path), render404() | {param} placeholders → regex | array-based route matching

## ROUTES

[ROUTE] GET / | config/routes.php | HomeController::index | public | renders home page
[ROUTE] GET /login | config/routes.php | AuthController::showLogin | no auth | renders login form
[ROUTE] POST /login | config/routes.php | AuthController::login | no auth | processes login with CSRF + rate limiting
[ROUTE] GET /logout | config/routes.php | AuthController::logout | auth required | destroys session + tokens
[ROUTE] GET /change-password | config/routes.php | AuthController::showChangePassword | auth required | renders password change form
[ROUTE] POST /change-password | config/routes.php | AuthController::changePassword | auth required | updates password with CSRF

## VIEWS & TEMPLATES

[VIEW] layouts/main.php | src/Views/layouts/main.php | base HTML layout | vars: $title, $mainClass, $showNav, $content, $scripts | Bootstrap 5 dark theme, conditional navbar
[VIEW] auth/login.php | src/Views/auth/login.php | login form | vars: $error | fields: username, password, remember_me | includes CSRF field
[VIEW] auth/change-password.php | src/Views/auth/change-password.php | password change form | vars: $error, $success, $username | fields: current_password, new_password, confirm_password | min 8 chars
[VIEW] home/index.php | src/Views/home/index.php | home page | public | welcome page with conditional auth
[PARTIAL] partials/navbar.php | src/Views/partials/navbar.php | top navigation bar | user menu with change password and logout links

## CONFIG

[CONFIG] app.php | config/app.php | main app config | app_name=App, timezone(America/Chicago), debug, database(env-based: DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD), session(name=app_session, lifetime=7200), auth(username=admin), remember_me(lifetime=90days), csrf(enabled, token_length=32), rate_limit(max_attempts=3, lockout=30min, progressive, max=24hr)
[CONFIG] routes.php | config/routes.php | route definitions | array of [METHOD, path, [Controller::class, action]] | 6 routes total
[CONFIG] services.php | config/services.php | placeholder | empty file

## FRONTEND

[CSS] app.css | public/css/app.css | custom dark theme | bg:#1a1a1a | card, form, dropdown, navbar styles | mobile responsive
[CSS] bootstrap.min.css | public/css/bootstrap.min.css | Bootstrap 5 framework
[JS] bootstrap.bundle.min.js | public/js/bootstrap.bundle.min.js | Bootstrap 5 JS bundle

## ENTRY POINT

[ENTRY] index.php | public/index.php | front controller | loads autoloader → config → timezone → session → remember-me check → routes → Router::dispatch() | session config: file-based, 2hr lifetime, httponly, samesite=lax
[ENTRY] .htaccess | public/.htaccess | Apache rewrite rules | all requests → index.php | blocks: .env, .pwd, .json, .lock, .md | no directory listing

## DATABASE

[DATABASE] init.sql | database/init.sql | MySQL schema + seed | tables: users, remember_tokens, rate_limits | seeds admin user (password: 'password')
[DATABASE] users table | MySQL | id, username (UNIQUE), password_hash, created_at, updated_at
[DATABASE] remember_tokens table | MySQL | id, username, token_hash (indexed), expires_at, created_at
[DATABASE] rate_limits table | MySQL | id, ip_address (UNIQUE), attempts (JSON), lockout_until, lockout_count, updated_at

## STORAGE

[STORAGE] sessions/ | storage/sessions/ | PHP session files | one file per session ID | configured in public/index.php

## SCRIPTS

[SCRIPT] generate-password.php | scripts/generate-password.php | CLI tool | usage: docker-compose exec app php scripts/generate-password.php [new-password] | updates admin password in MySQL users table

## DOCKER

[DOCKER] Dockerfile | Dockerfile | php:8.5-apache | installs opcache + pdo_mysql | enables mod_rewrite | port 8088 | docroot /var/www/html/public | creates storage/sessions | www-data owns storage/
[DOCKER] docker-compose.yml | docker-compose.yml | services: app (port 8088), mysql (port 3306, healthcheck), adminer (port 8080) | volume: mysql_data | env: DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD | app depends_on mysql healthy

## PATTERNS

[PATTERN] Auth Flow | session-based | login → User::authenticate() → AuthMiddleware::setAuthenticated() → session_regenerate_id() → optional RememberToken::create() | logout → session_destroy() + token deletion
[PATTERN] CSRF | Synchronizer Token | CsrfMiddleware::field() in forms → CsrfMiddleware::verify() on POST → hash_equals() comparison → 403 on failure | regenerated after login
[PATTERN] Rate Limiting | IP-based progressive lockout | check() before login → recordAttempt() on failure → lockout doubles each violation (30m→60m→120m...) → clear() on success | capped at 24hr | MySQL storage
[PATTERN] Template Rendering | output buffering | BaseController::view() → extract($data) → ob_start() → include template → ob_get_clean() | layout wraps content
[PATTERN] Password Hashing | ARGON2ID | password_hash(PASSWORD_ARGON2ID) → stored in MySQL users table → password_verify() on login
[PATTERN] Database Persistence | MySQL 8.0 | PDO singleton via Database::getConnection() | users, remember_tokens, rate_limits tables | env-based config
[PATTERN] Security | defense in depth | CSRF tokens + rate limiting + session regeneration + output escaping + httponly cookies + .htaccess blocking + ARGON2ID hashing + timing-safe comparison

## SESSION KEYS

[CONST] Session Keys | authenticated(bool), username(string), login_time(int), _csrf_token(string), login_error(string), password_error(string), password_success(string), csrf_error(string), rate_limit_error(string)
[CONST] Cookie Names | app_session (session), remember_token (remember-me)
