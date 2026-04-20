<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mikrotik.php';

requireLogin();

$pageTitle = 'تنظیمات';
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) die('درخواست نامعتبر');

    $fields = [
        'mt_host',
        'mt_user',
        'mt_pass',
        'mt_port',
        'wg_interface',
        'wg_server_public_key',
        'wg_endpoint',
        'wg_listen_port',
        'wg_dns',
        'wg_allowed_ips',
        'wg_subnet',
        'wg_server_ip',
    ];

    foreach ($fields as $key) {
        if (isset($_POST[$key])) {
            setSetting($key, trim($_POST[$key]));
        }
    }

    // Test connection
    $identity = mtGetIdentity();
    if (str_starts_with($identity, 'Error:')) {
        $errors[] = 'تنظیمات ذخیره شد اما اتصال به میکروتیک ناموفق بود: ' . $identity;
    } else {
        flashSet('success', 'تنظیمات ذخیره شد. روتر: ' . $identity);
        header('Location: settings.php');
        exit;
    }
}

$s = getAllSettings();
include __DIR__ . '/templates/header.php';
?>

<?= flashHtml() ?>
<?php if ($errors): ?>
    <div class="alert alert-warning">
        <ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?></ul>
    </div>
<?php endif; ?>

<form method="POST" action="" novalidate>
    <?= csrfField() ?>

    <div class="row g-4">
        <!-- MikroTik connection -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h6 class="mb-0 fw-semibold"><i class="fas fa-router me-2"></i>تنظیمات میکروتیک</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">آدرس IP روتر</label>
                        <input type="text" name="mt_host" class="form-control" dir="ltr"
                            value="<?= e($s['mt_host'] ?? '192.168.88.1') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">پورت API (پیش‌فرض 8728)</label>
                        <input type="number" name="mt_port" class="form-control" dir="ltr"
                            value="<?= e($s['mt_port'] ?? '8728') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">نام کاربری میکروتیک</label>
                        <input type="text" name="mt_user" class="form-control" dir="ltr"
                            value="<?= e($s['mt_user'] ?? 'admin') ?>" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">رمز عبور میکروتیک</label>
                        <input type="password" name="mt_pass" class="form-control" dir="ltr"
                            value="<?= e($s['mt_pass'] ?? '') ?>" autocomplete="new-password">
                        <div class="form-text">خالی = بدون رمز</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- WireGuard settings -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h6 class="mb-0 fw-semibold"><i class="fas fa-shield-halved me-2"></i>تنظیمات WireGuard</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">نام اینترفیس WireGuard</label>
                        <input type="text" name="wg_interface" class="form-control" dir="ltr"
                            value="<?= e($s['wg_interface'] ?? 'wireguard1') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Public Key سرور</label>
                        <input type="text" name="wg_server_public_key" class="form-control" dir="ltr"
                            value="<?= e($s['wg_server_public_key'] ?? '') ?>"
                            placeholder="کلید عمومی اینترفیس wireguard روتر">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Endpoint (IP یا دامنه عمومی روتر)</label>
                        <input type="text" name="wg_endpoint" class="form-control" dir="ltr"
                            value="<?= e($s['wg_endpoint'] ?? '') ?>" placeholder="1.2.3.4">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">پورت WireGuard</label>
                            <input type="number" name="wg_listen_port" class="form-control" dir="ltr"
                                value="<?= e($s['wg_listen_port'] ?? '13231') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">DNS</label>
                            <input type="text" name="wg_dns" class="form-control" dir="ltr"
                                value="<?= e($s['wg_dns'] ?? '8.8.8.8') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Subnet (مثلاً 10.0.0.0/24)</label>
                            <input type="text" name="wg_subnet" class="form-control" dir="ltr"
                                value="<?= e($s['wg_subnet'] ?? '10.0.0.0/24') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">IP سرور</label>
                            <input type="text" name="wg_server_ip" class="form-control" dir="ltr"
                                value="<?= e($s['wg_server_ip'] ?? '10.0.0.1') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">AllowedIPs در کانفیگ کلاینت</label>
                            <input type="text" name="wg_allowed_ips" class="form-control" dir="ltr"
                                value="<?= e($s['wg_allowed_ips'] ?? '0.0.0.0/0') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i>ذخیره تنظیمات
        </button>
        <button type="button" class="btn btn-outline-info" id="btnTestConn">
            <i class="fas fa-plug me-2"></i>تست اتصال
        </button>
    </div>
