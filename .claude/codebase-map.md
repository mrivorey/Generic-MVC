# Codebase Map - App
# Search this file with Grep. Do not read the entire file.
# Format: [TAG] Name | path | details

## CONTROLLERS

[CONTROLLER] BaseController | src/Controllers/BaseController.php | abstract base | view(), json(), redirect(), redirectWithErrors(), validationErrors(), isAuthenticated(), getSessionUser(), escape() | loads config/app.php
[CONTROLLER] AuthController | src/Controllers/AuthController.php | extends BaseController | showLogin(), login(), logout() | uses User, RememberToken, AuthMiddleware, CsrfMiddleware, RateLimitMiddleware, Flash
[CONTROLLER] HomeController | src/Controllers/HomeController.php | extends BaseController | index() | public (passes auth status to view) | renders home view
[CONTROLLER] ProfileController | src/Controllers/ProfileController.php | extends BaseController | show(), showChangePassword(), changePassword(), apiKeys(), generateApiKey(), revokeApiKey(id) | uses User, RememberToken, ApiKey, Flash, Validator
[CONTROLLER] Admin\UserController | src/Controllers/Admin/UserController.php | extends BaseController | index(), create(), store(), edit(id), update(id), destroy(id) | CRUD for users | uses User, Role, Flash, Validator | syncRoles on create/update
[CONTROLLER] Admin\RoleController | src/Controllers/Admin/RoleController.php | extends BaseController | index(), create(), store(), edit(id), update(id), destroy(id) | CRUD for roles | uses Role, Permission, Flash, Validator | syncPermissions on create/update | prevents admin role deletion
[CONTROLLER] PasswordResetController | src/Controllers/PasswordResetController.php | extends BaseController | showForgotForm(), sendResetLink(), showResetForm(), resetPassword() | uses User, PasswordResetToken, RememberToken, Mailer, Flash, Validator | no user enumeration on forgot | single-use tokens | CSRF on POST
[CONTROLLER] Api\ApiController | src/Controllers/Api/ApiController.php | abstract API base | success(data, meta), error(message, code, status), apiUser() | extends BaseController | overrides redirect to throw
[CONTROLLER] Api\UserApiController | src/Controllers/Api/UserApiController.php | extends ApiController | me() | returns authenticated API user info with roles array

## CORE

