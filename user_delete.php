<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mikrotik.php';

requireLogin();

$id   = getInt('id');
$user = getUserById($id);
if (!$user) {
    flashSet('danger', 'کاربر یافت نشد.');
    header('Location: users.php');
    exit;
}

// Confirmation page (GET), actual delete on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) die('درخواست نامعتبر');

    $errors = [];
    try {
        if ($user['mikrotik_peer_id']) {
            mtRemovePeer($user['mikrotik_peer_id']);
        }
        if ($user['mikrotik_queue_id']) {
            mtRemoveQueue($user['mikrotik_queue_id']);
        }
    } catch (Throwable $e) {
        // Log but don't block deletion from DB
        $errors[] = 'هشدار میکروتیک: ' . $e->getMessage();
    }

    dbQuery('DELETE FROM wg_users WHERE id = ?', [$id]);
    flashSet('success', 'کاربر «' . $user['name'] . '» حذف شد.');
    header('Location: users.php');
    exit;
}

$pageTitle = 'حذف کاربر';
include __DIR__ . '/templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-danger border-0 shadow-sm">
            <div class="card-header bg-danger text-white">
                <h6 class="mb-0"><i class="fas fa-trash me-2"></i>تأیید حذف کاربر</h6>
            </div>
            <div class="card-body">
                <p>آیا از حذف کاربر زیر مطمئن هستید؟</p>
                <ul class="list-unstyled">
                    <li><strong>نام:</strong> <?= e($user['name']) ?></li>
                    <li><strong>یوزرنیم:</strong> <?= e($user['username']) ?></li>
                    <li><strong>IP:</strong> <?= e($user['allowed_address']) ?></li>
                </ul>
                <p class="text-danger small">
                    <i class="fas fa-triangle-exclamation me-1"></i>
                    Peer و Queue این کاربر از میکروتیک نیز حذف خواهد شد.
                </p>
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>بله، حذف کن
                        </button>
                        <a href="users.php" class="btn btn-outline-secondary">انصراف</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>