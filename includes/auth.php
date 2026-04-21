<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Check if the current visitor is logged in.
 * Redirect to login page if not.
 */
function requireLogin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . BASE_URL . '/panel/');
        exit;
    }
}

/**
 * Attempt to log the admin in.
 * Returns true on success, false on failure.
 */
function attemptLogin(string $username, string $password): bool
{
    $row = dbQuery(
        'SELECT id, password FROM admins WHERE username = ? LIMIT 1',
        [trim($username)]
    )->fetch();

    if (!$row || !password_verify($password, $row['password'])) {
        return false;
    }

    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['admin_id']       = $row['id'];
    $_SESSION['admin_username'] = trim($username);
    $_SESSION['csrf_token']     = bin2hex(random_bytes(32));

    return true;
}

/**
 * Log out the current admin.
 */
function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $p['path'],
            $p['domain'],
            $p['secure'],
            $p['httponly']
        );
    }
    session_destroy();
}

/**
 * Return the stored CSRF token, generating one if needed.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token.
 */
function validateCsrf(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';
    return !empty($submitted)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}

/**
 * Return a hidden CSRF input field.
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}
