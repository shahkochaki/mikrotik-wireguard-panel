<?php
require_once __DIR__ . '/config.php';

/**
 * Returns a singleton PDO instance.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/**
 * Helper: run a prepared query and return the statement.
 */
function dbQuery(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch a single setting value by key.
 */
function getSetting(string $key, string $default = ''): string
{
    $row = dbQuery('SELECT setting_value FROM settings WHERE setting_key = ?', [$key])->fetch();
    return $row ? (string) $row['setting_value'] : $default;
}

/**
 * Update a single setting.
 */
function setSetting(string $key, string $value): void
{
    dbQuery(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [$key, $value]
    );
}

/**
 * Fetch all settings as key => value array.
 */
function getAllSettings(): array
{
    $rows = dbQuery('SELECT setting_key, setting_value FROM settings')->fetchAll();
    $out  = [];
    foreach ($rows as $row) {
        $out[$row['setting_key']] = $row['setting_value'];
    }
    return $out;
}
