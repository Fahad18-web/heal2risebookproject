<?php
/**
 * Heal2Rise Book - NGO Notifications
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('ngo');

$ngoId = getCurrentUserId();
$db = getDB();

// Mark all as read if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_type = 'ngo' AND user_id = ?");
        $stmt->execute([$ngoId]);
        setFlashMessage('All notifications marked as read.', 'success');
        redirect('/ngo/notifications.php');
        exit;
    }
}

// Mark single as read
if (isset($_GET['read'])) {
    $notifId = intval($_GET['read']);
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_type = 'ngo' AND user_id = ?");
    $stmt->execute([$notifId, $ngoId]);
}

// Get all notifications
$stmt = $db->prepare("
    SELECT * FROM notifications 
    WHERE user_type = 'ngo' AND user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->execute([$ngoId]);
$notifications = $stmt->fetchAll();

// Count unread
$stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_type = 'ngo' AND user_id = ? AND is_read = 0");
$stmt->execute([$ngoId]);
$unreadCount = $stmt->fetchColumn();

$pageTitle = 'Notifications';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="py-4">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 mb-4">
                <div class="dashboard-sidebar">
                    <div class="text-center mb-4">
                        <i class="bi bi-building display-4 text-primary"></i>
                        <h6 class="mt-2 mb-0"><?= htmlspecialchars($_SESSION['user_data']['name'] ?? 'NGO') ?></h6>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?= url('/ngo/dashboard.php') ?>">
                            <i class="bi bi-speedometer2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="<?= url('/ngo/profile.php') ?>">
                            <i class="bi bi-building"></i>Organization Profile
                        </a>
                        <a class="nav-link" href="<?= url('/ngo/cases.php') ?>">
                            <i class="bi bi-folder"></i>Assigned Cases
                        </a>
                        <a class="nav-link" href="<?= url('/ngo/team.php') ?>">
                            <i class="bi bi-people"></i>Team Members
                        </a>
                        <a class="nav-link" href="<?= url('/ngo/donations.php') ?>">
                            <i class="bi bi-cash-stack"></i>Donations
                        </a>
                        <a class="nav-link active" href="<?= url('/ngo/notifications.php') ?>">
                            <i class="bi bi-bell"></i>Notifications
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger ms-2"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                        <hr>
                        <a class="nav-link text-danger" href="<?= url('/logout.php') ?>">
                            <i class="bi bi-box-arrow-right"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">Notifications</h4>
                    <?php if ($unreadCount > 0): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="mark_all_read" value="1">
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-check-all me-2"></i>Mark All as Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-body">
                        <?php if (empty($notifications)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-bell-slash display-1 text-muted mb-3"></i>
                                <h5>No Notifications</h5>
                                <p class="text-muted">You don't have any notifications yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($notifications as $notif): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-start <?= !$notif['is_read'] ? 'bg-light' : '' ?>">
                                        <div class="d-flex align-items-start">
                                            <div class="me-3">
                                                <?php
                                                $iconClass = 'bi-bell';
                                                $bgClass = 'bg-primary';
                                                switch ($notif['type']) {
                                                    case 'success': $iconClass = 'bi-check-circle'; $bgClass = 'bg-success'; break;
                                                    case 'warning': $iconClass = 'bi-exclamation-triangle'; $bgClass = 'bg-warning'; break;
                                                    case 'danger': $iconClass = 'bi-x-circle'; $bgClass = 'bg-danger'; break;
                                                    case 'info': $iconClass = 'bi-info-circle'; $bgClass = 'bg-info'; break;
                                                }
                                                ?>
                                                <span class="badge <?= $bgClass ?> rounded-circle p-2">
                                                    <i class="bi <?= $iconClass ?>"></i>
                                                </span>
                                            </div>
                                            <div>
                                                <h6 class="mb-1 <?= !$notif['is_read'] ? 'fw-bold' : '' ?>">
                                                    <?= htmlspecialchars($notif['title']) ?>
                                                </h6>
                                                <p class="mb-1 text-muted"><?= htmlspecialchars($notif['message']) ?></p>
                                                <small class="text-muted"><?= formatDate($notif['created_at'], 'M d, Y H:i') ?></small>
                                            </div>
                                        </div>
                                        <?php if (!$notif['is_read']): ?>
                                            <a href="?read=<?= $notif['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-check"></i>
                                            </a>
                                        <?php endif; ?>
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
