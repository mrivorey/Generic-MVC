<?php

use App\Routing\Router;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\PasswordResetController;
use App\Controllers\ProfileController;
use App\Controllers\Admin\UserController;
use App\Controllers\Admin\RoleController;
use App\Controllers\Api\UserApiController;

// Home
Router::get('/', [HomeController::class, 'index'])->name('home');

// Authentication routes
Router::get('/login', [AuthController::class, 'showLogin'])->name('login');
Router::post('/login', [AuthController::class, 'login'])->middleware('csrf');
Router::get('/logout', [AuthController::class, 'logout'])->name('logout');

// Password reset routes
Router::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.forgot');
Router::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('csrf');
Router::get('/reset-password', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Router::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('csrf');

// Profile routes
Router::get('/profile', [ProfileController::class, 'show'])->name('profile')->middleware('auth');
Router::get('/change-password', [ProfileController::class, 'showChangePassword'])->name('password.change')->middleware('auth');
Router::post('/change-password', [ProfileController::class, 'changePassword'])->middleware(['auth', 'csrf']);

// API Key management (session auth)
Router::get('/profile/api-keys', [ProfileController::class, 'apiKeys'])->name('profile.api-keys')->middleware('auth');
Router::post('/profile/api-keys', [ProfileController::class, 'generateApiKey'])->name('profile.api-keys.generate')->middleware(['auth', 'csrf']);
Router::post('/profile/api-keys/{id}/revoke', [ProfileController::class, 'revokeApiKey'])->name('profile.api-keys.revoke')->middleware(['auth', 'csrf'])->where('id', '[0-9]+');

// Admin routes
Router::group(['prefix' => '/admin', 'middleware' => ['auth', 'role:admin']], function () {
    // Users
    Router::get('/users', [UserController::class, 'index'])->name('admin.users.index');
    Router::get('/users/create', [UserController::class, 'create'])->name('admin.users.create');
    Router::post('/users', [UserController::class, 'store'])->name('admin.users.store')->middleware('csrf');
    Router::get('/users/{id}/edit', [UserController::class, 'edit'])->name('admin.users.edit')->where('id', '[0-9]+');
    Router::put('/users/{id}', [UserController::class, 'update'])->name('admin.users.update')->middleware('csrf')->where('id', '[0-9]+');
    Router::post('/users/{id}/delete', [UserController::class, 'destroy'])->name('admin.users.destroy')->middleware('csrf')->where('id', '[0-9]+');

    // Roles
    Router::get('/roles', [RoleController::class, 'index'])->name('admin.roles.index');
    Router::get('/roles/create', [RoleController::class, 'create'])->name('admin.roles.create');
    Router::post('/roles', [RoleController::class, 'store'])->name('admin.roles.store')->middleware('csrf');
    Router::get('/roles/{id}/edit', [RoleController::class, 'edit'])->name('admin.roles.edit')->where('id', '[0-9]+');
    Router::put('/roles/{id}', [RoleController::class, 'update'])->name('admin.roles.update')->middleware('csrf')->where('id', '[0-9]+');
    Router::post('/roles/{id}/delete', [RoleController::class, 'destroy'])->name('admin.roles.destroy')->middleware('csrf')->where('id', '[0-9]+');
});

// API v1 routes (cors runs first — handles OPTIONS preflight before auth)
Router::group(['prefix' => '/api/v1', 'middleware' => ['cors', 'api_auth', 'api_rate_limit']], function () {
    Router::get('/user', [UserApiController::class, 'me'])->name('api.user');
});
