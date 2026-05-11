<?php
$pageTitle = "Notifications - Admin";
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('admin');

$db = getDB();
$userId = getCurrentUserId();

// Handle Mark All Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_type = 'admin' AND user_id = ?");
        $stmt->execute([$userId]);
        setFlashMessage('success', 'All notifications marked as read.');
        redirect('/admin/notifications.php');
    }
}

// Fetch notifications
$notifications = getNotifications('admin', $userId, 50);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5 mt-4" id="main-content">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= url('/admin/dashboard.php') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Notifications</li>
                </ol>
            </nav>
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h2 mb-0">Admin Notifications</h1>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-check2-all me-1"></i> Mark All as Read
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                        <div class="p-5 text-center text-muted">
                            <i class="bi bi-bell-slash fs-1 d-block mb-3"></i>
                            <h5>No notifications yet</h5>
                            <p>You're all caught up!</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $n): ?>
                                <div class="list-group-item p-4 <?= $n['is_read'] ? '' : 'bg-light' ?>">
                                    <div class="d-flex w-100 justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">
                                            <i class="bi bi-circle-fill small text-<?= $n['type'] ?: 'primary' ?> me-2"></i>
                                            <?= htmlspecialchars($n['title']) ?>
                                            <?php if (!$n['is_read']): ?>
                                                <span class="badge bg-primary ms-2 rounded-pill small">New</span>
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-muted"><i class="bi bi-clock me-1"></i><?= date('d M Y, h:i A', strtotime($n['created_at'])) ?></small>
                                    </div>
                                    <div class="mb-0 text-muted ms-4 ps-1"><?= $n['message'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>