[CORE] Database | src/Core/Database.php | PDO singleton | getConnection(), reset(), setConnection(PDO), transaction(callable), beginTransaction(), commit(), rollBack(), getTransactionDepth() | reads config/app.php database section | ERRMODE_EXCEPTION, FETCH_ASSOC, EMULATE_PREPARES=false | MySQL via pdo_mysql | nested transaction support via SAVEPOINTs ($transactionDepth tracking) | transaction() auto-commits on success, rolls back + rethrows on Throwable
[CORE] ExitTrap | src/Core/ExitTrap.php | static class | enableTestMode(), disableTestMode(), exit(code) | in production calls exit(), in test mode throws ExitException
[CORE] ExitException | src/Core/ExitException.php | extends RuntimeException | getExitCode() | thrown by ExitTrap in test mode
[CORE] Flash | src/Core/Flash.php | static class | set(type, message), get(type), all(), has(type), setOldInput(data), old(key, default), clearOldInput() | session keys: _flash, _old_input | types: success, error, warning, info
[CORE] Model | src/Core/Model.php | abstract base model | static methods: find(id), findBy(column, value), where(column, value), all(orderBy), create(data), update(id, data), delete(id), query(sql, bindings), paginate(perPage, conditions, orderBy) | $table, $fillable | mass-assignment protection | opt-in soft deletes ($softDeletes=true): delete() sets deleted_at, queries auto-filter, withTrashed()/onlyTrashed() flags (auto-reset), restore(id), forceDelete(id)
[CORE] Validator | src/Core/Validator.php | static factory | make(data, rules)->validate() | fails(), errors(), validated() | rules: required, string, email, min:N, max:N, numeric, integer, confirmed, unique:table,column[,except_id], in:val1,val2 | stores errors in _validation_errors session
[CORE] FileSystem | src/Core/FileSystem.php | static file I/O utility | write(path, data), read(path), append(path, data), delete(path), exists(path), setStorageRoot(path), reset() | all paths relative to storage root | path traversal protection via realpath+str_starts_with | null byte rejection | auto-mkdir on write/append | LOCK_EX on writes | lazy-loads root from config/app.php paths.storage
[CORE] Logger | src/Core/Logger.php | static logging utility | emergency(), alert(), critical(), error(), warning(), notice(), info(), debug(), channel(name)->LogChannel, writeLog(level, message, context, channel), setConfig(config), reset() | PSR-3 levels with weight filtering | writes to logs/{channel}.log or logs/{channel}-YYYY-MM-DD.log (daily rotation) via FileSystem::append | lazy-loads config from config/app.php logging section | per-channel min_level overrides | daily rotation with auto-cleanup of old files (max_files days)
[CORE] LogChannel | src/Core/LogChannel.php | named log channel object | emergency(), alert(), critical(), error(), warning(), notice(), info(), debug() | delegates to Logger::writeLog() | returned by Logger::channel()
[CORE] FormBuilder | src/Core/FormBuilder.php | static form helper | open(attributes), close(), text(), email(), password(), textarea(), number(), hidden(), select(), checkbox(), radio(), submit() | auto CSRF, method spoofing, Bootstrap 5 markup, validation error states, old input repopulation
[CORE] Mailer | src/Core/Mailer.php | static SMTP class | send(to, subject, body, options)->bool, sendTemplate(to, subject, template, data)->bool, setConfig(config), reset() | raw SMTP via stream_socket_client | TLS/SSL support, AUTH LOGIN | logs via Logger::channel('mail') | returns false on failure (never throws)
[CORE] ErrorHandler | src/Core/ErrorHandler.php | static class | register(config), handleException(Throwable), handleError(severity, message, file, line), handleShutdown(), reset(), isDebug() | global exception/error/shutdown handler | debug mode: dev page with stack trace, code snippet, request data | production mode: branded error pages (404, 403, 500, generic) | API requests: JSON error responses | logs via Logger::channel('error') | ValidationException: redirect with errors + old input | HttpException: custom status codes + headers
[CORE] CacheDriver | src/Core/CacheDriver.php | interface | get(key), set(key, value, ttl), has(key), delete(key), clear() | contract for cache backends
[CORE] FileCacheDriver | src/Core/FileCacheDriver.php | implements CacheDriver | file-based cache | constructor(?cacheDir) defaults to storage/cache | serialized values with expiry timestamp | LOCK_EX writes | key sanitization (special chars → underscores, long keys → md5) | garbage collection on first set() per request | .cache file extension
[CORE] Cache | src/Core/Cache.php | static facade | get(key, default), set(key, value, ttl), has(key), delete(key), clear(), remember(key, ttl, callback), setDriver(CacheDriver), reset() | delegates to CacheDriver | default TTL from config/app.php cache.ttl (3600) | lazy-initializes FileCacheDriver
[CORE] FileUpload | src/Core/FileUpload.php | static file upload handler | handle(fieldName, options)->?array, delete(path)->bool, setConfig(config), reset() | server-side MIME detection via finfo | validates MIME type and file size | generates unique hex filenames | writes via FileSystem | configurable allowed_types and max_size | lazy-loads config from config/app.php uploads section | delete restricted to uploads/ prefix
[CORE] PaginationResult | src/Core/PaginationResult.php | value object | items(), currentPage(), lastPage(), total(), perPage(), hasMorePages(), links(baseUrl) | Bootstrap 5 pagination HTML generation | smart ellipsis for many pages (<=7 show all, otherwise 1...current-1,current,current+1...last) | preserves existing query string params
[CORE] Paginator | src/Core/Paginator.php | static class | paginate(table, perPage, conditions, orderBy)->PaginationResult, fromQuery(countSql, selectSql, bindings, perPage)->PaginationResult, setCurrentPage(page), reset() | simple table pagination with WHERE conditions (equality or operator array) | fromQuery for complex joins | resolves page from $_GET['page'] or manual setCurrentPage

## EXCEPTIONS

[EXCEPTION] HttpException | src/Exceptions/HttpException.php | extends RuntimeException | constructor(statusCode, message, headers, previous) | getStatusCode(), getHeaders() | base HTTP error with status code
[EXCEPTION] NotFoundException | src/Exceptions/NotFoundException.php | extends HttpException | default 404, message "Page not found"
[EXCEPTION] AuthorizationException | src/Exceptions/AuthorizationException.php | extends HttpException | default 403, message "Access denied"
[EXCEPTION] ValidationException | src/Exceptions/ValidationException.php | extends RuntimeException | constructor(errors, redirectUrl, oldInput) | getErrors(), getRedirectUrl(), getOldInput() | carries validation errors for redirect

## MODELS

[MODEL] User | src/Models/User.php | extends Model | authenticate(username, password)->?array, updatePassword(userId, current, new), setPassword(userId, new), roles(userId)->array, hasRole(userId, slug)->bool, hasPermission(userId, slug)->bool, syncRoles(userId, roleIds) | MySQL users table | ARGON2ID hashing | many-to-many roles via user_roles pivot | $softDeletes = true
[MODEL] RememberToken | src/Models/RememberToken.php | extends Model | createToken(userId), validate(rawToken)->?array, deleteToken(rawToken), clearForUser(userId), clearAll() | MySQL remember_tokens table | SHA256 hash, 90-day lifetime | user_id based
[MODEL] Role | src/Models/Role.php | extends Model | findBySlug(slug), permissions(roleId), hasPermission(roleId, slug), users(roleId)->array, syncPermissions(roleId, permissionIds) | MySQL roles table | seeded: admin, editor, viewer
[MODEL] Permission | src/Models/Permission.php | extends Model | findBySlug(slug) | MySQL permissions table | seeded: users.view, users.create, users.edit, users.delete
[MODEL] PasswordResetToken | src/Models/PasswordResetToken.php | extends Model | createToken(userId)->rawToken, validate(rawToken)->?array, deleteToken(rawToken), clearForUser(userId), clearExpired() | MySQL password_reset_tokens table | SHA256 hash, 1-hour lifetime | UNIQUE user_id (one token per user) | ON DUPLICATE KEY UPDATE for replacement
[MODEL] ApiKey | src/Models/ApiKey.php | extends Model | generate(userId, name)->rawKey, validateKey(rawKey)->?array, forUser(userId), revoke(keyId, userId) | MySQL api_keys table | SHA256 hash, app_ prefix

