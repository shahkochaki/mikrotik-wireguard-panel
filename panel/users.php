<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = __('page_users');

// Handle bulk expiry check
$expiredUpdated = 0;
if (isset($_GET['check_expiry']) && $_GET['check_expiry'] === '1') {
    require_once __DIR__ . '/../includes/mikrotik.php';
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
    flashSet('info', __('expiry_check_done', ['n' => $expiredUpdated]));
    header('Location: users');
    exit;
}

$users = getAllUsers();

include __DIR__ . '/../templates/header.php';
?>

<?= flashHtml() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="badge bg-secondary me-2"><?= count($users) ?> <?= __('lbl_username') === 'Username' ? 'users' : 'کاربر' ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="users?check_expiry=1" class="btn btn-outline-warning btn-sm">
            <i class="fas fa-clock me-1"></i><?= __('btn_check_expiry') ?>
        </a>
        <a href="user_import" class="btn btn-outline-info btn-sm">
            <i class="fas fa-file-import me-1"></i><?= __('btn_import') ?>
        </a>
        <a href="user_add" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus me-1"></i><?= __('btn_add_user') ?>
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
                        <th><?= __('col_name') ?> / <?= __('col_username') ?></th>
                        <th><?= __('col_ip') ?></th>
                        <th><?= __('col_download') ?></th>
                        <th><?= __('col_upload') ?></th>
                        <th><?= __('col_usage') ?></th>
                        <th><?= __('col_expiry') ?></th>
                        <th><?= __('col_status') ?></th>
                        <th><?= __('col_actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="fas fa-users fa-2x d-block mb-2"></i>
                                <?= __('no_users_registered') ?>
                                <a href="user_add" class="d-block mt-2"><?= __('add_first_user') ?></a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $i => $u):
                            $expired   = $u['expiry_date'] && strtotime($u['expiry_date']) < time();
                            $usedBytes = (int)($u['rx_bytes'] ?? 0) + (int)($u['tx_bytes'] ?? 0);
                            $limitGb   = isset($u['data_limit_gb']) && $u['data_limit_gb'] !== null
                                ? (float)$u['data_limit_gb'] : null;
                            $usagePct  = dataUsagePercent($usedBytes, $limitGb);
                        ?>
                            <tr class="<?= $expired ? 'table-danger' : '' ?>"
                                data-user-id="<?= $u['id'] ?>">
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <strong><?= e($u['name']) ?></strong>
                                    <br><small class="text-muted"><?= e($u['username']) ?></small>
                                </td>
                                <td><code><?= e($u['allowed_address']) ?></code></td>
                                <td><i class="fas fa-arrow-down text-success"></i> <?= e($u['download_speed']) ?></td>
                                <td><i class="fas fa-arrow-up text-primary"></i> <?= e($u['upload_speed']) ?></td>
                                <td class="usage-cell" data-user-id="<?= $u['id'] ?>">
                                    <?php if ($limitGb !== null): ?>
                                        <small><?= formatBytes($usedBytes) ?> / <?= $limitGb ?> GB</small>
                                        <div class="progress mt-1" style="height:5px" title="<?= $usagePct ?>%">
                                            <div class="progress-bar <?= $usagePct >= 90 ? 'bg-danger' : ($usagePct >= 70 ? 'bg-warning' : 'bg-success') ?>"
                                                style="width:<?= $usagePct ?>%"></div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small"><?= formatBytes($usedBytes) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['expiry_date']): ?>
                                        <?= $expired
                                    ? '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i>' . __('expired') . '</span>'
                                            : '<span class="badge bg-success">' . e(date('Y-m-d', strtotime($u['expiry_date']))) . '</span>'
                                        ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= __('unlimited') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="status-cell">
                                    <?= $u['is_active']
                                        ? '<span class="badge bg-success">' . __('active') . '</span>'
                                        : '<span class="badge bg-secondary">' . __('inactive') . '</span>'
                                    ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="user_config?id=<?= $u['id'] ?>"
                                            class="btn btn-outline-info" title="<?= __('btn_download_config') ?>">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="user_edit?id=<?= $u['id'] ?>"
                                            class="btn btn-outline-secondary" title="<?= __('edit') ?>">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <button type="button"
                                            class="btn <?= $u['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?> btn-ajax-toggle"
                                            data-id="<?= $u['id'] ?>"
                                            data-active="<?= $u['is_active'] ?>"
                                            title="<?= $u['is_active'] ? __('btn_disable') : __('btn_enable') ?>">
                                            <i class="fas <?= $u['is_active'] ? 'fa-pause' : 'fa-play' ?>"></i>
                                        </button>
                                        <button type="button"
                                            class="btn btn-outline-danger btn-ajax-delete"
                                            data-id="<?= $u['id'] ?>"
                                            data-name="<?= e($u['name']) ?>"
                                            title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </button>
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

<?php include __DIR__ . '/../templates/footer.php'; ?>