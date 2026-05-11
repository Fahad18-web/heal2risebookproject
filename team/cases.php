<?php
/**
 * Heal2Rise Book - Team Member Cases
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('team_member');

$teamMemberId = getCurrentUserId();
$db = getDB();

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = sanitize($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;

// Build query
$where = ["c.team_member_id = ?"];
$params = [$teamMemberId];

if ($statusFilter !== 'all') {
    $where[] = "c.status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    // Only search by case_number directly or try to join users table if needed.
    // We already join users u, so we can search full_name
    $where[] = "(c.case_number LIKE ? OR u.full_name LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Count total
$stmt = $db->prepare("SELECT COUNT(*) FROM cases c LEFT JOIN users u ON c.user_id = u.id $whereClause");
$stmt->execute($params);
$total = $stmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

// Get cases
$stmt = $db->prepare("
    SELECT c.*, u.full_name as user_name, u.email as user_email
    FROM cases c
    LEFT JOIN users u ON c.user_id = u.id
    $whereClause
    ORDER BY c.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$cases = $stmt->fetchAll();

// Statistics
$stmt = $db->prepare("SELECT COUNT(*) FROM cases WHERE team_member_id = ? AND status NOT IN ('closed', 'cancelled')");
$stmt->execute([$teamMemberId]);
$activeCount = $stmt->fetchColumn();

// Progress alerts stats could be here
// e.g. at 100% awaiting closure
$stmt = $db->prepare("SELECT COUNT(*) FROM cases c WHERE c.team_member_id = ? AND c.progress_percentage = 100 AND c.status != 'closed'");
$stmt->execute([$teamMemberId]);
$atHundredPercent = $stmt->fetchColumn();

$pageTitle = 'My Cases';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="py-4">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 mb-4">
                <div class="dashboard-sidebar">
                    <div class="text-center mb-4">
                        <i class="bi bi-person-badge display-4 text-primary"></i>
                        <h6 class="mt-2 mb-0"><?= htmlspecialchars($_SESSION['user_data']['name'] ?? 'Team Member') ?></h6>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?= url('/team/dashboard.php') ?>">
                            <i class="bi bi-speedometer2"></i>Dashboard
                        </a>
                        <a class="nav-link active" href="<?= url('/team/cases.php') ?>">
                            <i class="bi bi-folder"></i>My Cases
                        </a>
                        <a class="nav-link" href="<?= url('/team/chat.php') ?>">
                            <i class="bi bi-chat-dots"></i>Messages
                        </a>
                        <a class="nav-link" href="<?= url('/team/profile.php') ?>">
                            <i class="bi bi-person"></i>My Profile
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
                    <h4 class="page-title-soft mb-0">My Assigned Cases</h4>
                    <div class="case-status-summary">
                        <span class="badge bg-primary me-2"><?= $activeCount ?> Active</span>
                        <?php if ($atHundredPercent > 0): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-check2-circle"></i> <?= $atHundredPercent ?> Awaiting Closure</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" placeholder="Case # or User Name" value="<?= $searchQuery ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="assigned" <?= $statusFilter === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                                    <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="counseling" <?= $statusFilter === 'counseling' ? 'selected' : '' ?>>Counseling</option>
                                    <option value="rehabilitation" <?= $statusFilter === 'rehabilitation' ? 'selected' : '' ?>>Rehabilitation</option>
                                    <option value="skill_development" <?= $statusFilter === 'skill_development' ? 'selected' : '' ?>>Skill Development</option>
                                    <option value="follow_up" <?= $statusFilter === 'follow_up' ? 'selected' : '' ?>>Follow Up</option>
                                    <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-2"></i>Filter Cases
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Cases List -->
                <div class="card">
                    <div class="card-body p-0">
                        <?php if (empty($cases)): ?>
                            <div class="text-center p-5">
                                <i class="bi bi-folder-x display-1 text-muted mb-3"></i>
                                <h5>No Cases Found</h5>
                                <p class="text-muted">You don't have any cases matching these criteria.</p>
                                <?php if ($searchQuery || $statusFilter !== 'all'): ?>
                                    <a href="<?= url('/team/cases.php') ?>" class="btn btn-outline-primary mt-2">Clear Filters</a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Case ID</th>
                                            <th>User</th>
                                            <th>Issue</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cases as $c): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($c['case_number']) ?></strong><br>
                                                    <small class="text-muted"><?= formatDate($c['created_at'], 'M d, Y') ?></small>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($c['user_name']) ?><br>
                                                    <small class="text-muted"><?= htmlspecialchars($c['user_email']) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', $c['issue_category'])) ?></span><br>
                                                    <small class="text-muted text-truncate d-inline-block" style="max-width: 150px;">
                                                        <?= htmlspecialchars($c['description']) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $c['priority'] === 'urgent' ? 'danger' : ($c['priority'] === 'high' ? 'warning' : 'secondary') ?>">
                                                        <?= ucfirst($c['priority']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= getStatusBadge($c['status']) ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $c['status'])) ?>
                                                    </span>
                                                    
                                                    <?php if(isset($c['progress_percentage'])): ?>
                                                    <div class="progress mt-2" style="height: 4px;">
                                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $c['progress_percentage'] ?>%;"></div>
                                                    </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="<?= url('/team/case-details.php?id=' . $c['id']) ?>" class="btn btn-sm btn-outline-primary">
                                                        View & Manage
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($pagination['total_pages'] > 1): ?>
                                <div class="p-3 border-top d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        Showing <?= $pagination['offset'] + 1 ?> to <?= min($pagination['offset'] + $perPage, $total) ?> of <?= $total ?> cases
                                    </small>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination pagination-sm mb-0">
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($searchQuery) ?>">Previous</a>
                                            </li>
                                            
                                            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                                <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($searchQuery) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <li class="page-item <?= $page >= $pagination['total_pages'] ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($searchQuery) ?>">Next</a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
