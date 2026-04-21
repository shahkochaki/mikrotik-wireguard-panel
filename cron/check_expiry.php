<?php

/**
 * Cron job: sync bandwidth from router, disable expired & over-quota users.
 * Recommended schedule: every minute
 *   * * * * * php /path/to/wireguard-panel/cron/check_expiry.php >> /var/log/wg_expiry.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mikrotik.php';

$now = date('Y-m-d H:i:s');
echo "[{$now}] WireGuard cron started\n";

// ── Step 1: Sync bandwidth stats from router ──────────────────────
try {
    $wgInterface = getSetting('wg_interface', 'wireguard1');
    $stats       = mtGetAllPeerStats($wgInterface);

    if (!empty($stats)) {
        $dbUsers = dbQuery(
            'SELECT id, mikrotik_peer_id FROM wg_users WHERE mikrotik_peer_id IS NOT NULL'
        )->fetchAll();

        foreach ($dbUsers as $u) {
            $pid = $u['mikrotik_peer_id'];
            if (!isset($stats[$pid])) continue;
            $s  = $stats[$pid];
            $lh = null;
            if (!empty($s['last-handshake']) && $s['last-handshake'] !== 'never') {
                // RouterOS dates: "jan/15/2024 10:30:00"
                $months = [
                    'jan' => 1,
                    'feb' => 2,
                    'mar' => 3,
                    'apr' => 4,
                    'may' => 5,
                    'jun' => 6,
                    'jul' => 7,
                    'aug' => 8,
                    'sep' => 9,
                    'oct' => 10,
                    'nov' => 11,
                    'dec' => 12
                ];
                if (preg_match(
                    '/^(\w{3})\/(\d{1,2})\/(\d{4})\s+(\d{2}:\d{2}:\d{2})$/i',
                    $s['last-handshake'],
                    $m
                )) {
                    $mo = $months[strtolower($m[1])] ?? null;
                    if ($mo) {
                        $lh = sprintf('%04d-%02d-%02d %s', $m[3], $mo, $m[2], $m[4]);
                    }
                }
            }
            dbQuery(
                'UPDATE wg_users SET rx_bytes = ?, tx_bytes = ?, last_handshake = ? WHERE id = ?',
                [$s['rx'], $s['tx'], $lh, $u['id']]
            );
        }
        echo "[{$now}] Bandwidth synced for " . count($dbUsers) . " users.\n";
    }
} catch (Throwable $e) {
    echo "[{$now}] WARNING: Could not sync bandwidth: {$e->getMessage()}\n";
}

// ── Step 2: Disable expired users ────────────────────────────────
$expired = dbQuery(
    'SELECT id, name, mikrotik_peer_id, mikrotik_queue_id
     FROM wg_users
     WHERE is_active = 1
       AND expiry_date IS NOT NULL
       AND expiry_date < NOW()'
)->fetchAll();

$expiredCount = 0;
foreach ($expired as $user) {
    try {
        if ($user['mikrotik_peer_id']) mtSetPeerDisabled($user['mikrotik_peer_id'], true);
        if ($user['mikrotik_queue_id']) mtSetQueueDisabled($user['mikrotik_queue_id'], true);
        dbQuery('UPDATE wg_users SET is_active = 0 WHERE id = ?', [$user['id']]);
        echo "[{$now}] Expired: {$user['name']} (id={$user['id']})\n";
        $expiredCount++;
    } catch (Throwable $e) {
        echo "[{$now}] ERROR disabling expired {$user['name']}: {$e->getMessage()}\n";
    }
}

// ── Step 3: Disable over-quota users ─────────────────────────────
$overQuota = dbQuery(
    'SELECT id, name, mikrotik_peer_id, mikrotik_queue_id, rx_bytes, tx_bytes, data_limit_gb
     FROM wg_users
     WHERE is_active = 1
       AND data_limit_gb IS NOT NULL
       AND data_limit_gb > 0
       AND (rx_bytes + tx_bytes) >= (data_limit_gb * 1073741824)'
)->fetchAll();

$quotaCount = 0;
foreach ($overQuota as $user) {
    try {
        if ($user['mikrotik_peer_id']) mtSetPeerDisabled($user['mikrotik_peer_id'], true);
        if ($user['mikrotik_queue_id']) mtSetQueueDisabled($user['mikrotik_queue_id'], true);
        dbQuery('UPDATE wg_users SET is_active = 0 WHERE id = ?', [$user['id']]);
        $used = formatBytes((int)($user['rx_bytes'] + $user['tx_bytes']));
        echo "[{$now}] Over-quota: {$user['name']} — used {$used} / {$user['data_limit_gb']} GB\n";
        $quotaCount++;
    } catch (Throwable $e) {
        echo "[{$now}] ERROR disabling over-quota {$user['name']}: {$e->getMessage()}\n";
    }
}

echo "[{$now}] Done — expired: {$expiredCount}, over-quota: {$quotaCount}.\n";
exit(0);