## MIDDLEWARE

[MIDDLEWARE] AuthMiddleware | src/Middleware/AuthMiddleware.php | static class | check(), requireAuth(), requireRole(string ...$allowedRoles), requirePermission(slug), setAuthenticated(user array), user()->?array, checkRememberToken(), setRememberToken(userId), clearRememberCookie(), deleteRememberToken(), logout() | session keys: authenticated, user_id, username, user_roles(array), user_name, login_time
[MIDDLEWARE] CsrfMiddleware | src/Middleware/CsrfMiddleware.php | static class | token(), field(), validate(), verify(), regenerate(), clear() | Synchronizer Token Pattern | session key: _csrf_token | 32-byte random token
[MIDDLEWARE] RateLimitMiddleware | src/Middleware/RateLimitMiddleware.php | static class | check(ip), isLocked(ip), recordAttempt(ip), clear(ip) | MySQL rate_limits table | 3 attempts, 30min base lockout, progressive doubling, 24hr cap
[MIDDLEWARE] ApiAuthMiddleware | src/Middleware/ApiAuthMiddleware.php | static class | verify() | reads Authorization: Bearer app_xxx header | validates API key | stores user in $_REQUEST['_api_user'] | returns JSON 401
[MIDDLEWARE] ApiRateLimitMiddleware | src/Middleware/ApiRateLimitMiddleware.php | static class | check() | keyed by API key hash | 60 requests/minute | returns JSON 429 with Retry-After
[MIDDLEWARE] RequestLogMiddleware | src/Middleware/RequestLogMiddleware.php | static class | start(), log(), reset() | logs every request on shutdown via Logger::channel('requests') | captures: method, uri, status, duration_ms, ip, user_id | logs to storage/logs/requests.log
[MIDDLEWARE] SecurityHeadersMiddleware | src/Middleware/SecurityHeadersMiddleware.php | static class | apply(), setConfig(), resetConfig(), loadConfig() | sets X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy, optional CSP | configurable via security_headers key in config/app.php | respects enabled flag and headers_sent()
[MIDDLEWARE] CorsMiddleware | src/Middleware/CorsMiddleware.php | static class | handle(), setConfig(), resetConfig(), loadConfig() | checks $_SERVER['HTTP_ORIGIN'] against allowed_origins | sets Access-Control-Allow-Origin, Vary: Origin | OPTIONS preflight: sets Allow-Methods, Allow-Headers, Max-Age, responds 204 via ExitTrap | optional credentials support | configurable via cors key in config/app.php

## ROUTING

[ROUTING] Router | src/Routing/Router.php | static fluent API | get(), post(), put(), patch(), delete(), match(), resource(), group(), url(name), dispatch(), reset() | named routes, route groups with prefix/middleware, method spoofing via _method
[ROUTING] Route | src/Routing/Route.php | route object | name(), middleware(), where(param, pattern), matches(method, uri)->?params | param constraints, regex matching
[ROUTING] MiddlewarePipeline | src/Routing/MiddlewarePipeline.php | static class | register(alias, handler), run(middlewareList) | aliases: auth, cors, csrf, role, permission, api_auth, api_rate_limit | supports colon params (role:admin,editor)

## ROUTES

