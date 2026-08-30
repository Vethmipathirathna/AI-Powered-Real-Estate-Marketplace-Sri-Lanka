<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

start_app_session();
logout_user();

// Start a fresh session so the logout flash can be shown on the login page.
start_app_session();
flash_set('success', 'You have been logged out.');
redirect('auth/login.php');
