<?php

/**
 * AJAX endpoint for user actions: toggle_user, delete_user.
 * All requests must be POST with a valid CSRF token.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mikrotik.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF validation failed']);
    exit;
}

$action = $_POST['action'] ?? '';

// ── toggle_user ──────────────────────────────────────────────────
if ($action === 'toggle_user') {
    $id   = (int)($_POST['id'] ?? 0);
    $user = getUserById($id);
    if (!$user) {
        echo json_encode(['error' => 'کاربر یافت نشد']);
        exit;
    }

    $newActive = $user['is_active'] ? 0 : 1;
    $routerOk  = true;
    $routerMsg = '';

    try {
        if ($user['mikrotik_peer_id']) {
            mtSetPeerDisabled($user['mikrotik_peer_id'], !$newActive);
        }
        if ($user['mikrotik_queue_id']) {
            mtSetQueueDisabled($user['mikrotik_queue_id'], !$newActive);
        }
    } catch (Throwable $e) {
        $routerOk  = false;
        $routerMsg = $e->getMessage();
        // Still update DB — router can be synced later via cron
    }

    dbQuery('UPDATE wg_users SET is_active = ? WHERE id = ?', [$newActive, $id]);

    echo json_encode([
        'success'    => true,
        'is_active'  => $newActive,
        'name'       => $user['name'],
        'router_ok'  => $routerOk,
        'router_msg' => $routerMsg,
    ]);
    exit;
}

// ── delete_user ──────────────────────────────────────────────────
if ($action === 'delete_user') {
    $id   = (int)($_POST['id'] ?? 0);
    $user = getUserById($id);
    if (!$user) {
        echo json_encode(['error' => 'کاربر یافت نشد']);
        exit;
    }

    $routerOk  = true;
    $routerMsg = '';

    try {
        if ($user['mikrotik_peer_id']) {
            mtRemovePeer($user['mikrotik_peer_id']);
        }
        if ($user['mikrotik_queue_id']) {
            mtRemoveQueue($user['mikrotik_queue_id']);
        }
    } catch (Throwable $e) {
        $routerOk  = false;
        $routerMsg = $e->getMessage();
        // Continue — delete from DB even if router removal fails
    }

    dbQuery('DELETE FROM wg_users WHERE id = ?', [$id]);

    echo json_encode([
        'success'    => true,
        'name'       => $user['name'],
        'router_ok'  => $routerOk,
        'router_msg' => $routerMsg,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
