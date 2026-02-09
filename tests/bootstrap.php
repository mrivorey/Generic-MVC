<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Enable ExitTrap test mode so exit() throws ExitException
\App\Core\ExitTrap::enableTestMode();

// Start session if not already started (needed for Flash, CSRF, etc.)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