[ROUTE] GET / | config/routes.php | HomeController::index | public | name: home
[ROUTE] GET /login | config/routes.php | AuthController::showLogin | no auth | name: login
[ROUTE] POST /login | config/routes.php | AuthController::login | middleware: csrf | processes login with rate limiting
[ROUTE] GET /logout | config/routes.php | AuthController::logout | name: logout
[ROUTE] GET /forgot-password | config/routes.php | PasswordResetController::showForgotForm | public | name: password.forgot
[ROUTE] POST /forgot-password | config/routes.php | PasswordResetController::sendResetLink | middleware: csrf | no user enumeration
[ROUTE] GET /reset-password | config/routes.php | PasswordResetController::showResetForm | public | name: password.reset | validates token via query param
[ROUTE] POST /reset-password | config/routes.php | PasswordResetController::resetPassword | middleware: csrf | single-use token
[ROUTE] GET /profile | config/routes.php | ProfileController::show | middleware: auth | name: profile
[ROUTE] GET /change-password | config/routes.php | ProfileController::showChangePassword | middleware: auth | name: password.change
[ROUTE] POST /change-password | config/routes.php | ProfileController::changePassword | middleware: auth, csrf
[ROUTE] GET /profile/api-keys | config/routes.php | ProfileController::apiKeys | middleware: auth | name: profile.api-keys
[ROUTE] POST /profile/api-keys | config/routes.php | ProfileController::generateApiKey | middleware: auth, csrf | name: profile.api-keys.generate
[ROUTE] POST /profile/api-keys/{id}/revoke | config/routes.php | ProfileController::revokeApiKey | middleware: auth, csrf
[ROUTE] GET /admin/users | config/routes.php | Admin\UserController::index | middleware: auth, role:admin | name: admin.users.index
[ROUTE] GET /admin/users/create | config/routes.php | Admin\UserController::create | middleware: auth, role:admin
[ROUTE] POST /admin/users | config/routes.php | Admin\UserController::store | middleware: auth, role:admin, csrf
[ROUTE] GET /admin/users/{id}/edit | config/routes.php | Admin\UserController::edit | middleware: auth, role:admin
[ROUTE] PUT /admin/users/{id} | config/routes.php | Admin\UserController::update | middleware: auth, role:admin, csrf | method spoofed via _method
[ROUTE] POST /admin/users/{id}/delete | config/routes.php | Admin\UserController::destroy | middleware: auth, role:admin, csrf
[ROUTE] GET /admin/roles | config/routes.php | Admin\RoleController::index | middleware: auth, role:admin | name: admin.roles.index
[ROUTE] GET /admin/roles/create | config/routes.php | Admin\RoleController::create | middleware: auth, role:admin
[ROUTE] POST /admin/roles | config/routes.php | Admin\RoleController::store | middleware: auth, role:admin, csrf
[ROUTE] GET /admin/roles/{id}/edit | config/routes.php | Admin\RoleController::edit | middleware: auth, role:admin
[ROUTE] PUT /admin/roles/{id} | config/routes.php | Admin\RoleController::update | middleware: auth, role:admin, csrf | method spoofed via _method
[ROUTE] POST /admin/roles/{id}/delete | config/routes.php | Admin\RoleController::destroy | middleware: auth, role:admin, csrf
[ROUTE] OPTIONS /api/v1/{path} | config/routes.php | CORS preflight catch-all | middleware: cors | empty response
[ROUTE] GET /api/v1/user | config/routes.php | Api\UserApiController::me | middleware: cors, api_auth, api_rate_limit | name: api.user

## VIEWS & TEMPLATES

[VIEW] layouts/main.php | src/Views/layouts/main.php | base HTML layout | vars: $title, $mainClass, $showNav, $content, $scripts | Bootstrap 5 dark theme, conditional navbar, flash messages
[VIEW] auth/login.php | src/Views/auth/login.php | login form | uses FormBuilder | fields: username, password, remember_me | flash messages
[VIEW] auth/forgot-password.php | src/Views/auth/forgot-password.php | forgot password form | uses FormBuilder | fields: email | flash messages | "Back to Login" link
[VIEW] auth/reset-password.php | src/Views/auth/reset-password.php | reset password form | uses FormBuilder | fields: token(hidden), password, password_confirmation | "Back to Login" link
[VIEW] emails/password-reset.php | src/Views/emails/password-reset.php | HTML email template | vars: $resetUrl, $userName, $expiryMinutes | dark theme, reset button, expiry notice, ignore message
[VIEW] auth/change-password.php | src/Views/auth/change-password.php | password change form | uses FormBuilder | fields: current_password, new_password, new_password_confirmation
[VIEW] home/index.php | src/Views/home/index.php | home page | public | welcome page with conditional auth
[VIEW] profile/show.php | src/Views/profile/show.php | user profile | displays user info, roles (badges), dates | link to change password
[VIEW] profile/api-keys.php | src/Views/profile/api-keys.php | API key management | list keys, generate form, revoke buttons | shows new key once
[VIEW] admin/users/index.php | src/Views/admin/users/index.php | user list table | shows all users with role badges, status, actions | delete with confirmation
[VIEW] admin/users/create.php | src/Views/admin/users/create.php | create user form | uses FormBuilder | fields: username, email, name, password, role_ids[] checkboxes, is_active
[VIEW] admin/users/edit.php | src/Views/admin/users/edit.php | edit user form | uses FormBuilder | fields: username, email, name, password(optional), role_ids[] checkboxes, is_active | method spoofed PUT
[VIEW] admin/roles/index.php | src/Views/admin/roles/index.php | role list table | shows roles with user counts, edit/delete buttons | admin role protected
[VIEW] admin/roles/create.php | src/Views/admin/roles/create.php | create role form | uses FormBuilder | fields: name, slug, description, permissions[] checkboxes
[VIEW] admin/roles/edit.php | src/Views/admin/roles/edit.php | edit role form | uses FormBuilder | fields: name, slug, description, permissions[] checkboxes | method spoofed PUT
[PARTIAL] partials/navbar.php | src/Views/partials/navbar.php | top navigation bar | admin dropdown (conditional on user_roles containing admin), profile link, API keys link, change password, logout | Manage Users + Manage Roles links
[PARTIAL] partials/flash-messages.php | src/Views/partials/flash-messages.php | renders flash messages | Bootstrap 5 dismissible alerts | type mapping: success→success, error→danger, warning→warning, info→info
[VIEW] errors/dev.php | src/Views/errors/dev.php | detailed debug error page | standalone HTML (no layout) | Bootstrap 5 dark theme | exception class, message, file:line, code snippet, stack trace, request details (method, URI, GET/POST, headers, session) | sensitive POST params masked
[VIEW] errors/404.php | src/Views/errors/404.php | production 404 page | uses layouts/main.php | "Page Not Found" with Go Home link
[VIEW] errors/403.php | src/Views/errors/403.php | production 403 page | uses layouts/main.php | "Access Denied" with Go Home link
[VIEW] errors/500.php | src/Views/errors/500.php | production 500 page | uses layouts/main.php | "Server Error" with Go Home link
[VIEW] errors/generic.php | src/Views/errors/generic.php | fallback error page | uses layouts/main.php | receives $code | generic message with Go Home link

