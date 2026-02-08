<?php

use App\Controllers\AuthController;
use App\Controllers\HomeController;

return [
    // Home
    ['GET', '/', [HomeController::class, 'index']],

    // Authentication routes
    ['GET', '/login', [AuthController::class, 'showLogin']],
    ['POST', '/login', [AuthController::class, 'login']],
    ['GET', '/logout', [AuthController::class, 'logout']],
    ['GET', '/change-password', [AuthController::class, 'showChangePassword']],
    ['POST', '/change-password', [AuthController::class, 'changePassword']],
];
