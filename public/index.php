<?php

// Front controller

// Load autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load configuration
$config = require dirname(__DIR__) . '/config/app.php';

// Set timezone
date_default_timezone_set($config['timezone']);

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

// Load routes
$routes = require dirname(__DIR__) . '/config/routes.php';

// Create router and dispatch
$router = new App\Routing\Router($routes);

// Get request method and URI
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Dispatch the request
echo $router->dispatch($method, $uri);