</form>

<!-- Connection test result panel -->
<div id="connTestPanel" class="card border-0 shadow-sm mt-4" style="display:none">
    <div class="card-header bg-transparent d-flex align-items-center gap-2">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-stethoscope me-2"></i>نتیجه تست اتصال</h6>
        <div id="connTestSpinner" class="spinner-border spinner-border-sm text-primary" style="display:none"></div>
    </div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush" id="connTestSteps"></ul>
    </div>
</div>

<script>
    document.getElementById('btnTestConn').addEventListener('click', function() {
        const form = document.querySelector('form');
        const panel = document.getElementById('connTestPanel');
        const steps = document.getElementById('connTestSteps');
        const spin = document.getElementById('connTestSpinner');
        const btn = this;

        // Collect current form values (not yet saved)
        const host = form.querySelector('[name=mt_host]').value.trim();
        const port = form.querySelector('[name=mt_port]').value.trim();
        const user = form.querySelector('[name=mt_user]').value.trim();
        const pass = form.querySelector('[name=mt_pass]').value;

        panel.style.display = 'block';
        steps.innerHTML = '';
        spin.style.display = 'inline-block';
        btn.disabled = true;

        const body = new URLSearchParams({
            host,
            port,
            user,
            pass
        });

        fetch('ajax_test_router.php', {
                method: 'POST',
                body
            })
            .then(r => r.json())
            .then(data => {
                spin.style.display = 'none';
                btn.disabled = false;

                (data.steps || []).forEach(function(s) {
                    const li = document.createElement('li');
                    li.className = 'list-group-item d-flex align-items-start gap-3';

                    const icon = document.createElement('span');
                    icon.innerHTML = s.ok ?
                        '<i class="fas fa-circle-check text-success mt-1"></i>' :
                        '<i class="fas fa-circle-xmark text-danger mt-1"></i>';

                    const text = document.createElement('div');
                    text.innerHTML =
                        '<strong>' + s.label + '</strong>' +
                        '<br><small class="text-muted">' + s.detail + '</small>';

                    li.appendChild(icon);
                    li.appendChild(text);
                    steps.appendChild(li);
                });

                if (!data.steps || data.steps.length === 0) {
                    steps.innerHTML = '<li class="list-group-item text-danger">پاسخ نامعتبر از سرور</li>';
                }
            })
            .catch(function(err) {
                spin.style.display = 'none';
                btn.disabled = false;
                steps.innerHTML = '<li class="list-group-item text-danger"><i class="fas fa-triangle-exclamation me-2"></i>خطا در ارسال درخواست: ' + err.message + '</li>';
            });
    });
</script>

<!-- Cron info -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-clock me-2"></i>Cron Job بررسی انقضا</h6>
    </div>
    <div class="card-body">
        <p class="mb-2">برای غیرفعال کردن خودکار کاربران منقضی‌شده یک cron job زیر تنظیم کنید:</p>
        <code class="d-block p-3 bg-dark text-light rounded" dir="ltr">
            * * * * * php <?= e(BASE_PATH) ?>/cron_check_expiry.php >> /var/log/wg_expiry.log 2>&amp;1
        </code>
        <p class="mt-2 text-muted small">یا می‌توانید از <a href="users.php?check_expiry=1">بررسی دستی</a> استفاده کنید.</p>
    </div>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>