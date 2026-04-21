<?php

/**
 * AJAX endpoint — tests MikroTik connection step by step.
 * Returns JSON array of diagnostic steps.
 * Accepts optional POST overrides (host, port, user, pass) so the
 * settings page can test *before* saving.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mikrotik.php';

header('Content-Type: application/json; charset=utf-8');

// Must be logged in
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Accept values from POST (settings form sends them before saving)
$host = isset($_POST['host']) ? trim($_POST['host']) : null;
$user = isset($_POST['user']) ? trim($_POST['user']) : null;
$pass = isset($_POST['pass']) ? $_POST['pass'] : null;
$port = isset($_POST['port']) && is_numeric($_POST['port']) ? (int) $_POST['port'] : null;

$steps = mtDiagnose($host, $user, $pass, $port);

echo json_encode([
    'steps'   => $steps,
    'success' => !empty($steps) && array_reduce($steps, fn($all, $s) => $all && $s['ok'], true),
]);
