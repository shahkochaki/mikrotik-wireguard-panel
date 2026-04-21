<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?><?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
</head>

<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-shield-halved me-2"></i><?= APP_NAME ?>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-gauge-high"></i> داشبورد
            </a>
            <a href="users.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['users.php', 'user_add.php', 'user_edit.php']) ? 'active' : '' ?>">
                <i class="fas fa-users"></i> مدیریت کاربران
            </a>
            <a href="user_add.php">
                <i class="fas fa-user-plus"></i> افزودن کاربر
            </a>
            <a href="user_import.php" class="<?= basename($_SERVER['PHP_SELF']) === 'user_import.php' ? 'active' : '' ?>">
                <i class="fas fa-file-import"></i> ایمپورت از روتر
            </a>
            <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>">
                <i class="fas fa-gear"></i> تنظیمات
            </a>
            <div class="sidebar-divider"></div>
            <a href="logout.php" class="text-danger">
                <i class="fas fa-right-from-bracket"></i> خروج
            </a>
        </nav>
        <div class="sidebar-footer">
            <small class="text-muted">
                <i class="fas fa-user-shield me-1"></i><?= e($_SESSION['admin_username'] ?? 'admin') ?>
            </small>
        </div>
    </div>

    <!-- Main content -->
    <div class="main-content">
        <!-- Top bar -->
        <div class="topbar d-flex align-items-center justify-content-between">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="mb-0 fw-semibold"><?= isset($pageTitle) ? e($pageTitle) : '' ?></h5>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success"><i class="fas fa-circle fa-xs me-1"></i>آنلاین</span>
                <a href="logout.php" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-right-from-bracket"></i>
                </a>
            </div>
        </div>

        <div class="content-body">