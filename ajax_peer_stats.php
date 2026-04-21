<?php

/**
 * AJAX – returns live bandwidth stats for all WireGuard peers.
 * Falls back to DB-cached values if router is unreachable.
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

// Try to get live stats from router; fall back to DB cache on failure
$liveStats  = [];
$fromRouter = false;
try {
    $wgInterface = getSetting('wg_interface', 'wireguard1');
    $liveStats   = mtGetAllPeerStats($wgInterface);
    $fromRouter  = true;
} catch (Throwable $e) {
    // silent fall-through to DB cache
}

$users  = dbQuery(
    'SELECT id, mikrotik_peer_id, rx_bytes, tx_bytes, data_limit_gb, last_handshake FROM wg_users'
)->fetchAll();

$result = [];
foreach ($users as $u) {
    $pid  = $u['mikrotik_peer_id'];
    $live = ($pid && isset($liveStats[$pid])) ? $liveStats[$pid] : null;

    $rx    = $live ? $live['rx'] : (int)$u['rx_bytes'];
    $tx    = $live ? $live['tx'] : (int)$u['tx_bytes'];
    $total = $rx + $tx;
    $limit = $u['data_limit_gb'] !== null ? (float)$u['data_limit_gb'] : null;

    $result[] = [
        'id'              => (int)$u['id'],
        'rx_bytes'        => $rx,
        'tx_bytes'        => $tx,
        'total_bytes'     => $total,
        'rx_fmt'          => formatBytes($rx),
        'tx_fmt'          => formatBytes($tx),
        'total_fmt'       => formatBytes($total),
        'data_limit_gb'   => $limit,
        'usage_pct'       => dataUsagePercent($total, $limit),
        'last_handshake'  => $live ? ($live['last-handshake'] ?? null) : $u['last_handshake'],
        'from_router'     => $fromRouter && $live !== null,
    ];
}

echo json_encode(['success' => true, 'stats' => $result, 'from_router' => $fromRouter]);
