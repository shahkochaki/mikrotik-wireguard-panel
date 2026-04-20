<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$pageTitle = 'مدیریت کاربران';

// Handle bulk expiry check
$expiredUpdated = 0;
if (isset($_GET['check_expiry']) && $_GET['check_expiry'] === '1') {
    require_once __DIR__ . '/includes/mikrotik.php';
    $expired = dbQuery(
        'SELECT id, mikrotik_peer_id, mikrotik_queue_id FROM wg_users
         WHERE is_active = 1 AND expiry_date IS NOT NULL AND expiry_date < NOW()'
    )->fetchAll();

    foreach ($expired as $eu) {
        try {
            if ($eu['mikrotik_peer_id']) {
                mtSetPeerDisabled($eu['mikrotik_peer_id'], true);
            }
            if ($eu['mikrotik_queue_id']) {
                mtSetQueueDisabled($eu['mikrotik_queue_id'], true);
            }
            dbQuery('UPDATE wg_users SET is_active = 0 WHERE id = ?', [$eu['id']]);
            $expiredUpdated++;
        } catch (Throwable $e) { /* ignore per-user errors */
        }
    }
    flashSet('info', "بررسی انقضا انجام شد. {$expiredUpdated} کاربر غیرفعال شد.");
    header('Location: users.php');
    exit;
}

$users = getAllUsers();

include __DIR__ . '/templates/header.php';
?>

<?= flashHtml() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="badge bg-secondary me-2"><?= count($users) ?> کاربر</span>
    </div>
    <div class="d-flex gap-2">
        <a href="users.php?check_expiry=1" class="btn btn-outline-warning btn-sm">
            <i class="fas fa-clock me-1"></i>بررسی انقضا
        </a>
        <a href="user_add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus me-1"></i>افزودن کاربر
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="usersTable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>نام / یوزرنیم</th>
                        <th>آدرس IP</th>
                        <th>دانلود</th>
                        <th>آپلود</th>
                        <th>تاریخ انقضا</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="fas fa-users fa-2x d-block mb-2"></i>
                                هیچ کاربری ثبت نشده است.
                                <a href="user_add.php" class="d-block mt-2">افزودن اولین کاربر</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $i => $u):
                            $expired = $u['expiry_date'] && strtotime($u['expiry_date']) < time();
                        ?>
                            <tr class="<?= $expired ? 'table-danger' : '' ?>">
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <strong><?= e($u['name']) ?></strong>
                                    <br><small class="text-muted"><?= e($u['username']) ?></small>
                                </td>
                                <td><code><?= e($u['allowed_address']) ?></code></td>
                                <td><i class="fas fa-arrow-down text-success"></i> <?= e($u['download_speed']) ?></td>
                                <td><i class="fas fa-arrow-up text-primary"></i> <?= e($u['upload_speed']) ?></td>
                                <td>
                                    <?php if ($u['expiry_date']): ?>
                                        <?= $expired
                                            ? '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i>منقضی</span>'
                                            : '<span class="badge bg-success">' . e(date('Y-m-d', strtotime($u['expiry_date']))) . '</span>'
                                        ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">نامحدود</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $u['is_active']
                                        ? '<span class="badge bg-success">فعال</span>'
                                        : '<span class="badge bg-secondary">غیرفعال</span>'
                                    ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <!-- Download config -->
                                        <a href="user_config.php?id=<?= $u['id'] ?>"
                                            class="btn btn-outline-info" title="دانلود کانفیگ">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <!-- Edit -->
                                        <a href="user_edit.php?id=<?= $u['id'] ?>"
                                            class="btn btn-outline-secondary" title="ویرایش">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <!-- Toggle active -->
                                        <a href="user_toggle.php?id=<?= $u['id'] ?>"
                                            class="btn <?= $u['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                            title="<?= $u['is_active'] ? 'غیرفعال کردن' : 'فعال کردن' ?>">
                                            <i class="fas <?= $u['is_active'] ? 'fa-pause' : 'fa-play' ?>"></i>
                                        </a>
                                        <!-- Delete -->
                                        <a href="user_delete.php?id=<?= $u['id'] ?>"
                                            class="btn btn-outline-danger confirm-delete" title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
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