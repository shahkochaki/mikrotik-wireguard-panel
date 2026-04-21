<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mikrotik.php';

requireLogin();

$pageTitle  = 'ایمپورت کاربران از روتر';
$errors     = [];
$imported   = 0;
$fetchError = null;

// IDs & public-keys already in DB
$existingPeerIds = dbQuery(
    'SELECT mikrotik_peer_id FROM wg_users WHERE mikrotik_peer_id IS NOT NULL'
)->fetchAll(PDO::FETCH_COLUMN);

$existingPubKeys = dbQuery(
    'SELECT public_key FROM wg_users WHERE public_key IS NOT NULL AND public_key != ""'
)->fetchAll(PDO::FETCH_COLUMN);

$existingAddrs = dbQuery(
    'SELECT allowed_address FROM wg_users'
)->fetchAll(PDO::FETCH_COLUMN);

// Fetch peers from router
$routerPeers = [];
try {
    $wgInterface = getSetting('wg_interface', 'wireguard1');
    $routerPeers = mtListPeers($wgInterface);
} catch (Throwable $e) {
    $fetchError = $e->getMessage();
}

// Separate already-imported from new
$newPeers      = [];
$alreadyInDb   = 0;
foreach ($routerPeers as $peer) {
    $inByPeerId  = in_array($peer['.id'] ?? '', $existingPeerIds, true);
    $inByPubKey  = in_array($peer['public-key'] ?? '', $existingPubKeys, true);
    if ($inByPeerId || $inByPubKey) {
        $alreadyInDb++;
    } else {
        $newPeers[] = $peer;
    }
}

// ── Handle POST (import) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) die('درخواست نامعتبر');

    $selectedIds = $_POST['selected_peers'] ?? [];
    $peerForms   = $_POST['peer'] ?? [];

    foreach ($selectedIds as $peerId) {
        $form     = $peerForms[$peerId] ?? [];
        $name     = trim($form['name']     ?? '');
        $username = trim($form['username'] ?? '');

        if (!$name || !$username) {
            $errors[] = "پیر «{$peerId}»: نام یا یوزرنیم خالی است.";
            continue;
        }
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
            $errors[] = "پیر «{$peerId}»: یوزرنیم «{$username}» نامعتبر است.";
            continue;
        }
        if (dbQuery('SELECT id FROM wg_users WHERE username = ?', [$username])->fetch()) {
            $errors[] = "یوزرنیم «{$username}» قبلاً ثبت شده است.";
            continue;
        }

        // Find the peer data from router list
        $peerData = null;
        foreach ($routerPeers as $p) {
            if (($p['.id'] ?? '') === $peerId) {
                $peerData = $p;
                break;
            }
        }
        if (!$peerData) {
            $errors[] = "پیر «{$peerId}» در روتر پیدا نشد.";
            continue;
        }

        $allowedAddr  = $peerData['allowed-address'] ?? '';
        $publicKey    = $peerData['public-key']       ?? '';
        $isDisabled   = (($peerData['disabled'] ?? 'false') === 'true');
        $downloadSpd  = trim($form['download_speed'] ?? '10M');
        $uploadSpd    = trim($form['upload_speed']   ?? '10M');
        $expiryDate   = trim($form['expiry_date']    ?? '');
        $dataLimitGb  = is_numeric($form['data_limit_gb'] ?? '') && (float)$form['data_limit_gb'] > 0
            ? (float)$form['data_limit_gb'] : null;
        $expiry       = $expiryDate ? date('Y-m-d 23:59:59', strtotime($expiryDate)) : null;

        dbQuery(
            'INSERT INTO wg_users
             (name, username, mikrotik_peer_id, public_key, allowed_address,
              download_speed, upload_speed, expiry_date, data_limit_gb, is_active, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $name,
                $username,
                $peerId,
                $publicKey,
                $allowedAddr,
                $downloadSpd,
                $uploadSpd,
                $expiry,
                $dataLimitGb,
                $isDisabled ? 0 : 1,
                'ایمپورت از روتر — کلید خصوصی موجود نیست',
            ]
        );
        $imported++;
    }

    if ($imported > 0 && empty($errors)) {
        flashSet('success', "{$imported} کاربر با موفقیت ایمپورت شد.");
        header('Location: users.php');
        exit;
    }
}

$speedOptions = speedOptions();
include __DIR__ . '/templates/header.php';
?>

<?= flashHtml() ?>

<?php if ($fetchError): ?>
    <div class="alert alert-danger">
        <i class="fas fa-triangle-exclamation me-2"></i>
        <strong>خطا در اتصال به روتر:</strong> <?= e($fetchError) ?>
    </div>
<?php endif; ?>

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