## CONFIG

[CONFIG] app.php | config/app.php | main app config | app_name=App, timezone(America/Chicago), debug, url(APP_URL), database(env-based: DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD), session(name=app_session, lifetime=7200), remember_me(lifetime=90days), csrf(enabled, token_length=32), logging(rotation=daily, max_files=14), mail(MAIL_HOST/PORT/USERNAME/PASSWORD/ENCRYPTION/FROM), password_reset(token_lifetime=3600), rate_limit(max_attempts=3, lockout=30min, progressive, max=24hr), security_headers(enabled, frame_options=DENY, csp), cors(allowed_origins, methods, headers, max_age, credentials), uploads(allowed_types, max_size=5MB), cache(driver=file, ttl=3600)
[CONFIG] routes.php | config/routes.php | route definitions | fluent static API | Router::get/post/put/delete with name(), middleware(), where() | groups with prefix/middleware
[CONFIG] services.php | config/services.php | placeholder | empty file

## FRONTEND

[CSS] app.css | public/css/app.css | custom dark theme | bg:#1a1a1a | card, form, dropdown, navbar styles | mobile responsive
[CSS] bootstrap.min.css | public/css/bootstrap.min.css | Bootstrap 5 framework
[JS] bootstrap.bundle.min.js | public/js/bootstrap.bundle.min.js | Bootstrap 5 JS bundle

## ENTRY POINT

[ENTRY] index.php | public/index.php | front controller | loads autoloader → config → timezone → ErrorHandler::register() → SecurityHeadersMiddleware::apply() → RequestLogMiddleware::start() → session → remember-me check → require routes.php → Router::dispatch() | session config: file-based, 2hr lifetime, httponly, samesite=lax
[ENTRY] .htaccess | public/.htaccess | Apache rewrite rules | all requests → index.php | blocks: .env, .pwd, .json, .lock, .md | no directory listing
[ENTRY] cli | cli | CLI entry point | loads autoloader → config → timezone → CommandRunner::run() | executable, shebang #!/usr/bin/env php

## COMMANDS

