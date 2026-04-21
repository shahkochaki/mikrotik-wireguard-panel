<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mikrotik.php';

requireLogin();

$id   = getInt('id');
$user = getUserById($id);
if (!$user) {
    flashSet('danger', 'کاربر یافت نشد.');
    header('Location: users');
    exit;
}

$pageTitle = 'ویرایش کاربر: ' . $user['name'];
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) die('درخواست نامعتبر');

    $name          = postStr('name');
    $downloadSpeed = postStr('download_speed', '10M');
    $uploadSpeed   = postStr('upload_speed',   '10M');
    $expiryDate    = postStr('expiry_date');
    $notes         = postStr('notes');
    $isActive      = isset($_POST['is_active']) ? 1 : 0;
    $dataLimitRaw  = postStr('data_limit_gb');
    $dataLimitGb   = is_numeric($dataLimitRaw) && (float)$dataLimitRaw > 0 ? (float)$dataLimitRaw : null;

    if (!$name) $errors[] = 'نام نمایشی الزامی است.';

    $expiry = null;
    if ($expiryDate) {
        $expiry = date('Y-m-d 23:59:59', strtotime($expiryDate));
        if (!$expiry) $errors[] = 'تاریخ انقضای نامعتبر.';
    }

    if (empty($errors)) {
        try {
            // Update queue speed on router
            if ($user['mikrotik_queue_id']) {
                if ($downloadSpeed !== $user['download_speed'] || $uploadSpeed !== $user['upload_speed']) {
                    mtUpdateQueue($user['mikrotik_queue_id'], $downloadSpeed, $uploadSpeed);
                }
            }

            // Enable/disable peer and queue based on active state
            if ((int)$user['is_active'] !== $isActive) {
                if ($user['mikrotik_peer_id']) {
                    mtSetPeerDisabled($user['mikrotik_peer_id'], !$isActive);
                }
                if ($user['mikrotik_queue_id']) {
                    mtSetQueueDisabled($user['mikrotik_queue_id'], !$isActive);
                }
            }

            dbQuery(
                'UPDATE wg_users SET name=?, download_speed=?, upload_speed=?,
                 expiry_date=?, is_active=?, notes=?, data_limit_gb=?, updated_at=NOW()
                 WHERE id=?',
                [$name, $downloadSpeed, $uploadSpeed, $expiry, $isActive, $notes, $dataLimitGb, $id]
            );

            // Reload
            $user = getUserById($id);
            flashSet('success', 'اطلاعات کاربر با موفقیت به‌روزرسانی شد.');
        } catch (Throwable $e) {
            $errors[] = 'خطا در ارتباط با میکروتیک: ' . $e->getMessage();
        }
    }
}

$speedOptions = speedOptions();
include __DIR__ . '/../templates/header.php';
?>

<?= flashHtml() ?>
<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Edit form -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-pen me-2"></i>ویرایش کاربر</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="" novalidate>
                    <?= csrfField() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">نام نمایشی <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                value="<?= e($user['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">نام کاربری</label>
                            <input type="text" class="form-control" dir="ltr"
                                value="<?= e($user['username']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-arrow-down text-success me-1"></i>سرعت دانلود
                            </label>
                            <select name="download_speed" class="form-select">
                                <?php foreach ($speedOptions as $val => $label): ?>
                                    <option value="<?= $val ?>"
                                        <?= ($user['download_speed'] === $val ? 'selected' : '') ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-arrow-up text-primary me-1"></i>سرعت آپلود
                            </label>
                            <select name="upload_speed" class="form-select">
                                <?php foreach ($speedOptions as $val => $label): ?>
                                    <option value="<?= $val ?>"
                                        <?= ($user['upload_speed'] === $val ? 'selected' : '') ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-calendar me-1"></i>تاریخ انقضا</label>
                            <input type="date" name="expiry_date" class="form-control" dir="ltr"
                                value="<?= $user['expiry_date'] ? date('Y-m-d', strtotime($user['expiry_date'])) : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-database me-1"></i>حجم مجاز (GB)
                            </label>
                            <input type="number" name="data_limit_gb" class="form-control" dir="ltr"
                                min="0.1" step="0.1"
                                value="<?= e($user['data_limit_gb'] ?? '') ?>">
                            <div class="form-text">خالی = بدون محدودیت حجمی</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">یادداشت</label>
                            <input type="text" name="notes" class="form-control"
                                value="<?= e($user['notes'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active"
                                    id="isActive" <?= $user['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActive">کاربر فعال باشد</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>ذخیره تغییرات
                        </button>
                        <a href="users" class="btn btn-outline-secondary">بازگشت</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Info card -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-info-circle me-2"></i>اطلاعات فنی</h6>
            </div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5">آدرس IP</dt>
                    <dd class="col-7"><code><?= e($user['allowed_address']) ?></code></dd>
                    <dt class="col-5">Public Key</dt>
                    <dd class="col-7 text-break"><code><?= e(substr($user['public_key'] ?? '', 0, 20)) ?>…</code></dd>
                    <dt class="col-5">Peer ID</dt>
                    <dd class="col-7"><code><?= e($user['mikrotik_peer_id'] ?? '—') ?></code></dd>
                    <dt class="col-5">Queue ID</dt>
                    <dd class="col-7"><code><?= e($user['mikrotik_queue_id'] ?? '—') ?></code></dd>
                    <?php
                    $rx    = (int)($user['rx_bytes'] ?? 0);
                    $tx    = (int)($user['tx_bytes'] ?? 0);
                    $total = $rx + $tx;
                    $limitGb = isset($user['data_limit_gb']) && $user['data_limit_gb'] !== null
                        ? (float)$user['data_limit_gb'] : null;
                    $pct = dataUsagePercent($total, $limitGb);
                    ?>
                    <dt class="col-5">دانلود</dt>
                    <dd class="col-7"><?= formatBytes($rx) ?></dd>
                    <dt class="col-5">آپلود</dt>
                    <dd class="col-7"><?= formatBytes($tx) ?></dd>
                    <dt class="col-5">کل مصرف</dt>
                    <dd class="col-7">
                        <?= formatBytes($total) ?>
                        <?php if ($limitGb !== null): ?>
                            / <?= $limitGb ?> GB
                            <div class="progress mt-1" style="height:5px">
                                <div class="progress-bar <?= $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success') ?>"
                                    style="width:<?= $pct ?>%"></div>
                            </div>
                        <?php endif; ?>
                    </dd>
                    <dt class="col-5">ایجاد شده</dt>
                    <dd class="col-7"><?= e($user['created_at']) ?></dd>
                </dl>
            </div>
        </div>

        <div class="d-grid gap-2">
            <a href="user_config?id=<?= $id ?>" class="btn btn-outline-info">
                <i class="fas fa-download me-2"></i>دانلود فایل کانفیگ
            </a>
            <a href="user_delete?id=<?= $id ?>" class="btn btn-outline-danger confirm-delete">
                <i class="fas fa-trash me-2"></i>حذف کاربر
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>