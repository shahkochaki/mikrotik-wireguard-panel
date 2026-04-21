<?php
require_once __DIR__ . '/db.php';

// ----------------------------------------------------------------
// Flash Messages
// ----------------------------------------------------------------

function flashSet(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flashGet(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function flashHtml(): string
{
    $flash = flashGet();
    if (!$flash) return '';
    $cls = match ($flash['type']) {
        'success' => 'alert-success',
        'danger'  => 'alert-danger',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    $msg = htmlspecialchars($flash['message']);
    return <<<HTML
    <div class="alert {$cls} alert-dismissible fade show" role="alert">
        {$msg}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    HTML;
}

// ----------------------------------------------------------------
// Input sanitization
// ----------------------------------------------------------------

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function postStr(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function getInt(string $key, int $default = 0): int
{
    return isset($_GET[$key]) ? (int) $_GET[$key] : $default;
}

// ----------------------------------------------------------------
// IP address helpers
// ----------------------------------------------------------------

/**
 * Find the next available IP in the WireGuard subnet.
 * The server_ip is always excluded.
 */
function nextAvailableIp(): string
{
    $subnet   = getSetting('wg_subnet', '10.0.0.0/24');
    $serverIp = getSetting('wg_server_ip', '10.0.0.1');

    [$network, $prefix] = explode('/', $subnet);
    $base  = ip2long($network);
    $count = (int) pow(2, 32 - (int) $prefix);

    // Gather already-used IPs from DB
    $usedRows = dbQuery('SELECT allowed_address FROM wg_users')->fetchAll();
    $used     = [];
    foreach ($usedRows as $row) {
        $ip    = explode('/', $row['allowed_address'])[0];
        $used[] = ip2long($ip);
    }
    $used[] = ip2long($serverIp); // exclude server
    $used[] = $base;              // exclude network address
    $used[] = $base + $count - 1; // exclude broadcast

    for ($i = 2; $i < $count; $i++) {
        $candidate = $base + $i;
        if (!in_array($candidate, $used, true)) {
            return long2ip($candidate) . '/32';
        }
    }

    throw new RuntimeException('No available IP addresses in subnet ' . $subnet);
}

// ----------------------------------------------------------------
// Speed formatting
// ----------------------------------------------------------------

function speedOptions(): array
{
    return [
        '512k' => '512 Kbps',
        '1M'   => '1 Mbps',
        '2M'   => '2 Mbps',
        '5M'   => '5 Mbps',
        '10M'  => '10 Mbps',
        '20M'  => '20 Mbps',
        '30M'  => '30 Mbps',
        '50M'  => '50 Mbps',
        '100M' => '100 Mbps',
        '200M' => '200 Mbps',
        '1G'   => '1 Gbps',
    ];
}

// ----------------------------------------------------------------
// WireGuard client config generator
// ----------------------------------------------------------------

function buildClientConfig(array $user, array $settings): string
{
    $privateKey  = $user['private_key']  ?? '';
    $address     = explode('/', $user['allowed_address'])[0]; // strip /32
    $dns         = $settings['wg_dns']         ?? '8.8.8.8';
    $serverPubKey = $settings['wg_server_public_key'] ?? '';
    $endpoint    = $settings['wg_endpoint']    ?? '';
    $allowedIps  = $settings['wg_allowed_ips'] ?? '0.0.0.0/0';
    $listenPort  = $settings['wg_listen_port'] ?? '13231';

    $psk = !empty($user['preshared_key'])
        ? 'PresharedKey = ' . $user['preshared_key'] . "\n"
        : '';

    return <<<CFG
    [Interface]
    PrivateKey = {$privateKey}
    Address = {$address}/32
    DNS = {$dns}

    [Peer]
    PublicKey = {$serverPubKey}
    {$psk}AllowedIPs = {$allowedIps}
    Endpoint = {$endpoint}:{$listenPort}
    PersistentKeepalive = 25
    CFG;
}

// ----------------------------------------------------------------
// User DB helpers
// ----------------------------------------------------------------

function getAllUsers(): array
{
    return dbQuery(
        'SELECT * FROM wg_users ORDER BY created_at DESC'
    )->fetchAll();
}

function getUserById(int $id): ?array
{
    $row = dbQuery('SELECT * FROM wg_users WHERE id = ?', [$id])->fetch();
    return $row ?: null;
}

function countUsers(): int
{
    return (int) dbQuery('SELECT COUNT(*) FROM wg_users')->fetchColumn();
}

function countActiveUsers(): int
{
    return (int) dbQuery('SELECT COUNT(*) FROM wg_users WHERE is_active = 1')->fetchColumn();
}

function countExpiredUsers(): int
{
    return (int) dbQuery(
        'SELECT COUNT(*) FROM wg_users WHERE expiry_date IS NOT NULL AND expiry_date < NOW()'
    )->fetchColumn();
}

// ----------------------------------------------------------------
// Formatting helpers
// ----------------------------------------------------------------

/**
 * Format bytes to a human-readable string (B, KB, MB, GB, TB).
 */
function formatBytes(int $bytes, int $decimals = 2): string
{
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int) min(floor(log($bytes, 1024)), count($units) - 1);
    return round($bytes / (1024 ** $i), $decimals) . ' ' . $units[$i];
}

/**
 * Calculate bandwidth usage percentage for a user.
 * Returns null if no data limit is set.
 */
function dataUsagePercent(int $usedBytes, ?float $limitGb): ?float
{
    if ($limitGb === null || $limitGb <= 0) return null;
    $limitBytes = $limitGb * 1073741824; // 1 GB = 1024^3 bytes
    return min(100, round(($usedBytes / $limitBytes) * 100, 1));
}
