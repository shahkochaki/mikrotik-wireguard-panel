<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in → go to dashboard
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = postStr('username') ?? trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'نام کاربری و رمز عبور را وارد کنید.';
    } elseif (!attemptLogin($username, $password)) {
        $error = 'نام کاربری یا رمز عبور اشتباه است.';
    } else {
        header('Location: dashboard.php');
        exit;
    }
}

// helper used in template only
function postStr(string $key): string
{
    return isset($_POST[$key]) ? htmlspecialchars(trim($_POST[$key]), ENT_QUOTES) : '';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ورود — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="login-page">

    <div class="login-wrapper d-flex align-items-center justify-content-center min-vh-100">
        <div class="card login-card shadow-lg">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <div class="login-logo mb-3">
                        <i class="fas fa-shield-halved fa-3x text-primary"></i>
                    </div>
                    <h3 class="fw-bold"><?= APP_NAME ?></h3>
                    <p class="text-muted small">مدیریت WireGuard میکروتیک</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" novalidate>
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">نام کاربری</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" name="username" class="form-control"
                                value="<?= postStr('username') ?>"
                                placeholder="admin" autocomplete="username" required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">رمز عبور</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" class="form-control"
                                placeholder="••••••••" autocomplete="current-password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="fas fa-right-to-bracket me-2"></i>ورود به پنل
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>