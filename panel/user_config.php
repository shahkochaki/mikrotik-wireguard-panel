<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$id   = getInt('id');
$user = getUserById($id);

if (!$user) {
    header('HTTP/1.0 404 Not Found');
    die('کاربر یافت نشد.');
}

if (empty($user['private_key'])) {
    die('کلید خصوصی برای این کاربر ذخیره نشده است.');
}

$settings = getAllSettings();
$config   = buildClientConfig($user, $settings);

// Strip leading whitespace from heredoc indentation
$config = preg_replace('/^[ \t]+/m', '', $config);

$filename = 'wg_' . preg_replace('/[^a-z0-9_\-]/i', '_', $user['username']) . '.conf';

// Remove BOM if any included file introduced one into the output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Ensure the config string itself has no BOM
$config = ltrim($config, "\xEF\xBB\xBF");

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($config));
echo $config;
exit;
