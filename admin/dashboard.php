<?php
/**
 * Heal2Rise Book - Admin Dashboard
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('admin');

$adminId = getCurrentUserId();
$db = getDB();

// Get statistics
$stats = [];

// Total users
$stmt = $db->query("SELECT COUNT(*) FROM users");
$stats['total_users'] = $stmt->fetchColumn();

// Pending user verifications
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE verification_status = 'pending'");
$stats['pending_users'] = $stmt->fetchColumn();

// Total NGOs
$stmt = $db->query("SELECT COUNT(*) FROM ngos");
$stats['total_ngos'] = $stmt->fetchColumn();

// Pending NGO verifications
$stmt = $db->query("SELECT COUNT(*) FROM ngos WHERE verification_status = 'pending'");
$stats['pending_ngos'] = $stmt->fetchColumn();

// Total cases
$stmt = $db->query("SELECT COUNT(*) FROM cases");
$stats['total_cases'] = $stmt->fetchColumn();

// Active cases
$stmt = $db->query("SELECT COUNT(*) FROM cases WHERE status NOT IN ('closed', 'cancelled')");
$stats['active_cases'] = $stmt->fetchColumn();

// Closed cases
$stmt = $db->query("SELECT COUNT(*) FROM cases WHERE status = 'closed'");
$stats['closed_cases'] = $stmt->fetchColumn();

// Donation summary
$stmt = $db->query("SELECT COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END), 0) FROM donations");
$stats['donation_completed_amount'] = $stmt->fetchColumn();
$stmt = $db->query("SELECT COUNT(*) FROM donations WHERE payment_status = 'pending'");
$stats['donation_pending_count'] = $stmt->fetchColumn();

// Recent pending users
$stmt = $db->query("SELECT * FROM users WHERE verification_status = 'pending' ORDER BY created_at DESC LIMIT 5");
$pendingUsers = $stmt->fetchAll();

// Recent pending NGOs
$stmt = $db->query("SELECT * FROM ngos WHERE verification_status = 'pending' ORDER BY created_at DESC LIMIT 5");
$pendingNgos = $stmt->fetchAll();

// Recent cases
$stmt = $db->query("
    SELECT c.*, u.full_name as user_name, n.organization_name 
    FROM cases c 
    LEFT JOIN users u ON c.user_id = u.id 
    LEFT JOIN ngos n ON c.ngo_id = n.id 
    ORDER BY c.created_at DESC LIMIT 5
");
$recentCases = $stmt->fetchAll();

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="py-4">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 mb-4">
                <div class="dashboard-sidebar">
                    <div class="text-center mb-4">
                        <i class="bi bi-shield-lock-fill display-4 text-primary"></i>
                        <h5 class="mt-2 mb-0">Admin Panel</h5>
                        <small class="text-muted"><?= $_SESSION['user_data']['name'] ?? 'Administrator' ?></small>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="<?= url('/admin/dashboard.php') ?>">
                            <i class="bi bi-speedometer2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="<?= url('/admin/profile.php') ?>">
                            <i class="bi bi-person"></i>My Profile
                        </a>
                        <a class="nav-link" href="<?= url('/admin/users.php') ?>">
                            <i class="bi bi-people"></i>Users
                            <?php if ($stats['pending_users'] > 0): ?>
                                <span class="badge bg-warning ms-auto"><?= $stats['pending_users'] ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link" href="<?= url('/admin/ngos.php') ?>">
                            <i class="bi bi-building"></i>NGOs
                            <?php if ($stats['pending_ngos'] > 0): ?>
                                <span class="badge bg-warning ms-auto"><?= $stats['pending_ngos'] ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link" href="<?= url('/admin/cases.php') ?>">
                            <i class="bi bi-folder"></i>Cases
                        </a>
                        <a class="nav-link" href="<?= url('/admin/donations.php') ?>">
                            <i class="bi bi-cash-stack"></i>Donations
                            <?php if ($stats['donation_pending_count'] > 0): ?>
                                <span class="badge bg-warning ms-auto"><?= $stats['donation_pending_count'] ?></span>
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
                <h4 class="mb-4">Dashboard Overview</h4>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stat-card primary">
                            <h3><?= $stats['total_users'] ?></h3>
                            <p>Total Users</p>
                            <i class="bi bi-people stat-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card info">
                            <h3><?= $stats['total_ngos'] ?></h3>
                            <p>Partner NGOs</p>
                            <i class="bi bi-building stat-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card warning">
                            <h3><?= $stats['active_cases'] ?></h3>
                            <p>Active Cases</p>
                            <i class="bi bi-folder-check stat-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card success">
                            <h3><?= $stats['closed_cases'] ?></h3>
                            <p>Cases Resolved</p>
                            <i class="bi bi-check-circle stat-icon"></i>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div class="card border-success h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Verified Donations</small>
                                    <h4 class="mb-0 text-success">PKR <?= number_format(floatval($stats['donation_completed_amount']), 0) ?></h4>
                                </div>
                                <i class="bi bi-cash-coin fs-2 text-success"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card border-warning h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Donations Pending Review</small>
                                    <h4 class="mb-0 text-warning"><?= intval($stats['donation_pending_count']) ?></h4>
                                </div>
                                <i class="bi bi-hourglass-split fs-2 text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Pending Users -->
                    <div class="col-md-6 col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-person-exclamation me-2"></i>Pending User Verifications</span>
                                <a href="<?= url('/admin/users.php?status=pending') ?>" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pendingUsers)): ?>
                                    <p class="text-muted text-center py-3 mb-0">No pending verifications.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($pendingUsers as $user): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?= htmlspecialchars($user['full_name']) ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                                                </div>
                                                <div class="d-flex gap-1">
                                                    <form method="POST" action="<?= url('/admin/verify-user.php') ?>" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn btn-sm btn-success" title="Approve">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="<?= url('/admin/verify-user.php') ?>" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Reject">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Pending NGOs -->
                    <div class="col-md-6 col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-building-exclamation me-2"></i>Pending NGO Verifications</span>
                                <a href="<?= url('/admin/ngos.php?status=pending') ?>" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pendingNgos)): ?>
                                    <p class="text-muted text-center py-3 mb-0">No pending verifications.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($pendingNgos as $ngo): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?= htmlspecialchars($ngo['organization_name']) ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($ngo['city']) ?>, <?= htmlspecialchars($ngo['state']) ?></small>
                                                </div>
                                                <div>
                                                    <a href="<?= url('/admin/verify-ngo.php?id=' . $ngo['id']) ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        Review
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Cases -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-folder-fill me-2"></i>Recent Cases</span>
                        <a href="<?= url('/admin/cases.php') ?>" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentCases)): ?>
                            <p class="text-muted text-center py-3 mb-0">No cases yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Case #</th>
                                            <th>User</th>
                                            <th>NGO</th>
                                            <th>Issue</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentCases as $case): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($case['case_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($case['user_name']) ?></td>
                                                <td><?= htmlspecialchars($case['organization_name'] ?? 'Not Assigned') ?></td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= ucfirst(str_replace('_', ' ', $case['issue_category'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= getStatusBadge($case['status']) ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $case['status'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= formatDate($case['created_at']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
