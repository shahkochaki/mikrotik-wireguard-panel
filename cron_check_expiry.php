<?php

/**
 * Cron job: disable expired WireGuard users on MikroTik.
 * Run every minute via crontab:
 *   * * * * * php /path/to/wireguard-panel/cron_check_expiry.php >> /var/log/wg_expiry.log 2>&1
 */

// Allow CLI execution only (prevent web access)
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mikrotik.php';

$now = date('Y-m-d H:i:s');
echo "[{$now}] WireGuard expiry check started\n";

$expired = dbQuery(
    'SELECT id, name, mikrotik_peer_id, mikrotik_queue_id
     FROM wg_users
     WHERE is_active = 1
       AND expiry_date IS NOT NULL
       AND expiry_date < NOW()'
)->fetchAll();

if (empty($expired)) {
    echo "[{$now}] No expired users found.\n";
    exit(0);
}

$count = 0;
foreach ($expired as $user) {
    try {
        if ($user['mikrotik_peer_id']) {
            mtSetPeerDisabled($user['mikrotik_peer_id'], true);
        }
        if ($user['mikrotik_queue_id']) {
            mtSetQueueDisabled($user['mikrotik_queue_id'], true);
        }
        dbQuery('UPDATE wg_users SET is_active = 0 WHERE id = ?', [$user['id']]);
        echo "[{$now}] Disabled user: {$user['name']} (id={$user['id']})\n";
        $count++;
    } catch (Throwable $e) {
        echo "[{$now}] ERROR for user {$user['name']}: {$e->getMessage()}\n";
    }
}

echo "[{$now}] Done. Disabled {$count} user(s).\n";
exit(0);
