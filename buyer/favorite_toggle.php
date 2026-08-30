<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_helpers.php';

require_role('BUYER');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('properties/index.php');
}

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$propertyId = (int) ($_POST['property_id'] ?? 0);
$return = buyer_safe_return_path($_POST['return'] ?? '');

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    flash_set('error', 'Invalid request. Please try again.');
    redirect($return);
}

try {
    [$ok, $message] = buyer_toggle_favorite(db(), $userId, $propertyId);
    flash_set($ok ? 'success' : 'error', $message);
} catch (Throwable $e) {
    flash_set('error', 'Unable to update favorites right now. Please try again later.');
}

redirect($return);
