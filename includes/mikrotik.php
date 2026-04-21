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
 * Generate a WireGuard Curve25519 keypair using the best available method:
 *
 *   1. PHP sodium extension  (php-sodium / libsodium — fastest)
 *   2. `wg genkey` CLI       (wireguard-tools on the web server)
 *   3. `openssl genpkey` CLI (OpenSSL binary — almost always present)
 *   4. PHP openssl extension with X25519 curve (PHP 8.0+ / OpenSSL 1.1+)
 *   5. Router API            (RouterOS 7.x — last resort, requires live connection)
 *
 * Throws RuntimeException only when every method fails.
 */
function generateKeypairLocally(): array
{
    // ── Method 1: PHP sodium ────────────────────────────────────────
    if (function_exists('sodium_crypto_scalarmult_base')) {
        $priv    = random_bytes(32);
        $priv[0]  = chr(ord($priv[0])  & 248);
        $priv[31] = chr((ord($priv[31]) & 127) | 64);
        return [
            'private-key' => base64_encode($priv),
            'public-key'  => base64_encode(sodium_crypto_scalarmult_base($priv)),
        ];
    }

    // Helper: check if a shell function is actually callable
    $shellOk = function_exists('exec')
        && !in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))));

    // ── Method 2: wg CLI ────────────────────────────────────────────
    if ($shellOk) {
        exec('wg genkey 2>/dev/null', $wgOut, $wgRc);
        if ($wgRc === 0 && !empty($wgOut[0])) {
            $priv = trim($wgOut[0]);
            exec('printf %s ' . escapeshellarg($priv) . ' | wg pubkey 2>/dev/null', $pkOut, $pkRc);
            if ($pkRc === 0 && !empty($pkOut[0])) {
                return ['private-key' => $priv, 'public-key' => trim($pkOut[0])];
            }
        }
    }

    // ── Method 3: openssl CLI + DER parsing ─────────────────────────
    // PKCS8 X25519 DER layout (48 bytes total):
    //   30 2e 02 01 00 30 05 06 03 [2b 65 6e] 04 22 04 20 [32-byte privkey]
    //   offset 0                               8  9  10     16            47
    // SubjectPublicKeyInfo DER layout (44 bytes total):
    //   30 2a 30 05 06 03 [2b 65 6e] 03 21 00 [32-byte pubkey]
    //   offset 0           4  5  6    9  10 11 12            43
    if ($shellOk && function_exists('proc_open')) {
        exec('openssl genpkey -algorithm X25519 2>/dev/null', $pemLines, $opensslRc);
        if ($opensslRc === 0 && count($pemLines) > 2) {
            $privPem = implode("\n", $pemLines);
            $privDer = base64_decode(preg_replace('/-----[^-]+-----|[\r\n\s]/', '', $privPem));

            // Validate X25519 OID (2b 65 6e) at offset 8
            if (strlen($privDer) >= 48 && substr($privDer, 8, 3) === "\x2b\x65\x6e") {
                $rawPriv = substr($privDer, 16, 32);

                // Pipe private key into `openssl pkey -pubout` to get public key
                $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
                $ph   = proc_open('openssl pkey -pubout 2>/dev/null', $desc, $pipes);
                if (is_resource($ph)) {
                    fwrite($pipes[0], $privPem);
                    fclose($pipes[0]);
                    $pubPem = stream_get_contents($pipes[1]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($ph);

                    $pubDer = base64_decode(preg_replace('/-----[^-]+-----|[\r\n\s]/', '', $pubPem));
                    // Validate X25519 OID at offset 4
                    if (strlen($pubDer) >= 44 && substr($pubDer, 4, 3) === "\x2b\x65\x6e") {
                        $rawPub = substr($pubDer, 12, 32);
                        if (strlen($rawPriv) === 32 && strlen($rawPub) === 32) {
                            return [
                                'private-key' => base64_encode($rawPriv),
                                'public-key'  => base64_encode($rawPub),
                            ];
                        }
                    }
                }
            }
        }
    }

    // ── Method 4: PHP openssl extension (PHP 8.0+ / OpenSSL 1.1+) ──
    if (function_exists('openssl_pkey_new')) {
        $res = @openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'X25519',
        ]);
        if ($res !== false) {
            openssl_pkey_export($res, $privPem);
            $det     = openssl_pkey_get_details($res);
            $privDer = base64_decode(preg_replace('/-----[^-]+-----|[\r\n\s]/', '', $privPem));
            $pubDer  = base64_decode(preg_replace('/-----[^-]+-----|[\r\n\s]/', '', $det['key'] ?? ''));
            if (strlen($privDer) >= 48 && strlen($pubDer) >= 44) {
                $rawPriv = substr($privDer, 16, 32);
                $rawPub  = substr($pubDer,  12, 32);
                if (strlen($rawPriv) === 32 && strlen($rawPub) === 32) {
                    return [
                        'private-key' => base64_encode($rawPriv),
                        'public-key'  => base64_encode($rawPub),
                    ];
                }
            }
        }
    }

    throw new RuntimeException(
        'WireGuard keypair generation failed — no suitable method found on this server. ' .
        'Install one of: php-sodium  |  wireguard-tools  |  openssl (CLI)'
    );
}

/**
 * Generate a WireGuard keypair (public entry point).
 * Tries local generation first; falls back to router API as last resort.
 */
function mtGenerateKeypair(): array
{
    try {
        return generateKeypairLocally();
    } catch (RuntimeException $localEx) {
        // Last-resort: ask the router (RouterOS 7.x +generate-keypair command)
        try {
            $api  = mtConnect();
            $rows = $api->comm('/interface/wireguard/generate-keypair');
            $api->disconnect();
            if (!empty($rows[0]['public-key'])) {
                return $rows[0];
            }
        } catch (Throwable $routerEx) {
            // fall through to re-throw the original local error
        }
        throw new RuntimeException(
            $localEx->getMessage() .
            ' — Router API also unavailable. ' .
            'Quick fix: run  apt install php-sodium && service php*-fpm restart'
        );
    }
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
