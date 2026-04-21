<!DOCTYPE html>
<html lang="<?= currentLang() ?>" dir="<?= langDir() ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?><?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap" rel="stylesheet">
    <?php if (isRtl()): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <?php else: ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <?php endif; ?>
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
            <a href="dashboard" class="<?= basename($_SERVER['PHP_SELF'], '.php') === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-gauge-high"></i> <?= __('nav_dashboard') ?>
            </a>
            <a href="users" class="<?= in_array(basename($_SERVER['PHP_SELF'], '.php'), ['users', 'user_add', 'user_edit', 'user_import']) ? 'active' : '' ?>">
                <i class="fas fa-users"></i> <?= __('nav_users') ?>
            </a>
            <a href="user_add">
                <i class="fas fa-user-plus"></i> <?= __('nav_add_user') ?>
            </a>
            <a href="user_import" class="<?= basename($_SERVER['PHP_SELF'], '.php') === 'user_import' ? 'active' : '' ?>">
                <i class="fas fa-file-import"></i> <?= __('nav_import') ?>
            </a>
            <a href="wireguard_guide" class="<?= basename($_SERVER['PHP_SELF'], '.php') === 'wireguard_guide' ? 'active' : '' ?>">
                <i class="fas fa-circle-nodes"></i> <?= __('nav_wg_guide') ?>
            </a>
            <a href="settings" class="<?= basename($_SERVER['PHP_SELF'], '.php') === 'settings' ? 'active' : '' ?>">
                <i class="fas fa-gear"></i> <?= __('nav_settings') ?>
            </a>
            <div class="sidebar-divider"></div>
            <a href="logout" class="text-danger">
                <i class="fas fa-right-from-bracket"></i> <?= __('logout') ?>
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
                <span class="badge bg-success"><i class="fas fa-circle fa-xs me-1"></i><?= __('online') ?></span>
                <?= langSwitcherHtml() ?>
                <a href="logout" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-right-from-bracket"></i>
                </a>
            </div>
        </div>

        <div class="content-body">