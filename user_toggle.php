<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mikrotik.php';

requireLogin();

$id   = getInt('id');
$user = getUserById($id);
if (!$user) {
    flashSet('danger', 'کاربر یافت نشد.');
    header('Location: users.php');
    exit;
}

// Toggle active state
$newActive = $user['is_active'] ? 0 : 1;

try {
    if ($user['mikrotik_peer_id']) {
        mtSetPeerDisabled($user['mikrotik_peer_id'], !$newActive);
    }
    if ($user['mikrotik_queue_id']) {
        mtSetQueueDisabled($user['mikrotik_queue_id'], !$newActive);
    }
    dbQuery('UPDATE wg_users SET is_active = ? WHERE id = ?', [$newActive, $id]);
    flashSet('success', 'وضعیت کاربر ' . e($user['name']) . ' تغییر کرد.');
} catch (Throwable $e) {
    flashSet('danger', 'خطا: ' . $e->getMessage());
}

header('Location: users.php');
exit;
