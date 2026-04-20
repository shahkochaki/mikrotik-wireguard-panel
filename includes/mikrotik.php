<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../lib/RouterosAPI.php';

/**
 * Create and return an authenticated RouterosAPI instance
 * using settings stored in the database.
 * Throws RuntimeException on failure.
 */
function mtConnect(): RouterosAPI
{
    $host = getSetting('mt_host', '192.168.88.1');
    $user = getSetting('mt_user', 'admin');
    $pass = getSetting('mt_pass', '');
    $port = (int) getSetting('mt_port', '8728');

    $api = new RouterosAPI();
    if (!$api->connect($host, $user, $pass, $port)) {
        throw new RuntimeException('MikroTik connection failed: ' . $api->error);
    }
    return $api;
}

// ----------------------------------------------------------------
// WireGuard Peer Management
// ----------------------------------------------------------------

/**
 * Generate a WireGuard keypair via MikroTik.
 * Returns ['public-key' => ..., 'private-key' => ...] or throws.
 */
function mtGenerateKeypair(): array
{
    $api  = mtConnect();
    $rows = $api->comm('/interface/wireguard/generate-keypair');
    $api->disconnect();

    if ($rows === false || empty($rows)) {
        throw new RuntimeException('Failed to generate keypair');
    }
    return $rows[0]; // {'public-key': ..., 'private-key': ...}
}

/**
 * Add a WireGuard peer to MikroTik.
 * Returns the MikroTik peer .id on success, throws on failure.
 */
function mtAddPeer(string $publicKey, string $allowedAddress, string $wgInterface): string
{
    $api = mtConnect();
    $rows = $api->comm('/interface/wireguard/peers/add', [
        'interface'       => $wgInterface,
        'public-key'      => $publicKey,
        'allowed-address' => $allowedAddress,
        'disabled'        => 'no',
    ]);
    $api->disconnect();

    if ($rows === false) {
        throw new RuntimeException('Failed to add WireGuard peer: ' . (new RouterosAPI())->error);
    }
    // MikroTik returns [['ret' => '*XX']] for add commands
    return $rows[0]['ret'] ?? '';
}

/**
 * Remove a WireGuard peer by MikroTik .id
 */
function mtRemovePeer(string $peerId): void
{
    $api = mtConnect();
    $api->comm('/interface/wireguard/peers/remove', ['.id' => $peerId]);
    $api->disconnect();
}

/**
 * Enable or disable a WireGuard peer.
 */
function mtSetPeerDisabled(string $peerId, bool $disabled): void
{
    $api = mtConnect();
    $api->comm('/interface/wireguard/peers/set', [
        '.id'      => $peerId,
        'disabled' => $disabled ? 'yes' : 'no',
    ]);
    $api->disconnect();
}

/**
 * Get live stats for a peer (last-handshake, rx, tx).
 */
function mtGetPeerStats(string $peerId): array
{
    $api  = mtConnect();
    $rows = $api->comm('/interface/wireguard/peers/print', [], ['?.id=' . $peerId]);
    $api->disconnect();

    return $rows[0] ?? [];
}

/**
 * List all WireGuard peers from the router.
 */
function mtListPeers(string $wgInterface): array
{
    $api  = mtConnect();
    $rows = $api->comm('/interface/wireguard/peers/print', [], ['?interface=' . $wgInterface]);
    $api->disconnect();

    return $rows ?: [];
}

// ----------------------------------------------------------------
// Queue / Speed Limiting
// ----------------------------------------------------------------

/**
 * Add a Simple Queue for speed limiting a peer.
 * $download and $upload are MikroTik rate strings: '10M', '2M', etc.
 * Returns the queue .id.
 */
function mtAddQueue(string $name, string $targetIp, string $download, string $upload): string
{
    $api  = mtConnect();
    // MikroTik max-limit format: upload/download
    $rows = $api->comm('/queue/simple/add', [
        'name'      => $name,
        'target'    => $targetIp . '/32',
        'max-limit' => $upload . '/' . $download,
        'comment'   => 'WG:' . $name,
    ]);
    $api->disconnect();

    if ($rows === false) {
        throw new RuntimeException('Failed to add queue');
    }
    return $rows[0]['ret'] ?? '';
}

/**
 * Update speed on an existing queue.
 */
