<?php

/**
 * AJAX – returns router system information (identity, CPU, memory, uptime, version, peer count).
 * Called from dashboard.php on page load.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mikrotik.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $info = mtGetSystemInfo();
    echo json_encode(['success' => true, 'data' => $info]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
