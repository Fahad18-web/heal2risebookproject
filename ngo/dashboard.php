<?php
/**
 * Heal2Rise Book - NGO Dashboard
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('ngo');

$ngoId = getCurrentUserId();
$db = getDB();

// Get NGO data
$stmt = $db->prepare("SELECT * FROM ngos WHERE id = ?");
$stmt->execute([$ngoId]);
$ngo = $stmt->fetch();

// Get assigned cases
// ✅ FIX 1 — tm.role removed (column still exists but category is correct now)
$stmt = $db->prepare("
    SELECT c.*, u.full_name as user_name, u.issue_category as user_issue,
           tm.full_name as team_member_name, tm.category as team_member_category
    FROM cases c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN team_members tm ON c.team_member_id = tm.id
    WHERE c.ngo_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$ngoId]);
$cases = $stmt->fetchAll();

// Get team members
// ✅ FIX 2 — tm.status added to SELECT (needed for badge display below)
$stmt = $db->prepare("
    SELECT DISTINCT tm.id, tm.full_name, tm.category, tm.email, tm.status
    FROM team_members tm
    JOIN cases c ON c.team_member_id = tm.id
    WHERE c.ngo_id = ?
    ORDER BY tm.full_name
");
$stmt->execute([$ngoId]);
$teamMembers = $stmt->fetchAll();

// Statistics
$activeCases = count(array_filter($cases, fn($c) => !in_array($c['status'], ['closed', 'cancelled'])));
$pendingCases = count(array_filter($cases, fn($c) => $c['status'] === 'pending' || $c['status'] === 'assigned'));
$closedCases = count(array_filter($cases, fn($c) => $c['status'] === 'closed'));
$teamCount = count($teamMembers);

// Get notifications
$notifications = getNotifications('ngo', $ngoId, 5);
$unreadCount = getUnreadNotificationCount('ngo', $ngoId);
$donationSummary = getNGODonationSummary($ngoId);

$pageTitle = 'NGO Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="py-4">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="dashboard-sidebar">
                    <div class="text-center mb-4">
                        <img src="<?= url('/uploads/ngos/' . $ngo['logo']) ?>" alt="Logo" class="profile-avatar" 
                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($ngo['organization_name']) ?>&background=4A90A4&color=fff'">
                        <h5 class="mb-1"><?= htmlspecialchars($ngo['organization_name']) ?></h5>
                        <p class="text-muted small mb-2"><?= htmlspecialchars($ngo['city']) ?></p>
                        <span class="badge bg-<?= getStatusBadge($ngo['verification_status']) ?>">
                            <?= ucfirst($ngo['verification_status']) ?>
                        </span>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="<?= url('/ngo/dashboard.php') ?>">
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
                        <a class="nav-link" href="<?= url('/ngo/notifications.php') ?>">
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
                <h2 class="page-title-soft">NGO Operations Dashboard</h2>

                <!-- Welcome Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-2">Welcome, <?= htmlspecialchars($ngo['organization_name']) ?>!</h4>
                                <p class="text-muted mb-0">
                                    Capacity: <?= $ngo['current_cases'] ?>/<?= $ngo['capacity'] ?> cases
                                </p>
                            </div>
                            <!-- ✅ FIX 3 — "Add Team Member" button removed -->
                            <!-- NGO ab team members add nahi kar sakti — sirf Admin karta hai -->
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stat-card primary">
                            <h3><?= $activeCases ?></h3>
                            <p>Active Cases</p>
                            <i class="bi bi-folder-check stat-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card warning">
                            <h3><?= $pendingCases ?></h3>
                            <p>Pending</p>
                            <i class="bi bi-hourglass-split stat-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card success">
                            <h3><?= $closedCases ?></h3>
                            <p>Resolved</p>
                            <i class="bi bi-check-circle stat-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card info">
                            <h3><?= $teamCount ?></h3>
                            <p>Team Members</p>
                            <i class="bi bi-people stat-icon"></i>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div class="card border-success h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Verified Donations</small>
                                    <h4 class="mb-0 text-success">PKR <?= number_format(floatval($donationSummary['total_received'] ?? 0), 0) ?></h4>
                                </div>
                                <i class="bi bi-cash-coin fs-2 text-success"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card border-warning h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Pending Donations</small>
                                    <h4 class="mb-0 text-warning">PKR <?= number_format(floatval($donationSummary['total_pending'] ?? 0), 0) ?></h4>
                                </div>
                                <i class="bi bi-hourglass-split fs-2 text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Cases -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-folder-fill me-2"></i>Assigned Cases</span>
                        <a href="<?= url('/ngo/cases.php') ?>" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cases)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-1 text-muted"></i>
                                <p class="text-muted mt-3">No cases assigned yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Case #</th>
                                            <th>User</th>
                                            <th>Issue</th>
                                            <th>Assigned To</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($cases, 0, 5) as $case): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($case['case_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($case['user_name']) ?></td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= ucfirst(str_replace('_', ' ', $case['issue_category'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($case['team_member_name']): ?>
                                                        <?= htmlspecialchars($case['team_member_name']) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted fst-italic">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= getStatusBadge($case['status']) ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $case['status'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="<?= url('/ngo/case-details.php?id=' . $case['id']) ?>" class="btn btn-sm btn-outline-primary">
                                                        View
                                                    </a>
                                                    <?php if ($case['team_member_id']): ?>
                                                        <a href="<?= url('/ngo/chat-reply.php?case_id=' . $case['id']) ?>" class="btn btn-sm btn-outline-info ms-1" title="Chat">
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

                <!-- Team Members Overview -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-people-fill me-2"></i>Team Members On Our Cases</span>
                        <a href="<?= url('/ngo/team.php') ?>" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($teamMembers)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-person-search display-4 text-muted"></i>
                                <p class="text-muted mt-3">No team members assigned to your cases yet.</p>
                                <!-- ✅ FIX 4 — "Add Team Member" button removed -->
                                <!-- Admin manages team members — NGO cannot add them -->
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach (array_slice($teamMembers, 0, 4) as $member): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex align-items-center p-3 bg-light rounded">
                                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($member['full_name']) ?>&background=4A90A4&color=fff" 
                                                 alt="<?= htmlspecialchars($member['full_name']) ?>" 
                                                 class="rounded-circle me-3" width="50" height="50">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($member['full_name']) ?></h6>
                                                <!-- ✅ FIX 5 — role → category, cases_assigned removed -->
                                                <small class="text-muted">
                                                    <?= ucfirst(str_replace('_', ' ', $member['category'])) ?>
                                                </small>
                                            </div>
                                            <!-- ✅ FIX 6 — is_available → status column use ho raha hai -->
                                            <span class="badge bg-<?= $member['status'] === 'active' ? 'success' : 'secondary' ?> ms-auto">
                                                <?= $member['status'] === 'active' ? 'Active' : 'Inactive' ?>
                                            </span>
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