function mtUpdateQueue(string $queueId, string $download, string $upload): void
{
    $api = mtConnect();
    $api->comm('/queue/simple/set', [
        '.id'       => $queueId,
        'max-limit' => $upload . '/' . $download,
    ]);
    $api->disconnect();
}

/**
 * Remove a Simple Queue by .id
 */
function mtRemoveQueue(string $queueId): void
{
    $api = mtConnect();
    $api->comm('/queue/simple/remove', ['.id' => $queueId]);
    $api->disconnect();
}

/**
 * Enable or disable a queue.
 */
function mtSetQueueDisabled(string $queueId, bool $disabled): void
{
    $api = mtConnect();
    $api->comm('/queue/simple/set', [
        '.id'      => $queueId,
        'disabled' => $disabled ? 'yes' : 'no',
    ]);
    $api->disconnect();
}

// ----------------------------------------------------------------
// Connectivity test
// ----------------------------------------------------------------

/**
 * Ping the router and return router identity string.
 */
function mtGetIdentity(): string
{
    try {
        $api  = mtConnect();
        $rows = $api->comm('/system/identity/print');
        $api->disconnect();
        return $rows[0]['name'] ?? 'Unknown';
    } catch (Throwable $e) {
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Run a full step-by-step diagnostic against the saved settings.
 * Returns an array of steps:
 *   [['label' => '...', 'ok' => true/false, 'detail' => '...'], ...]
 * The caller can JSON-encode this for AJAX responses.
 */
function mtDiagnose(?string $host = null, ?string $user = null, ?string $pass = null, ?int $port = null): array
{
    $host = $host ?? getSetting('mt_host', '192.168.88.1');
    $user = $user ?? getSetting('mt_user', 'admin');
    $pass = $pass ?? getSetting('mt_pass', '');
    $port = $port ?? (int) getSetting('mt_port', '8728');

    $steps = [];

    // --- Step 1: Validate settings are filled ---
    $cfgOk = ($host !== '' && $port > 0 && $user !== '');
    $steps[] = [
        'label'  => 'بررسی تنظیمات',
        'ok'     => $cfgOk,
        'detail' => $cfgOk
            ? "host={$host}  port={$port}  user={$user}"
            : 'آدرس IP، پورت یا نام کاربری پر نشده است.',
    ];
    if (!$cfgOk) return $steps;

    // --- Step 2: TCP port reachability ---
    $portErr = '';
    $portOk  = RouterosAPI::portCheck($host, $port, 3, $portErr);
    $steps[] = [
        'label'  => "اتصال TCP به {$host}:{$port}",
        'ok'     => $portOk,
        'detail' => $portOk
            ? 'پورت باز است و روتر پاسخ می‌دهد.'
            : "پورت بسته یا قابل دسترس نیست: {$portErr}",
    ];
    if (!$portOk) return $steps;

    // --- Step 3: API login ---
    $api      = new RouterosAPI();
    $loginOk  = $api->connect($host, $user, $pass, $port, 4);
    $steps[] = [
        'label'  => 'ورود به API میکروتیک',
        'ok'     => $loginOk,
        'detail' => $loginOk
            ? 'احراز هویت موفق بود.'
            : 'خطای ورود: ' . $api->error,
    ];
    if (!$loginOk) return $steps;

    // --- Step 4: Fetch identity ---
    $rows  = $api->comm('/system/identity/print');
    $name  = $rows[0]['name'] ?? null;
    $steps[] = [
        'label'  => 'دریافت اطلاعات روتر',
        'ok'     => ($name !== null),
        'detail' => $name !== null
            ? "نام روتر: {$name}"
            : 'پاسخ نامعتبر از روتر.',
    ];

    // --- Step 5: Check WireGuard interface ---
    $wgIface  = getSetting('wg_interface', 'wireguard1');
    $wgRows   = $api->comm('/interface/wireguard/print', [], ['?name=' . $wgIface]);
    $wgFound  = !empty($wgRows);
    $steps[] = [
        'label'  => "اینترفیس WireGuard «{$wgIface}»",
        'ok'     => $wgFound,
        'detail' => $wgFound
            ? 'اینترفیس روی روتر وجود دارد.'
            : "اینترفیس «{$wgIface}» پیدا نشد. نام را در تنظیمات بررسی کنید.",
    ];

    $api->disconnect();
    return $steps;
}
