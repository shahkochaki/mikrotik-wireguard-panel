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
 * Generate a WireGuard keypair locally using PHP's libsodium (Curve25519).
 * No router connection needed. PHP 7.2+ has sodium bundled.
 */
function generateKeypairLocally(): array
{
    $private = random_bytes(32);
    // Clamp the private key per Curve25519 spec
    $private[0]  = chr(ord($private[0])  & 248);
    $private[31] = chr((ord($private[31]) & 127) | 64);
    $public = sodium_crypto_scalarmult_base($private);
    return [
        'private-key' => base64_encode($private),
        'public-key'  => base64_encode($public),
    ];
}

/**
 * Generate a WireGuard keypair.
 * Uses local PHP/sodium generation (no router connection needed).
 * Falls back to router API only if sodium extension is unavailable.
 */
function mtGenerateKeypair(): array
{
    // Prefer local generation — fast, zero router dependency
    if (function_exists('sodium_crypto_scalarmult_base')) {
        return generateKeypairLocally();
    }
    // Fallback: ask the router (RouterOS 7.x only)
    $api  = mtConnect();
    $rows = $api->comm('/interface/wireguard/generate-keypair');
    $api->disconnect();
    if ($rows === false || empty($rows)) {
        throw new RuntimeException(
            'Failed to generate keypair — PHP sodium extension not available and router returned no data'
        );
    }
    return $rows[0];
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
 * Fetch live stats (rx, tx, last-handshake) for ALL peers on an interface in one call.
 * Returns keyed array: [peerId => ['rx' => int, 'tx' => int, 'last-handshake' => string|null]]
 */
function mtGetAllPeerStats(string $wgInterface): array
{
    $api  = mtConnect();
    $rows = $api->comm('/interface/wireguard/peers/print', [], ['?interface=' . $wgInterface]);
    $api->disconnect();
    if (!$rows) return [];
    $out = [];
    foreach ($rows as $row) {
        $id = $row['.id'] ?? null;
        if ($id !== null) {
            $out[$id] = [
                'rx'             => (int)($row['rx'] ?? 0),
                'tx'             => (int)($row['tx'] ?? 0),
                'last-handshake' => $row['last-handshake-time'] ?? null,
            ];
        }
    }
    return $out;
}

/**
 * Fetch router system information: identity, CPU, memory, uptime, version, peer count.
 */
function mtGetSystemInfo(): array
{
    $api   = mtConnect();
    $idn   = $api->comm('/system/identity/print');
    $res   = $api->comm('/system/resource/print');
    $wg    = getSetting('wg_interface', 'wireguard1');
    $peers = $api->comm('/interface/wireguard/peers/print', [], ['?interface=' . $wg]);
    $api->disconnect();

    $r        = $res[0] ?? [];
    $freeMem  = (int)($r['free-memory']  ?? 0);
    $totalMem = (int)($r['total-memory'] ?? 1);

    return [
        'identity'     => $idn[0]['name'] ?? 'Unknown',
        'uptime'       => $r['uptime']    ?? 'N/A',
        'version'      => $r['version']   ?? 'N/A',
        'cpu_load'     => (int)($r['cpu-load'] ?? 0),
        'free_memory'  => $freeMem,
        'total_memory' => $totalMem,
        'mem_percent'  => $totalMem > 0 ? round(100 - ($freeMem / $totalMem * 100)) : 0,
        'board_name'   => $r['board-name'] ?? 'N/A',
        'peer_count'   => count($peers ?: []),
    ];
}

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
