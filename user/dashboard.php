<?php
/**
 * Heal2Rise Book - User Dashboard
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require user login
requireLogin('user');

$userId = getCurrentUserId();
$db = getDB();

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Get user's cases
$stmt = $db->prepare("
    SELECT c.*, n.organization_name, n.phone as ngo_phone, 
           tm.full_name as team_member_name, tm.role as team_member_role
    FROM cases c
    LEFT JOIN ngos n ON c.ngo_id = n.id
    LEFT JOIN team_members tm ON c.team_member_id = tm.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$userId]);
$cases = $stmt->fetchAll();

// Get notifications
$notifications = getNotifications('user', $userId, 5);
$unreadCount = getUnreadNotificationCount('user', $userId);

// Get case statistics
$activeCases = count(array_filter($cases, fn($c) => !in_array($c['status'], ['closed', 'cancelled'])));
$completedCases = count(array_filter($cases, fn($c) => $c['status'] === 'closed'));

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="py-4">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="dashboard-sidebar">
                    <div class="text-center mb-4">
                        <div class="profile-avatar mx-auto">
                            <img src="<?= url('/uploads/users/' . $user['profile_picture']) ?>" alt="Profile" 
                                 onerror="this.style.display='none'; this.parentElement.textContent='<?= strtoupper(substr($user['full_name'], 0, 1)) ?>'">
                        </div>
                        <h5 class="mb-1 mt-3"><?= htmlspecialchars($user['full_name']) ?></h5>
                        <p class="text-muted small mb-2">@<?= htmlspecialchars($user['username']) ?></p>
                        <span class="badge bg-<?= getStatusBadge($user['verification_status']) ?>">
                            <?= ucfirst($user['verification_status']) ?>
                        </span>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="<?= url('/user/dashboard.php') ?>">
                            <i class="bi bi-speedometer2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="<?= url('/user/profile.php') ?>">
                            <i class="bi bi-person"></i>My Profile
                        </a>
                        <a class="nav-link" href="<?= url('/user/cases.php') ?>">
                            <i class="bi bi-folder"></i>My Cases
                        </a>
                        <a class="nav-link" href="<?= url('/user/notifications.php') ?>">
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
            <div class="col-md-8 col-lg-9">
                <h2 class="page-title-soft">My Support Dashboard</h2>
                <!-- Welcome Message -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-2">Welcome, <?= htmlspecialchars($user['full_name']) ?>!</h4>
                                <p class="text-muted mb-0">
                                    <?php if ($user['verification_status'] === 'approved'): ?>
                                        Your account is verified. You can now connect with NGOs and get support.
                                    <?php else: ?>
                                        Your account is pending verification. Once verified, you can access all features.
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                <?php if ($user['verification_status'] === 'approved' && $activeCases === 0): ?>
                                    <a href="<?= url('/user/request-support.php') ?>" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-2"></i>Request Support
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="stat-card primary">
                            <h3><?= $activeCases ?></h3>
                            <p>Active Cases</p>
                            <i class="bi bi-folder-check stat-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card success">
                            <h3><?= $completedCases ?></h3>
                            <p>Completed</p>
                            <i class="bi bi-check-circle stat-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card info">
                            <h3><?= $unreadCount ?></h3>
                            <p>Notifications</p>
                            <i class="bi bi-bell stat-icon"></i>
                        </div>
                    </div>
                </div>

                <!-- Active Cases -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-folder-fill me-2"></i>My Cases</span>
                        <a href="<?= url('/user/cases.php') ?>" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cases)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-1 text-muted"></i>
                                <p class="text-muted mt-3">No cases yet.</p>
                                <?php if ($user['verification_status'] === 'approved'): ?>
                                    <a href="<?= url('/user/request-support.php') ?>" class="btn btn-primary mt-2">
                                        <i class="bi bi-plus-circle me-2"></i>Request Support
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Case #</th>
                                            <th>NGO</th>
                                            <th>Assigned To</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($cases, 0, 5) as $case): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($case['case_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($case['organization_name'] ?? 'Not Assigned') ?></td>
                                                <td>
                                                    <?php if ($case['team_member_name']): ?>
                                                        <?= htmlspecialchars($case['team_member_name']) ?>
                                                        <br><small class="text-muted"><?= ucfirst($case['team_member_role']) ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= getStatusBadge($case['status']) ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $case['status'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= formatDate($case['created_at']) ?></td>
                                                <td>
                                                    <a href="<?= url('/user/case-details.php?id=' . $case['id']) ?>" class="btn btn-sm btn-outline-primary">
                                                        View
                                                    </a>
                                                    <?php if ($case['team_member_id']): ?>
                                                        <a href="<?= url('/user/chat-reply.php?case_id=' . $case['id']) ?>" class="btn btn-sm btn-outline-info ms-1" title="Chat">
                                                            <i class="bi bi-chat-dots"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Notifications -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-bell-fill me-2"></i>Recent Notifications</span>
                        <a href="<?= url('/user/notifications.php') ?>" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($notifications)): ?>
                            <p class="text-muted text-center py-3 mb-0">No notifications yet.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="list-group-item d-flex align-items-start <?= $notification['is_read'] ? '' : 'bg-light' ?>">
                                        <div class="me-3">
                                            <span class="badge rounded-pill bg-<?= $notification['type'] ?>">
                                                <i class="bi bi-<?= $notification['type'] === 'success' ? 'check' : ($notification['type'] === 'warning' ? 'exclamation' : 'info') ?>"></i>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?= htmlspecialchars($notification['title']) ?></h6>
                                            <p class="mb-1 text-muted small"><?= htmlspecialchars($notification['message']) ?></p>
                                            <small class="text-muted"><?= timeAgo($notification['created_at']) ?></small>
                                        </div>
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
