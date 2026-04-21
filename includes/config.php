<?php
// ====================================================
// WireGuard Panel - Main Configuration
// ====================================================

// --- Application ---
define('APP_NAME',    'WireGuard Panel');
define('APP_VERSION', '1.0.0');

// --- Database (edit these) ---
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'wireguard_panel');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- Session ---
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// --- Base path ---
define('BASE_PATH', dirname(__DIR__));

// Calculate BASE_URL so it always points to the PROJECT ROOT,
// regardless of which sub-directory (administrator/, etc.) the calling script lives in.
$_bScheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$_bHost   = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Try DOCUMENT_ROOT first — most reliable method
$_bDocR   = rtrim(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$_bAppR   = rtrim(str_replace('\\', '/', (string) realpath(BASE_PATH)), '/');
if ($_bDocR !== '' && str_starts_with($_bAppR, $_bDocR)) {
    $_bPath = substr($_bAppR, strlen($_bDocR)) ?: '/';
} else {
    // Fallback: scripts are exactly 1 level deep (administrator/) from project root
    $_bPath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
}
define('BASE_URL', $_bScheme . '://' . $_bHost . $_bPath);
unset($_bScheme, $_bHost, $_bDocR, $_bAppR, $_bPath);

// --- Timezone ---
date_default_timezone_set('Asia/Tehran');

// --- i18n ---
require_once __DIR__ . '/lang.php';
loadLang();
