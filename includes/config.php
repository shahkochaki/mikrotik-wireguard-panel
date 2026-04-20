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
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'));

// --- Timezone ---
date_default_timezone_set('Asia/Tehran');
