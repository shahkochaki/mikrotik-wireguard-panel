<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mikrotik.php';

requireLogin();

$pageTitle = 'افزودن کاربر جدید';
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        die('درخواست نامعتبر');
    }

    $name          = postStr('name');
    $username      = postStr('username');
    $downloadSpeed = postStr('download_speed', '10M');
    $uploadSpeed   = postStr('upload_speed',   '10M');
    $expiryDate    = postStr('expiry_date');
    $dataLimitRaw  = postStr('data_limit_gb');
    $dataLimitGb   = is_numeric($dataLimitRaw) && (float)$dataLimitRaw > 0 ? (float)$dataLimitRaw : null;
    $notes         = postStr('notes');

    // --- Validation ---
    if (!$name)     $errors[] = 'نام نمایشی الزامی است.';
    if (!$username) $errors[] = 'نام کاربری الزامی است.';
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
        $errors[] = 'نام کاربری فقط می‌تواند شامل حروف انگلیسی، اعداد، - و _ باشد.';
    }
    if (dbQuery('SELECT id FROM wg_users WHERE username = ?', [$username])->fetch()) {
        $errors[] = 'این نام کاربری قبلاً ثبت شده است.';
    }

    $expiry = null;
    if ($expiryDate) {
        $expiry = date('Y-m-d 23:59:59', strtotime($expiryDate));
        if (!$expiry) {
            $errors[] = 'تاریخ انقضای نامعتبر.';
        }
    }

    if (empty($errors)) {
        try {
            // 1. Generate keypair from router
            $keypair    = mtGenerateKeypair();
            $publicKey  = $keypair['public-key']  ?? '';
            $privateKey = $keypair['private-key'] ?? '';

            // 2. Find next free IP
            $allowedAddress = nextAvailableIp();
            $targetIp       = explode('/', $allowedAddress)[0];

            // 3. Add peer to router
            $wgInterface = getSetting('wg_interface', 'wireguard1');
            $peerId      = mtAddPeer($publicKey, $allowedAddress, $wgInterface);

            // 4. Add speed queue
            $queueName = 'wg_' . $username;
            $queueId   = mtAddQueue($queueName, $targetIp, $downloadSpeed, $uploadSpeed);

            // 5. Save to DB
            dbQuery(
                'INSERT INTO wg_users
                 (name, username, mikrotik_peer_id, mikrotik_queue_id, public_key, private_key,
                  allowed_address, download_speed, upload_speed, expiry_date, data_limit_gb, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $name,
                    $username,
                    $peerId,
                    $queueId,
                    $publicKey,
                    $privateKey,
                    $allowedAddress,
                    $downloadSpeed,
                    $uploadSpeed,
                    $expiry,
                    $dataLimitGb,
                    $notes,
                ]
            );

            flashSet('success', "کاربر «{$name}» با موفقیت ایجاد شد.");
            header('Location: users');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'خطا در ارتباط با میکروتیک: ' . $e->getMessage();
        }
    }
}

$speedOptions = speedOptions();
include __DIR__ . '/../templates/header.php';
?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <strong>خطاها:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="max-width:700px">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-user-plus me-2"></i>اطلاعات کاربر جدید</h6>
    </div>
    <div class="card-body">
        <form method="POST" action="" novalidate>
            <?= csrfField() ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">نام نمایشی <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                        value="<?= postStr('name') ?>" placeholder="علی محمدی" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">نام کاربری <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" dir="ltr"
                        value="<?= postStr('username') ?>" placeholder="ali_mohammadi" required>
                    <div class="form-text">فقط حروف انگلیسی، عدد، - و _</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-arrow-down text-success me-1"></i>سرعت دانلود
                    </label>
                    <select name="download_speed" class="form-select">
                        <?php foreach ($speedOptions as $val => $label): ?>
                            <option value="<?= $val ?>" <?= (postStr('download_speed', '10M') === $val ? 'selected' : '') ?>>
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
                            <option value="<?= $val ?>" <?= (postStr('upload_speed', '10M') === $val ? 'selected' : '') ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-calendar me-1"></i>تاریخ انقضا
                    </label>
                    <input type="date" name="expiry_date" class="form-control" dir="ltr"
                        value="<?= postStr('expiry_date') ?>">
                    <div class="form-text">خالی = بدون محدودیت زمانی</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-database me-1"></i>حجم مجاز (GB)
                    </label>
                    <input type="number" name="data_limit_gb" class="form-control" dir="ltr"
                        min="0.1" step="0.1"
                        value="<?= htmlspecialchars(postStr('data_limit_gb')) ?>">
                    <div class="form-text">خالی = بدون محدودیت حجمی</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">یادداشت</label>
                    <input type="text" name="notes" class="form-control"
                        value="<?= postStr('notes') ?>" placeholder="اختیاری">
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check me-2"></i>ایجاد کاربر
                </button>
                <a href="users" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>