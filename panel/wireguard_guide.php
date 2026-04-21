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
    <?= __('guide_intro') ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-list-check me-2"></i><?= __('guide_prereqs') ?></h6>
            </div>
            <div class="card-body">
                <ul class="mb-0 small">
                    <li class="mb-2"><?= __('guide_prereq_1') ?></li>
                    <li class="mb-2"><?= __('guide_prereq_2') ?></li>
                    <li class="mb-2"><?= __('guide_prereq_3') ?></li>
                    <li class="mb-2"><?= __('guide_prereq_4') ?></li>
                    <li><?= __('guide_prereq_5') ?></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-gear me-2"></i><?= __('guide_current_vals') ?></h6>
            </div>
            <div class="card-body small">
                <div class="d-flex justify-content-between border-bottom py-2"><span><?= __('guide_val_interface') ?></span><strong dir="ltr"><?= e($wgInterface) ?></strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span><?= __('guide_val_port') ?></span><strong dir="ltr"><?= e($wgPort) ?></strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span>Subnet</span><strong dir="ltr"><?= e($wgSubnet) ?></strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span><?= __('guide_val_server_ip') ?></span><strong dir="ltr"><?= e($wgServerIp) ?></strong></div>
                <div class="d-flex justify-content-between py-2"><span>DNS</span><strong dir="ltr"><?= e($wgDns) ?></strong></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-bolt me-2"></i><?= __('guide_outcome') ?></h6>
            </div>
            <div class="card-body">
                <ul class="mb-0 small">
                    <li class="mb-2"><?= __('guide_outcome_1') ?></li>
                    <li class="mb-2"><?= __('guide_outcome_2') ?></li>
                    <li class="mb-2"><?= __('guide_outcome_3') ?></li>
                    <li class="mb-2"><?= __('guide_outcome_4') ?></li>
                    <li><?= __('guide_outcome_5') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-1 me-2"></i><?= __('guide_step1_title') ?></h6>
    </div>
    <div class="card-body">
        <p class="text-muted"><?= __('guide_step1_desc') ?></p>
        <pre class="bg-dark text-light rounded p-3 mb-3" dir="ltr"><code>/interface wireguard add name=<?= e($wgInterface) ?> listen-port=<?= e($wgPort) ?> mtu=1420</code></pre>
        <p class="text-muted mb-2"><?= __('guide_step1_pubkey') ?></p>
        <pre class="bg-dark text-light rounded p-3 mb-0" dir="ltr"><code>/interface wireguard print detail</code></pre>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-2 me-2"></i><?= __('guide_step2_title') ?></h6>
    </div>
    <div class="card-body">
        <p class="text-muted"><?= __('guide_step2_desc') ?></p>
        <pre class="bg-dark text-light rounded p-3 mb-3" dir="ltr"><code>/ip address add address=<?= e($wgServerIp) ?>/24 interface=<?= e($wgInterface) ?> comment="WireGuard subnet"</code></pre>
        <div class="alert alert-warning mb-0">
            <?= __('guide_step2_warning', ['subnet' => e($wgSubnet)]) ?>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-3 me-2"></i><?= __('guide_step3_title') ?></h6>
    </div>
    <div class="card-body">
        <p class="text-muted mb-2"><?= __('guide_step3_fw') ?></p>
        <pre class="bg-dark text-light rounded p-3 mb-3" dir="ltr"><code>/ip firewall filter add chain=input action=accept protocol=udp dst-port=<?= e($wgPort) ?> comment="Allow WireGuard"</code></pre>
        <p class="text-muted mb-2"><?= __('guide_step3_nat') ?></p>
        <pre class="bg-dark text-light rounded p-3 mb-0" dir="ltr"><code>/ip firewall nat add chain=srcnat action=masquerade src-address=<?= e($wgSubnet) ?> out-interface-list=WAN comment="WG NAT"</code></pre>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-4 me-2"></i><?= __('guide_step4_title') ?></h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= __('guide_step4_field') ?></th>
                        <th><?= __('guide_step4_value') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= __('guide_step4_iface') ?></td>
                        <td dir="ltr"><?= e($wgInterface) ?></td>
                    </tr>
                    <tr>
                        <td><?= __('guide_step4_pubkey') ?></td>
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
                        <td><?= __('guide_val_server_ip') ?></td>
                        <td dir="ltr"><?= e($wgServerIp) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <a href="settings" class="btn btn-primary">
                <i class="fas fa-gear me-1"></i> <?= __('guide_step4_goto') ?>
            </a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-5 me-2"></i><?= __('guide_step5_title') ?></h6>
    </div>
    <div class="card-body">
        <ol class="mb-3">
            <li><?= __('guide_step5_1') ?></li>
            <li><?= __('guide_step5_2', ['ip' => e($clientExampleIp)]) ?></li>
            <li><?= __('guide_step5_3') ?></li>
            <li><?= __('guide_step5_4') ?></li>
        </ol>
        <p class="text-muted mb-0"><?= __('guide_step5_note') ?></p>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-6 me-2"></i><?= __('guide_step6_title') ?></h6>
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
        <h6 class="mb-0 fw-semibold"><i class="fas fa-check-double me-2"></i><?= __('guide_final_check') ?></h6>
    </div>
    <div class="card-body">
        <p class="text-muted mb-2"><?= __('guide_final_desc') ?></p>
        <pre class="bg-dark text-light rounded p-3 mb-3" dir="ltr"><code>/interface wireguard peers print detail</code></pre>
        <ul class="mb-0">
            <li class="mb-2"><?= __('guide_final_1') ?></li>
            <li class="mb-2"><?= __('guide_final_2') ?></li>
            <li class="mb-2"><?= __('guide_final_3', ['port' => e($wgPort)]) ?></li>
            <li><?= __('guide_final_4') ?></li>
        </ul>
    </div>
</div>


<?php include __DIR__ . '/../templates/footer.php'; ?>