<!-- Info banner -->
<div class="alert alert-info d-flex align-items-start gap-2 mb-4">
    <i class="fas fa-circle-info mt-1 flex-shrink-0"></i>
    <div>
        <strong>نکته مهم:</strong>
        کاربران ایمپورت‌شده <strong>کلید خصوصی</strong> ندارند (روتر آن را نگه نمی‌دارد).
        بنابراین دانلود کانفیگ برای آن‌ها ممکن نخواهد بود و باید کانفیگ اصلی دستگاه کاربر را حفظ کنید.
    </div>
</div>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fw-bold fs-4 text-primary"><?= count($routerPeers) ?></div>
            <div class="text-muted small">کل پیرهای روتر</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fw-bold fs-4 text-success"><?= $alreadyInDb ?></div>
            <div class="text-muted small">قبلاً ایمپورت شده</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fw-bold fs-4 text-warning"><?= count($newPeers) ?></div>
            <div class="text-muted small">جدید (قابل ایمپورت)</div>
        </div>
    </div>
</div>

<?php if (empty($newPeers) && !$fetchError): ?>
    <div class="alert alert-success">
        <i class="fas fa-circle-check me-2"></i>
        همه پیرهای روتر قبلاً در دیتابیس ثبت شده‌اند.
    </div>
<?php elseif (!empty($newPeers)): ?>
    <form method="POST" action="">
        <?= csrfField() ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0 fw-semibold">
                <i class="fas fa-file-import me-2"></i>پیرهای جدید شناسایی‌شده
            </h6>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAll">
                    انتخاب همه
                </button>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-file-import me-1"></i>ایمپورت انتخاب‌شده‌ها
                </button>
            </div>
        </div>

        <?php foreach ($newPeers as $idx => $peer):
            $peerId  = $peer['.id']             ?? "peer_{$idx}";
            $pubKey  = $peer['public-key']      ?? '';
            $addr    = $peer['allowed-address'] ?? 'N/A';
            $disabled = (($peer['disabled'] ?? 'false') === 'true');
        ?>
            <div class="card border-0 shadow-sm mb-3 peer-card">
                <div class="card-header bg-transparent d-flex align-items-center gap-3">
                    <input type="checkbox" name="selected_peers[]" value="<?= e($peerId) ?>"
                        class="form-check-input peer-checkbox" id="peer_<?= $idx ?>">
                    <label class="fw-semibold mb-0 flex-grow-1" for="peer_<?= $idx ?>">
                        <code><?= e($addr) ?></code>
                        <?= $disabled ? '<span class="badge bg-secondary ms-2">غیرفعال</span>' : '<span class="badge bg-success ms-2">فعال</span>' ?>
                    </label>
                    <small class="text-muted d-none d-md-inline" title="Public Key" style="font-family:monospace">
                        <?= e(substr($pubKey, 0, 20)) ?>…
                    </small>
                </div>
                <div class="card-body peer-fields" style="display:none">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">نام نمایشی <span class="text-danger">*</span></label>
                            <input type="text" name="peer[<?= e($peerId) ?>][name]"
                                class="form-control" placeholder="علی محمدی">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">یوزرنیم <span class="text-danger">*</span></label>
                            <input type="text" name="peer[<?= e($peerId) ?>][username]"
                                class="form-control" dir="ltr" placeholder="ali_m">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">حجم مجاز (GB)</label>
                            <input type="number" name="peer[<?= e($peerId) ?>][data_limit_gb]"
                                class="form-control" dir="ltr" min="0.1" step="0.1"
                                placeholder="بدون محدودیت">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">سرعت دانلود</label>
                            <select name="peer[<?= e($peerId) ?>][download_speed]" class="form-select">
                                <?php foreach ($speedOptions as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $val === '10M' ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">سرعت آپلود</label>
                            <select name="peer[<?= e($peerId) ?>][upload_speed]" class="form-select">
                                <?php foreach ($speedOptions as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $val === '10M' ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">تاریخ انقضا</label>
                            <input type="date" name="peer[<?= e($peerId) ?>][expiry_date]"
                                class="form-control" dir="ltr">
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-file-import me-2"></i>ایمپورت انتخاب‌شده‌ها
            </button>
            <a href="users.php" class="btn btn-outline-secondary ms-2">انصراف</a>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle fields visibility when checkbox changes
            document.querySelectorAll('.peer-checkbox').forEach(function(cb) {
                cb.addEventListener('change', function() {
                    const fields = this.closest('.peer-card').querySelector('.peer-fields');
                    fields.style.display = this.checked ? 'block' : 'none';
                });
            });

            // Select all button
            document.getElementById('selectAll')?.addEventListener('click', function() {
                const allChecked = [...document.querySelectorAll('.peer-checkbox')].every(c => c.checked);
                document.querySelectorAll('.peer-checkbox').forEach(function(cb) {
                    cb.checked = !allChecked;
                    cb.dispatchEvent(new Event('change'));
                });
                this.textContent = allChecked ? 'انتخاب همه' : 'لغو انتخاب';
            });
        });
    </script>
<?php endif; ?>

<?php include __DIR__ . '/templates/footer.php'; ?>