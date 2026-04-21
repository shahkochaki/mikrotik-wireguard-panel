<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mikrotik.php';

requireLogin();

$pageTitle = __('page_dashboard');

// Stats
$totalUsers   = countUsers();
$activeUsers  = countActiveUsers();
$expiredUsers = countExpiredUsers();

// Router identity is fetched via AJAX on page load (non-blocking)

// Recent users (last 5)
$recentUsers = dbQuery(
    'SELECT * FROM wg_users ORDER BY created_at DESC LIMIT 5'
)->fetchAll();

include __DIR__ . '/../templates/header.php';
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
                        <div class="stat-label"><?= __('stat_total_users') ?></div>
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
                        <div class="stat-label"><?= __('stat_active_users') ?></div>
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
                        <div class="stat-label"><?= __('stat_expired_users') ?></div>
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
                        <div class="stat-label"><?= __('stat_router') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Fetch router identity asynchronously so it never blocks the page
    (function() {
        fetch('ajax_router_info')
            .then(r => r.json())
            .then(data => {
                const el = document.getElementById('routerIdentity');
                const card = document.getElementById('routerCard');
                if (data.success && data.data) {
                    const d = data.data;
                    el.textContent = d.identity;
                    // Populate expanded stats panel
                    document.getElementById('routerUptime').textContent = d.uptime;
                    document.getElementById('routerVersion').textContent = d.version;
                    document.getElementById('routerBoard').textContent = d.board_name;
                    document.getElementById('routerPeers').textContent = d.peer_count;
                    // CPU bar
                    const cpuBar = document.getElementById('cpuBar');
                    cpuBar.style.width = d.cpu_load + '%';
                    cpuBar.textContent = d.cpu_load + '%';
                    cpuBar.className = 'progress-bar ' +
                        (d.cpu_load > 80 ? 'bg-danger' : d.cpu_load > 50 ? 'bg-warning' : 'bg-success');
                    // Memory bar
                    const memBar = document.getElementById('memBar');
                    memBar.style.width = d.mem_percent + '%';
                    memBar.textContent = d.mem_percent + '%';
                    memBar.className = 'progress-bar ' +
                        (d.mem_percent > 80 ? 'bg-danger' : d.mem_percent > 60 ? 'bg-warning' : 'bg-info');
                    document.getElementById('routerStatsPanel').style.display = 'block';
                } else {
                    el.innerHTML = '<span class="text-danger" style="font-size:.8rem"><i class="fas fa-triangle-exclamation me-1"></i><?= __('disconnected') ?></span>';
                    card.querySelector('.stat-icon').classList.replace('bg-info-subtle', 'bg-danger-subtle');
                    card.querySelector('.stat-icon').classList.replace('text-info', 'text-danger');
                }
            })
            .catch(() => {
                document.getElementById('routerIdentity').innerHTML =
                    '<span class="text-secondary" style="font-size:.8rem"><?= __('unknown') ?></span>';
            });
    })();
</script>

<!-- Router stats panel (hidden until loaded) -->
<div id="routerStatsPanel" class="card border-0 shadow-sm mb-4" style="display:none">
    <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-microchip me-2"></i><?= __('router_info_title') ?></h6>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="text-muted small"><?= __('router_board') ?></div>
                <div class="fw-semibold" id="routerBoard">—</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small"><?= __('router_version') ?></div>
                <div class="fw-semibold" id="routerVersion">—</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small"><?= __('router_uptime') ?></div>
                <div class="fw-semibold" id="routerUptime">—</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small"><?= __('router_peers') ?></div>
                <div class="fw-semibold" id="routerPeers">—</div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="d-flex justify-content-between mb-1">
                    <small class="text-muted">CPU</small>
                    <small id="cpuPct"></small>
                </div>
                <div class="progress" style="height:18px">
                    <div id="cpuBar" class="progress-bar bg-success" style="width:0%"></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-between mb-1">
                    <small class="text-muted">حافظه</small>
                    <small id="memPct"></small>
                </div>
                <div class="progress" style="height:18px">
                    <div id="memBar" class="progress-bar bg-info" style="width:0%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent users table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-list me-2"></i><?= __('recent_users_title') ?></h6>
        <a href="users" class="btn btn-sm btn-outline-primary"><?= __('view_all') ?></a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= __('col_name') ?></th>
                        <th><?= __('col_ip') ?></th>
                        <th><?= __('col_speed') ?></th>
                        <th><?= __('col_expiry') ?></th>
                        <th><?= __('col_status') ?></th>
                        <th><?= __('col_actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentUsers)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <?= __('no_users_yet') ?>
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
                                            ? '<span class="badge bg-danger">' . __('expired') . '</span>'
                                            : '<span class="badge bg-success">' . e(date('Y-m-d', strtotime($u['expiry_date']))) . '</span>'
                                        ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= __('no_expiry') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $u['is_active']
                                        ? '<span class="badge bg-success">' . __('active') . '</span>'
                                        : '<span class="badge bg-secondary">' . __('inactive') . '</span>'
                                    ?>
                                </td>
                                <td>
                                    <a href="user_edit?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary">
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

<?php include __DIR__ . '/../templates/footer.php'; ?>