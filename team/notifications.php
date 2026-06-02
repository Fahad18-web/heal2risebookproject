<?php
$pageTitle = "Notifications - Team Member";
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('team_member');

$db = getDB();
$userId = getCurrentUserId();

// Handle Mark All Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_type = 'team_member' AND user_id = ?");
        $stmt->execute([$userId]);
        setFlashMessage('success', 'All notifications marked as read.');
        redirect('/team/notifications.php');
    }
}

// Fetch notifications
$notifications = getNotifications('team_member', $userId, 50);
$unreadMessages = getUnreadMessageCount('team_member', $userId);

require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="py-4">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 mb-4">
                <div class="dashboard-sidebar">
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?= url('/team/dashboard.php') ?>">
                            <i class="bi bi-speedometer2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="<?= url('/team/cases.php') ?>">
                            <i class="bi bi-folder"></i>My Cases
                        </a>
                        <a class="nav-link" href="<?= url('/team/chat.php') ?>">
                            <i class="bi bi-chat-dots"></i>Messages
                            <?php if ($unreadMessages > 0): ?>
                                <span class="badge bg-danger ms-2"><?= $unreadMessages ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link" href="<?= url('/team/profile.php') ?>">
                            <i class="bi bi-person"></i>My Profile
                        </a>
                        <a class="nav-link active" href="<?= url('/team/notifications.php') ?>">
                            <i class="bi bi-bell"></i>Notifications
                        </a>
                        <hr>
                        <a class="nav-link text-danger" href="<?= url('/logout.php') ?>">
                            <i class="bi bi-box-arrow-left"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">Notifications</h4>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-check2-all me-1"></i> Mark All as Read
                        </button>
                    </form>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <?php if (empty($notifications)): ?>
                            <div class="p-5 text-center text-muted">
                                <i class="bi bi-bell-slash fs-1 d-block mb-3"></i>
                                <h5>No notifications yet</h5>
                                <p>You have no notifications to show.</p>
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
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>