[COMMAND] Command | src/Commands/Command.php | abstract base | $name, $description, abstract execute(args)->int | helpers: output(STDOUT), error(STDERR), success(green ANSI), warning(yellow ANSI), confirm(y/n STDIN) | getName(), getDescription()
[COMMAND] CommandRunner | src/Commands/CommandRunner.php | discovery + dispatch | auto-discovers *Command.php in src/Commands/ | run(args)->int dispatches to command or showHelp() | getCommands() for testing | indexes by $name property
[COMMAND] MigrateCommand | src/Commands/MigrateCommand.php | name: migrate | creates migrations table on first run | scans database/migrations/*.sql | tracks executed in migrations table | runs pending SQL files via PDO::exec() | uses Database::getConnection()
[COMMAND] CacheClearCommand | src/Commands/CacheClearCommand.php | name: cache:clear | calls Cache::clear() | prints success message
[COMMAND] PasswordResetCommand | src/Commands/PasswordResetCommand.php | name: password:reset | usage: php cli password:reset <username> <password> | validates 2 args | looks up user by username | hashes with ARGON2ID | updates users table | uses Database::getConnection()

## DATABASE

[DATABASE] init.sql | database/init.sql | MySQL schema + seed | tables: roles, permissions, role_permissions, users (with deleted_at), user_roles, remember_tokens, rate_limits, api_keys, password_reset_tokens, migrations | seeds admin user (password: 'password'), 3 roles, 4 permissions, admin user_roles assignment | creates app_test DB with same schema
[DATABASE] migrations/002_roles_and_permissions.sql | database/migrations/002_roles_and_permissions.sql | adds RBAC | creates roles, permissions, role_permissions tables | ALTERs users (email, name, role_id, is_active, last_login_at) | ALTERs remember_tokens (user_id, drops username)
[DATABASE] migrations/003_api_keys.sql | database/migrations/003_api_keys.sql | creates api_keys table | user_id FK, name, key_hash (UNIQUE), last_used_at
[DATABASE] migrations/004_many_to_many_roles.sql | database/migrations/004_many_to_many_roles.sql | many-to-many roles | creates user_roles pivot, migrates users.role_id data, drops role_id from users, drops level from roles
[DATABASE] migrations/005_password_reset_tokens.sql | database/migrations/005_password_reset_tokens.sql | creates password_reset_tokens table | user_id UNIQUE FK, token_hash indexed, expires_at indexed
[DATABASE] migrations/006_add_soft_deletes_to_users.sql | database/migrations/006_add_soft_deletes_to_users.sql | adds deleted_at TIMESTAMP NULL to users table + index
[DATABASE] users table | MySQL | id, username (UNIQUE), email, name, is_active, last_login_at, password_hash, created_at, updated_at, deleted_at (nullable, indexed) | soft deletes enabled
[DATABASE] roles table | MySQL | id, name, slug (UNIQUE), description, created_at
[DATABASE] permissions table | MySQL | id, name, slug (UNIQUE), description, created_at
[DATABASE] role_permissions table | MySQL | role_id + permission_id (composite PK, FKs CASCADE)
[DATABASE] user_roles table | MySQL | user_id + role_id (composite PK, FKs CASCADE) | many-to-many pivot
[DATABASE] password_reset_tokens table | MySQL | id, user_id (UNIQUE, FK CASCADE), token_hash (indexed), expires_at, created_at | one active token per user
[DATABASE] remember_tokens table | MySQL | id, user_id (FK CASCADE), token_hash (indexed), expires_at, created_at
[DATABASE] rate_limits table | MySQL | id, ip_address (UNIQUE), attempts (JSON), lockout_until, lockout_count, updated_at
[DATABASE] api_keys table | MySQL | id, user_id (FK CASCADE), name, key_hash (UNIQUE, indexed), last_used_at, created_at
[DATABASE] migrations table | MySQL | id, migration (UNIQUE VARCHAR 255), executed_at (TIMESTAMP) | created by MigrateCommand on first run | tracks executed migration filenames

## STORAGE

[STORAGE] sessions/ | storage/sessions/ | PHP session files | one file per session ID | configured in public/index.php
[STORAGE] cache/ | storage/cache/ | application cache files | managed via FileSystem class
[STORAGE] logs/ | storage/logs/ | application log files | managed via FileSystem class

## SCRIPTS

[SCRIPT] generate-password.php | scripts/generate-password.php | CLI tool | usage: docker-compose exec app php scripts/generate-password.php [new-password] | updates admin password in MySQL users table

## DOCKER

[DOCKER] Dockerfile | Dockerfile | php:8.5-apache | installs git, unzip, Composer, pdo_mysql | enables mod_rewrite | port 8088 | docroot /var/www/html/public | creates storage/sessions | www-data owns storage/
[DOCKER] docker-compose.yml | docker-compose.yml | services: app (port 8088), mysql (port 3306, healthcheck), adminer (port 8080) | volume: mysql_data | env: DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD | app depends_on mysql healthy

## PATTERNS

[PATTERN] Auth Flow | multi-user session-based | login → User::authenticate() returns user array → AuthMiddleware::setAuthenticated(user) → session_regenerate_id() → optional RememberToken::createToken(userId) | logout → session_destroy() + token deletion
[PATTERN] RBAC | many-to-many roles | users ↔ roles via user_roles pivot | permissions via role_permissions pivot | AuthMiddleware::requireRole(...$allowedRoles) checks membership | AuthMiddleware::requirePermission(slug) checks union of all role permissions | admin bypass (admin role has all permissions implicitly)
[PATTERN] CSRF | Synchronizer Token | CsrfMiddleware::field() in forms or FormBuilder auto-includes | CsrfMiddleware::verify() on POST → hash_equals() comparison → 403 on failure | regenerated after login
[PATTERN] Rate Limiting | IP-based progressive lockout | check() before login → recordAttempt() on failure → lockout doubles each violation (30m→60m→120m...) → clear() on success | capped at 24hr | MySQL storage
[PATTERN] API Auth | Bearer token | Authorization: Bearer app_xxx header → ApiAuthMiddleware::verify() → ApiKey::validateKey() → user data in $_REQUEST['_api_user'] | JSON 401 on failure
[PATTERN] API Rate Limiting | key-based | 60 requests/minute per API key | ApiRateLimitMiddleware::check() | JSON 429 with Retry-After header
[PATTERN] Template Rendering | output buffering | BaseController::view() → extract($data) → ob_start() → include template → ob_get_clean() | layout wraps content
[PATTERN] Form Builder | FormBuilder static class | Form::open() auto-includes CSRF + method spoofing | field methods generate Bootstrap 5 markup | auto validation error states (is-invalid + invalid-feedback) | old input repopulation via Flash::old()
[PATTERN] Flash Messages | session-based one-redirect | Flash::set(type, message) → stored in $_SESSION['_flash'] → rendered by partials/flash-messages.php as Bootstrap alerts → auto-cleared after display
[PATTERN] Validation | Validator::make(data, rules)->validate() | per-field errors stored in $_SESSION['_validation_errors'] | used by FormBuilder for error display | rules: required, string, email, min, max, confirmed, unique, in
[PATTERN] Base Model | abstract Model class | static CRUD methods | $table + $fillable for mass-assignment protection | uses Database::getConnection() | returns plain arrays
[PATTERN] Password Hashing | ARGON2ID | password_hash(PASSWORD_ARGON2ID) → stored in MySQL users table → password_verify() on login
[PATTERN] Database Transactions | Database::transaction(callable) wraps callback in begin/commit with rollback on Throwable | nested transactions via SAVEPOINTs (sp_1, sp_2, ...) with $transactionDepth tracking | beginTransaction()/commit()/rollBack() for manual control | detects existing PDO transactions (e.g., test wrappers) and uses savepoints instead of real begin | throws RuntimeException on commit/rollBack without active transaction
[PATTERN] Database Persistence | MySQL 8.0 | PDO singleton via Database::getConnection() | tables: users, roles, permissions, role_permissions, user_roles, remember_tokens, rate_limits, api_keys | env-based config
[PATTERN] Error Handling | ErrorHandler registered in index.php | set_exception_handler + set_error_handler + register_shutdown_function | HttpException hierarchy (404, 403, custom status) | ValidationException redirects with errors + old input | debug mode: detailed dev page with stack trace + code snippet | production mode: branded error pages via views/errors/ | API routes (/api/*): JSON error responses | logs to error channel (404→notice, 403→warning, 500→error) | PHP warnings/notices converted to ErrorException | fatal errors caught via shutdown function
[PATTERN] Request Logging | RequestLogMiddleware::start() in index.php | register_shutdown_function logs on every request | method, URI, status, duration_ms, IP, user_id | Logger::channel('requests') → storage/logs/requests.log
[PATTERN] Password Reset | email-based reset flow | forgot form → validate email → generate token (SHA256, 1hr, one per user) → send email via Mailer::sendTemplate() → reset form → validate token + set password → delete token + clear remember tokens | no user enumeration (same success message regardless) | single-use tokens | CSRF on POST routes
[PATTERN] Mail | Mailer static class | raw SMTP via stream_socket_client | STARTTLS (587) or SSL (465) | AUTH LOGIN | sendTemplate() renders PHP view with ob_start | logs to mail channel | returns false on failure (never throws) | config via app.php mail section
[PATTERN] Security | defense in depth | CSRF tokens + rate limiting + session regeneration + output escaping + httponly cookies + .htaccess blocking + ARGON2ID hashing + timing-safe comparison + RBAC + API key hashing
[PATTERN] CLI Commands | CommandRunner auto-discovers *Command.php in src/Commands/ | abstract Command base with output helpers (STDOUT/STDERR, ANSI colors) | built-in: migrate (database/migrations/*.sql tracking), cache:clear (Cache::clear()), password:reset (username + password args) | entry point: cli (project root, executable)

## TESTING

[TEST] phpunit.xml | phpunit.xml | PHPUnit 11 config | suites: Unit, Integration | env vars for test DB (app_test) | bootstrap: tests/bootstrap.php
[TEST] bootstrap.php | tests/bootstrap.php | test bootstrap | loads autoloader, enables ExitTrap test mode, starts session
[TEST] TestCase | tests/TestCase.php | base test class | saves/restores superglobals ($_SESSION, $_SERVER, $_POST, $_REQUEST, $_COOKIE) | resets Router, FileSystem, Logger, Mailer, ErrorHandler, RequestLogMiddleware, FormBuilder, CsrfMiddleware, RateLimitMiddleware, SecurityHeadersMiddleware, Paginator, FileUpload, Cache, CorsMiddleware
[TEST] DatabaseTestCase | tests/DatabaseTestCase.php | extends TestCase | connects to app_test DB | wraps each test in transaction + rollback | createTestUser(overrides, roles=['viewer']), getRoleId() helpers
[TEST] Unit/Core/FlashTest | tests/Unit/Core/FlashTest.php | 12 tests | set/get, multiple messages, all(), has(), old input
[TEST] Unit/Core/ValidatorTest | tests/Unit/Core/ValidatorTest.php | 25 tests | all rules except unique | fieldLabel humanization
[TEST] Unit/Core/FormBuilderTest | tests/Unit/Core/FormBuilderTest.php | 20 tests | open/close, field types, CSRF, method spoofing, validation errors, old input
[TEST] Unit/Routing/RouteTest | tests/Unit/Routing/RouteTest.php | 15 tests | matches(), where(), name(), middleware(), setPrefix(), addMiddleware()
[TEST] Unit/Routing/RouterTest | tests/Unit/Routing/RouterTest.php | 18 tests | HTTP methods, named routes, url(), groups, resource(), dispatch(), method spoofing
[TEST] Unit/Routing/MiddlewarePipelineTest | tests/Unit/Routing/MiddlewarePipelineTest.php | 5 tests | run(), register(), parameters, ordering
[TEST] Integration/Core/DatabaseTransactionTest | tests/Integration/Core/DatabaseTransactionTest.php | 8 tests | transaction commit/rollback, return value, nested savepoints, manual begin/commit/rollback, throws without active transaction
[TEST] Integration/Core/ModelTest | tests/Integration/Core/ModelTest.php | 13 tests | CRUD via Role model | find, findBy, where, all, create, update, delete, query
[TEST] Integration/Core/ValidatorUniqueTest | tests/Integration/Core/ValidatorUniqueTest.php | 4 tests | unique rule with DB
[TEST] Integration/Models/UserTest | tests/Integration/Models/UserTest.php | 17 tests | authenticate, updatePassword, setPassword, roles, hasRole, hasPermission (multi-role)
[TEST] Integration/Models/UserRoleSyncTest | tests/Integration/Models/UserRoleSyncTest.php | 5 tests | syncRoles: assign, reassign, multiple, clear, idempotent
[TEST] Integration/Models/PasswordResetTokenTest | tests/Integration/Models/PasswordResetTokenTest.php | 8 tests | createToken, validate (valid/expired/invalid/inactive), deleteToken, clearForUser, token replacement (one per user)
[TEST] Integration/Models/RememberTokenTest | tests/Integration/Models/RememberTokenTest.php | 8 tests | createToken, validate, deleteToken, clearForUser
[TEST] Integration/Models/RoleTest | tests/Integration/Models/RoleTest.php | 8 tests | findBySlug, permissions, hasPermission, seeded roles, users(), syncPermissions()
[TEST] Integration/Models/ApiKeyTest | tests/Integration/Models/ApiKeyTest.php | 8 tests | generate, validateKey, forUser, revoke, ownership
[TEST] Integration/Middleware/RateLimitTest | tests/Integration/Middleware/RateLimitTest.php | 10 tests | check, recordAttempt, lockout, progressive, clear, disabled
[TEST] Integration/Middleware/CsrfTest | tests/Integration/Middleware/CsrfTest.php | 10 tests | token, field, validate, verify, regenerate, clear
[TEST] Integration/Middleware/AuthMiddlewareTest | tests/Integration/Middleware/AuthMiddlewareTest.php | 14 tests | check, setAuthenticated (multi-role), requireAuth, requireRole (membership), requirePermission, user
[TEST] Integration/Middleware/ApiAuthMiddlewareTest | tests/Integration/Middleware/ApiAuthMiddlewareTest.php | 6 tests | verify with valid/invalid/missing header, inactive user
[TEST] Integration/Middleware/ApiRateLimitTest | tests/Integration/Middleware/ApiRateLimitTest.php | 4 tests | check passes/fails, no api user, records attempt
[TEST] Unit/Core/MailerTest | tests/Unit/Core/MailerTest.php | 3 tests | setConfig overrides, reset clears config, send returns false on connection failure
[TEST] Unit/Core/ErrorHandlerTest | tests/Unit/Core/ErrorHandlerTest.php | 16 tests | HttpException status codes, dev/production pages, API JSON responses, ValidationException redirect, error-to-exception conversion, log levels (404→notice, 403→warning, 500→error), production detail suppression
[TEST] Unit/Middleware/RequestLogMiddlewareTest | tests/Unit/Middleware/RequestLogMiddlewareTest.php | 6 tests | logs method/URI/status/duration, includes user_id when authenticated, reset clears state
[TEST] Unit/Core/CacheTest | tests/Unit/Core/CacheTest.php | 14 tests | get/set round trip, default on miss, has true/false, delete, clear, expired returns default, remember caches on miss, remember returns cached, TTL override, driver injection, reset clears driver, key sanitization, complex value serialization
[TEST] Unit/Middleware/SecurityHeadersTest | tests/Unit/Middleware/SecurityHeadersTest.php | 8 tests | config defaults, disabled skips headers, reset clears config, custom frame_options, CSP when configured, headers_sent handling, empty CSP, setConfig override
[TEST] Unit/Core/FileUploadTest | tests/Unit/Core/FileUploadTest.php | 11 tests | null for no upload, null for no file error, success returns array, unique filename with extension, custom directory, throws on disallowed mime, throws on too large, throws on upload error, delete removes file, delete rejects non-uploads path, reset clears config
[TEST] Unit/Commands/CommandRunnerTest | tests/Unit/Commands/CommandRunnerTest.php | 4 tests | no args shows help, unknown command returns 1, discovers built-in commands (migrate, cache:clear, password:reset), runs valid command
[TEST] Unit/Core/PaginationResultTest | tests/Unit/Core/PaginationResultTest.php | tests PaginationResult value object | links HTML structure, empty for single page, active page, prev/next disabled, query string preserved, ellipsis
[TEST] Unit/Middleware/CorsMiddlewareTest | tests/Unit/Middleware/CorsMiddlewareTest.php | 12 tests | no headers without Origin, OPTIONS preflight exits, non-OPTIONS passes, rejects disallowed origin, wildcard allows any, credentials support, reset clears config
[TEST] Integration/Core/PaginatorTest | tests/Integration/Core/PaginatorTest.php | tests Paginator with DB | correct page results, default page 1, clamps to last page, conditions filtering, order by, empty table, fromQuery
[TEST] Integration/Core/SoftDeleteTest | tests/Integration/Core/SoftDeleteTest.php | 11 tests | delete sets deleted_at, find/findBy/where/all exclude deleted, withTrashed includes, onlyTrashed returns only deleted, flags auto-reset, restore, forceDelete, non-soft-delete model
[TEST] Integration/SmokeTest | tests/Integration/SmokeTest.php | 8 HTTP smoke tests | homepage 200, login 200, forgot-password 200, 404 for missing, profile redirects/403, admin requires auth, security headers present, API 401 without auth | requires running Docker app container | uses curl to http://localhost:8088
[TEST] Integration/Commands/MigrateCommandTest | tests/Integration/Commands/MigrateCommandTest.php | 4 tests | creates migrations table, runs pending migrations, skips executed migrations, reports nothing when up to date

## SESSION KEYS

[CONST] Session Keys | authenticated(bool), user_id(int), username(string), user_roles(array of slugs), user_name(string), login_time(int), _csrf_token(string), _flash(array), _old_input(array), _validation_errors(array), _new_api_key(string|temp)
[CONST] Cookie Names | app_session (session), remember_token (remember-me)
