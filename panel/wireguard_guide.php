<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = __('page_wg_guide');

$s = getAllSettings();
$wgInterface = $s['wg_interface'] ?? 'wireguard1';
$wgPort = $s['wg_listen_port'] ?? '13231';
$wgSubnet = $s['wg_subnet'] ?? '10.0.0.0/24';
$wgServerIp = $s['wg_server_ip'] ?? '10.0.0.1';
$wgEndpoint = $s['wg_endpoint'] ?? 'YOUR_PUBLIC_IP';
$wgDns = $s['wg_dns'] ?? '8.8.8.8';
$serverPublicKey = $s['wg_server_public_key'] ?? 'SERVER_PUBLIC_KEY';

$subnetBase = preg_replace('/\/\d+$/', '', $wgSubnet);
$clientExampleIp = '10.0.0.2';
if (preg_match('/^(\d+\.\d+\.\d+)\.\d+$/', $subnetBase, $match)) {
    $clientExampleIp = $match[1] . '.2';
}

include __DIR__ . '/../templates/header.php';
?>

<div class="alert alert-info border-0 shadow-sm">
    <i class="fas fa-circle-info me-2"></i>
    این صفحه یک راهنمای سریع برای راه‌اندازی اولیه WireGuard روی میکروتیک است. مقادیر نمونه از تنظیمات فعلی پنل شما پر شده‌اند تا سریع‌تر پیش بروید.
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-list-check me-2"></i>پیش‌نیازها</h6>
            </div>
            <div class="card-body">
                <ul class="mb-0 small">
                    <li class="mb-2">RouterOS نسخه 7 یا بالاتر</li>
                    <li class="mb-2">فعال بودن API روی پورت 8728</li>
                    <li class="mb-2">داشتن IP عمومی یا Port Forward برای UDP</li>
                    <li class="mb-2">تعریف Subnet جدا برای VPN</li>
                    <li>ثبت صحیح تنظیمات در صفحه تنظیمات پنل</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-gear me-2"></i>مقادیر فعلی پنل</h6>
            </div>
            <div class="card-body small">
                <div class="d-flex justify-content-between border-bottom py-2"><span>اینترفیس</span><strong dir="ltr"><?= e($wgInterface) ?></strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span>پورت</span><strong dir="ltr"><?= e($wgPort) ?></strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span>Subnet</span><strong dir="ltr"><?= e($wgSubnet) ?></strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span>IP سرور</span><strong dir="ltr"><?= e($wgServerIp) ?></strong></div>
                <div class="d-flex justify-content-between py-2"><span>DNS</span><strong dir="ltr"><?= e($wgDns) ?></strong></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-bolt me-2"></i>خروجی نهایی</h6>
            </div>
            <div class="card-body">
                <ul class="mb-0 small">
                    <li class="mb-2">ایجاد اینترفیس WireGuard روی روتر</li>
                    <li class="mb-2">باز شدن پورت UDP برای کلاینت‌ها</li>
                    <li class="mb-2">امکان ساخت Peer از داخل پنل</li>
                    <li class="mb-2">دریافت فایل کانفیگ برای کاربر</li>
                    <li>نمایش Handshake و RX/TX در پنل</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-1 me-2"></i>مرحله 1: ساخت اینترفیس WireGuard</h6>
    </div>
    <div class="card-body">
        <p class="text-muted">در ترمینال میکروتیک این دستور را اجرا کنید:</p>
        <pre class="bg-dark text-light rounded p-3 mb-3" dir="ltr"><code>/interface wireguard add name=<?= e($wgInterface) ?> listen-port=<?= e($wgPort) ?> mtu=1420</code></pre>
        <p class="text-muted mb-2">برای مشاهده کلید عمومی اینترفیس:</p>
        <pre class="bg-dark text-light rounded p-3 mb-0" dir="ltr"><code>/interface wireguard print detail</code></pre>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-2 me-2"></i>مرحله 2: اختصاص IP به اینترفیس</h6>
    </div>
    <div class="card-body">
        <p class="text-muted">یک IP از Subnet VPN به خود روتر بدهید:</p>
        <pre class="bg-dark text-light rounded p-3 mb-3" dir="ltr"><code>/ip address add address=<?= e($wgServerIp) ?>/24 interface=<?= e($wgInterface) ?> comment="WireGuard subnet"</code></pre>
        <div class="alert alert-warning mb-0">
            اگر Subnet شما مثلا <strong dir="ltr"><?= e($wgSubnet) ?></strong> است، مطمئن شوید IP سرور داخل همین بازه قرار دارد و با LAN شما تداخل ندارد.
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-3 me-2"></i>مرحله 3: باز کردن پورت و NAT</h6>
    </div>
    <div class="card-body">
        <p class="text-muted mb-2">اجازه دادن به ترافیک WireGuard روی UDP:</p>
        <pre class="bg-dark text-light rounded p-3 mb-3" dir="ltr"><code>/ip firewall filter add chain=input action=accept protocol=udp dst-port=<?= e($wgPort) ?> comment="Allow WireGuard"</code></pre>
        <p class="text-muted mb-2">اگر می‌خواهید کلاینت‌ها از اینترنت روتر استفاده کنند:</p>
        <pre class="bg-dark text-light rounded p-3 mb-0" dir="ltr"><code>/ip firewall nat add chain=srcnat action=masquerade src-address=<?= e($wgSubnet) ?> out-interface-list=WAN comment="WG NAT"</code></pre>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-4 me-2"></i>مرحله 4: ثبت تنظیمات در پنل</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>فیلد</th>
                        <th>مقدار پیشنهادی</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>نام اینترفیس</td>
                        <td dir="ltr"><?= e($wgInterface) ?></td>
                    </tr>
                    <tr>
                        <td>Public Key سرور</td>
                        <td dir="ltr"><?= e($serverPublicKey) ?></td>
                    </tr>
                    <tr>
                        <td>Endpoint</td>
                        <td dir="ltr"><?= e($wgEndpoint) ?>:<?= e($wgPort) ?></td>
                    </tr>
                    <tr>
                        <td>DNS</td>
                        <td dir="ltr"><?= e($wgDns) ?></td>
                    </tr>
                    <tr>
                        <td>Subnet</td>
                        <td dir="ltr"><?= e($wgSubnet) ?></td>
                    </tr>
                    <tr>
                        <td>IP سرور</td>
                        <td dir="ltr"><?= e($wgServerIp) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <a href="settings" class="btn btn-primary">
                <i class="fas fa-gear me-1"></i> رفتن به تنظیمات
            </a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-5 me-2"></i>مرحله 5: ساخت اولین کاربر</h6>
    </div>
    <div class="card-body">
        <ol class="mb-3">
            <li>از منوی پنل وارد صفحه افزودن کاربر شوید.</li>
            <li>برای کاربر یک IP مثل <strong dir="ltr"><?= e($clientExampleIp) ?>/32</strong> ثبت کنید.</li>
            <li>در صورت نیاز محدودیت سرعت، حجم یا تاریخ انقضا تعریف کنید.</li>
            <li>کاربر را ذخیره کنید و فایل کانفیگ را دانلود بگیرید.</li>
        </ol>
        <p class="text-muted mb-0">پنل به صورت خودکار Peer را روی میکروتیک می‌سازد و فایل `.conf` آماده را به شما می‌دهد.</p>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-6 me-2"></i>مرحله 6: نمونه کانفیگ کلاینت</h6>
    </div>
    <div class="card-body">
        <pre class="bg-dark text-light rounded p-3 mb-0" dir="ltr"><code>[Interface]
