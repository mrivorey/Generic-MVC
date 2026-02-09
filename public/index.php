<?php

// Front controller

// Load autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load configuration
$config = require dirname(__DIR__) . '/config/app.php';

// Set timezone
date_default_timezone_set($config['timezone']);

// Register error handler and request logging
\App\Core\ErrorHandler::register($config);
\App\Middleware\RequestLogMiddleware::start();

// Configure session
ini_set('session.save_path', $config['paths']['sessions']);
ini_set('session.gc_maxlifetime', $config['session']['lifetime']);
ini_set('session.cookie_lifetime', $config['session']['lifetime']);
ini_set('session.cookie_httponly', $config['session']['httponly'] ? '1' : '0');
ini_set('session.cookie_samesite', $config['session']['samesite']);

session_name($config['session']['name']);
session_start();

// Check for remember me token if not authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    \App\Middleware\AuthMiddleware::checkRememberToken();
}

// Load routes and dispatch
require dirname(__DIR__) . '/config/routes.php';
echo \App\Routing\Router::dispatch();
