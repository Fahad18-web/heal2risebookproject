<?php
/**
 * Heal2Rise Book - Team Member Dashboard
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('team_member');

$db           = getDB();
$teamMemberId = getCurrentUserId();
$userData     = $_SESSION['user_data'] ?? ['name' => 'Team Member', 'category' => ''];

// Handle Mark All Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_type = 'team_member' AND user_id = ?");
        $stmt->execute([$teamMemberId]);
        redirect('/team/dashboard.php');
    }
}

// ── Stats ────────────────────────────────────────────────────────────

// Total cases assigned to this team member
$stmt = $db->prepare("SELECT COUNT(*) FROM cases WHERE team_member_id = ?");
$stmt->execute([$teamMemberId]);
$totalCases = $stmt->fetchColumn();

// Cases still in progress (not closed)
$stmt = $db->prepare("SELECT COUNT(*) FROM cases WHERE team_member_id = ? AND status != 'closed'");
$stmt->execute([$teamMemberId]);
$inProgress = $stmt->fetchColumn();

// Cases at 100% progress awaiting satisfaction confirmation
$stmt = $db->prepare("
    SELECT COUNT(*) FROM cases 
    WHERE team_member_id = ? 
    AND progress_percentage = 100 
    AND status != 'closed'
");
$stmt->execute([$teamMemberId]);
$atHundredPercent = $stmt->fetchColumn();

// Pending closure requests sent to admin
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM cases c 
    JOIN satisfaction_requests sr ON c.id = sr.case_id 
    WHERE c.team_member_id = ? 
    AND sr.closure_request_sent = 1 
    AND sr.admin_decision = 'pending'
    AND c.status != 'closed'
");
$stmt->execute([$teamMemberId]);
$pendingClosures = $stmt->fetchColumn();

// ── Recent Cases (latest 5) ──────────────────────────────────────────
$stmt = $db->prepare("
    SELECT c.id, c.case_number, c.status, c.progress_percentage,
           u.full_name as user_name
    FROM cases c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.team_member_id = ? 
    ORDER BY c.id DESC 
    LIMIT 5
");
$stmt->execute([$teamMemberId]);
$recentCases = $stmt->fetchAll();

// ── Upcoming Sessions (next 5 scheduled) ────────────────────────────
$stmt = $db->prepare("
    SELECT cs.id, cs.session_date, cs.session_time, cs.session_type, cs.duration_minutes,
           c.case_number, c.id as case_id, u.full_name as user_name
    FROM counseling_sessions cs
    JOIN cases c ON cs.case_id = c.id
    JOIN users u ON c.user_id = u.id
    WHERE cs.counselor_id = ?
      AND cs.status = 'scheduled'
      AND cs.session_date >= CURDATE()
    ORDER BY cs.session_date ASC, cs.session_time ASC
    LIMIT 5
");
$stmt->execute([$teamMemberId]);
$upcomingSessions = $stmt->fetchAll();

// ── Unread messages count ────────────────────────────────────────────
$unreadMessages = getUnreadMessageCount('team_member', $teamMemberId);

$pageTitle = 'Team Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">

        <!-- ── Sidebar ─────────────────────────────────────────────── -->
        <div class="col-md-3 col-lg-2 mb-4">
            <div class="dashboard-sidebar">
                <div class="text-center mb-4">
                    <i class="bi bi-person-badge-fill display-4 text-primary"></i>
                    <h6 class="mt-2 mb-0"><?= htmlspecialchars($userData['name'] ?? 'Team Member') ?></h6>
                    <small class="text-muted">
                        <?= ucfirst(str_replace('_', ' ', $userData['category'] ?? '')) ?>
                    </small>
                </div>

                <nav class="nav flex-column">
                    <a class="nav-link active" href="<?= url('/team/dashboard.php') ?>">
                        <i class="bi bi-speedometer2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="<?= url('/team/cases.php') ?>">
                        <i class="bi bi-folder2-open"></i>My Cases
                    </a>
                    <a class="nav-link" href="<?= url('/team/chat.php') ?>">
                        <i class="bi bi-chat-dots"></i>Messages
                        <?php if ($unreadMessages > 0): ?>
                            <span class="badge bg-danger ms-2"><?= $unreadMessages ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="nav-link" href="<?= url('/team/profile.php') ?>">
                        <i class="bi bi-person"></i>Profile
                    </a>
                    <hr>
                    <a class="nav-link text-danger" href="<?= url('/logout.php') ?>">
                        <i class="bi bi-box-arrow-left"></i>Logout
                    </a>
                </nav>
            </div>
        </div>

        <!-- ── Main Content ─────────────────────────────────────────── -->
        <main class="col-md-9 col-lg-10 px-md-4 py-4">

            <!-- Flash Message -->
            <?php $flash = getFlashMessage(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
                    <?= htmlspecialchars($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Welcome Card -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="avatar-xl bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-4">
                        <i class="bi bi-person-badge display-5"></i>
                    </div>
                    <div>
                        <h3 class="mb-1 fw-bold text-dark">
                            Welcome back, <?= htmlspecialchars($userData['name'] ?? 'Team Member') ?>!
                        </h3>
                        <p class="mb-0 fs-5">
                            <span class="badge bg-light text-primary border px-3 py-2 rounded-pill">
                                <?= ucfirst(str_replace('_', ' ', $userData['category'] ?? '')) ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stat-card primary h-100">
                        <h3><?= $totalCases ?></h3>
                        <p>Total Cases</p>
                        <i class="bi bi-folder2-open stat-icon"></i>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card info h-100">
                        <h3><?= $inProgress ?></h3>
                        <p>In Progress</p>
                        <i class="bi bi-arrow-clockwise stat-icon"></i>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card success h-100">
                        <h3><?= $atHundredPercent ?></h3>
                        <p>100% Progress</p>
                        <i class="bi bi-check2-circle stat-icon"></i>
                        <small class="text-muted d-block mt-2">Awaiting satisfaction</small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card warning h-100">
                        <h3><?= $pendingClosures ?></h3>
                        <p>Pending Closures</p>
                        <i class="bi bi-lock stat-icon"></i>
                        <small class="text-muted d-block mt-2">Sent to admin</small>
                    </div>
                </div>
            </div>

            <!-- Recent Cases Table -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history me-2"></i>Recent Cases
                    </h5>
                    <a href="<?= url('/team/cases.php') ?>" class="btn btn-sm btn-outline-primary">
                        View All
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Case Number</th>
                                    <th>User Name</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentCases)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox display-6 d-block mb-2"></i>
                                            No cases assigned yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentCases as $case): ?>
                                        <?php
                                        // Progress bar color
                                        $prog      = (int)($case['progress_percentage'] ?? 0);
                                        $progColor = $prog === 100 ? 'success' : ($prog >= 50 ? 'warning' : 'danger');

                                        // Status badge color
                                        $badgeColor = 'secondary';
                                        if ($case['status'] === 'assigned')    $badgeColor = 'primary';
                                        if ($case['status'] === 'in_progress') $badgeColor = 'info';
                                        if ($case['status'] === 'counseling')  $badgeColor = 'warning';
                                        if ($case['status'] === 'closed')      $badgeColor = 'success';
                                        if ($case['status'] === 'follow_up')   $badgeColor = 'dark';
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($case['case_number']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($case['user_name']) ?></td>
                                            <td style="width: 25%;">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height: 8px;">
                                                        <div class="progress-bar bg-<?= $progColor ?>"
                                                             role="progressbar"
                                                             style="width: <?= $prog ?>%;"
                                                             aria-valuenow="<?= $prog ?>"
                                                             aria-valuemin="0"
                                                             aria-valuemax="100">
                                                        </div>
                                                    </div>
                                                    <span class="small fw-bold"><?= $prog ?>%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $badgeColor ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $case['status'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?= url('/team/case-details.php?id=' . $case['id']) ?>"
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bi bi-eye me-1"></i>View
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

            <!-- Upcoming Sessions -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-check me-2 text-primary"></i>Upcoming Sessions
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($upcomingSessions)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                            No upcoming sessions scheduled.
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($upcomingSessions as $session): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                                    <div>
                                        <div class="fw-semibold mb-1">
                                            <i class="bi bi-calendar3 text-primary me-1"></i>
                                            <?= date('D, d M Y', strtotime($session['session_date'])) ?>
                                            <?php if ($session['session_time']): ?>
                                                &nbsp;<i class="bi bi-clock text-muted"></i>
                                                <?= date('h:i A', strtotime($session['session_time'])) ?>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($session['user_name']) ?>
                                            &middot; <?= ucfirst(str_replace('_', ' ', $session['session_type'])) ?>
                                            &middot; <?= $session['duration_minutes'] ?> min
                                        </small>
                                    </div>
                                    <a href="<?= url('/team/case-details.php?id=' . $session['case_id']) ?>"
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-arrow-right me-1"></i><?= htmlspecialchars($session['case_number']) ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>