PrivateKey = CLIENT_PRIVATE_KEY
Address = <?= e($clientExampleIp) ?>/32
DNS = <?= e($wgDns) ?>

[Peer]
PublicKey = <?= e($serverPublicKey) ?>
AllowedIPs = 0.0.0.0/0
Endpoint = <?= e($wgEndpoint) ?>:<?= e($wgPort) ?>
PersistentKeepalive = 25</code></pre>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-check-double me-2"></i>بررسی نهایی</h6>
    </div>
    <div class="card-body">
        <p class="text-muted mb-2">برای اطمینان از اتصال صحیح، روی میکروتیک این دستور را بزنید:</p>
        <pre class="bg-dark text-light rounded p-3 mb-3" dir="ltr"><code>/interface wireguard peers print detail</code></pre>
        <ul class="mb-0">
            <li class="mb-2">`last-handshake` باید بعد از اتصال کلاینت مقدار بگیرد.</li>
            <li class="mb-2">RX/TX باید با عبور ترافیک افزایش پیدا کند.</li>
            <li class="mb-2">اگر روتر پشت مودم است، Port Forward برای UDP `<?= e($wgPort) ?>` را فراموش نکنید.</li>
            <li>اگر IP عمومی شما ثابت نیست، از دامنه یا DDNS در Endpoint استفاده کنید.</li>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>