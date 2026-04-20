<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mikrotik.php';

requireLogin();

$pageTitle = 'داشبورد';

// Stats
$totalUsers   = countUsers();
$activeUsers  = countActiveUsers();
$expiredUsers = countExpiredUsers();

// Router identity is fetched via AJAX on page load (non-blocking)

// Recent users (last 5)
$recentUsers = dbQuery(
    'SELECT * FROM wg_users ORDER BY created_at DESC LIMIT 5'
)->fetchAll();

include __DIR__ . '/templates/header.php';
?>

<?= flashHtml() ?>

<!-- Stat cards -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-primary-subtle text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stat-value"><?= $totalUsers ?></div>
                        <div class="stat-label">کل کاربران</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-success-subtle text-success">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stat-value"><?= $activeUsers ?></div>
                        <div class="stat-label">کاربران فعال</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-danger-subtle text-danger">
                        <i class="fas fa-clock-rotate-left"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stat-value"><?= $expiredUsers ?></div>
                        <div class="stat-label">منقضی شده</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-0 shadow-sm" id="routerCard">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-info-subtle text-info">
                        <i class="fas fa-router"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stat-value" id="routerIdentity" style="font-size:.95rem">
                            <span class="spinner-border spinner-border-sm text-secondary"></span>
                        </div>
                        <div class="stat-label">روتر میکروتیک</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Fetch router identity asynchronously so it never blocks the page
    (function() {
        fetch('ajax_test_router.php', {
                method: 'POST'
            })
            .then(r => r.json())
            .then(data => {
                const el = document.getElementById('routerIdentity');
                const card = document.getElementById('routerCard');
                const last = data.steps?.[data.steps.length - 1];
                if (data.success) {
                    // identity is in step 3 detail ("نام روتر: X")
                    const identityStep = data.steps?.find(s => s.label.includes('اطلاعات'));
                    const name = identityStep?.detail?.replace('نام روتر: ', '') ?? 'متصل';
                    el.textContent = name;
                } else {
                    el.innerHTML = '<span class="text-danger" style="font-size:.8rem"><i class="fas fa-triangle-exclamation me-1"></i>قطع</span>';
                    card.querySelector('.stat-icon').classList.replace('bg-info-subtle', 'bg-danger-subtle');
                    card.querySelector('.stat-icon').classList.replace('text-info', 'text-danger');
                    if (last) el.title = last.detail;
                }
            })
            .catch(() => {
                document.getElementById('routerIdentity').innerHTML =
                    '<span class="text-secondary" style="font-size:.8rem">نامشخص</span>';
            });
    })();
</script>

<!-- Recent users table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-list me-2"></i>آخرین کاربران اضافه شده</h6>
        <a href="users.php" class="btn btn-sm btn-outline-primary">مشاهده همه</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>نام</th>
                        <th>آدرس IP</th>
                        <th>سرعت</th>
                        <th>انقضا</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentUsers)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                کاربری ثبت نشده است.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentUsers as $u): ?>
                            <?php
                            $expired  = $u['expiry_date'] && strtotime($u['expiry_date']) < time();
                            $rowClass = $expired ? 'table-danger' : '';
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td><?= e($u['name']) ?></td>
                                <td><code><?= e($u['allowed_address']) ?></code></td>
                                <td>
                                    <small>
                                        <i class="fas fa-arrow-down text-success"></i> <?= e($u['download_speed']) ?>
                                        / <i class="fas fa-arrow-up text-primary"></i> <?= e($u['upload_speed']) ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($u['expiry_date']): ?>
                                        <?= $expired
                                            ? '<span class="badge bg-danger">منقضی</span>'
                                            : '<span class="badge bg-success">' . e(date('Y-m-d', strtotime($u['expiry_date']))) . '</span>'
                                        ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">بدون محدودیت</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $u['is_active']
                                        ? '<span class="badge bg-success">فعال</span>'
                                        : '<span class="badge bg-secondary">غیرفعال</span>'
                                    ?>
                                </td>
                                <td>
                                    <a href="user_edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>