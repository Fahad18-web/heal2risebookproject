<?php
/**
 * Heal2Rise Book - NGO All Cases
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('ngo');

$ngoId = getCurrentUserId();
$db = getDB();

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = sanitize($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;

// Build query
$where = ["c.ngo_id = ?"];
$params = [$ngoId];

if ($statusFilter !== 'all') {
    $where[] = "c.status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
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
    SELECT c.*, u.full_name as user_name, u.email as user_email,
           tm.full_name as team_member_name, tm.role as team_member_role
    FROM cases c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN team_members tm ON c.team_member_id = tm.id
    $whereClause
    ORDER BY c.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$cases = $stmt->fetchAll();

// Statistics
$stmt = $db->prepare("SELECT COUNT(*) FROM cases WHERE ngo_id = ? AND status NOT IN ('closed', 'cancelled')");
$stmt->execute([$ngoId]);
$activeCount = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM cases WHERE ngo_id = ? AND team_member_id IS NULL");
$stmt->execute([$ngoId]);
$unassignedCount = $stmt->fetchColumn();

$pageTitle = 'All Cases';
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
                        <a class="nav-link active" href="<?= url('/ngo/cases.php') ?>">
                            <i class="bi bi-folder"></i>Cases
                        </a>
                        <a class="nav-link" href="<?= url('/ngo/team.php') ?>">
                            <i class="bi bi-people"></i>Team
                        </a>
                        <a class="nav-link" href="<?= url('/ngo/profile.php') ?>">
                            <i class="bi bi-gear"></i>Settings
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
                    <h4 class="page-title-soft mb-0">NGO Connection & Case Assignment</h4>
                    <div class="case-status-summary">
                        <span class="badge bg-primary me-2"><?= $activeCount ?> Active</span>
                        <?php if ($unassignedCount > 0): ?>
                            <span class="badge bg-warning"><?= $unassignedCount ?> Unassigned</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" 
                                       value="<?= htmlspecialchars($searchQuery) ?>" 
                                       placeholder="Case number or user name...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="assigned" <?= $statusFilter === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                                    <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="counseling" <?= $statusFilter === 'counseling' ? 'selected' : '' ?>>Counseling</option>
                                    <option value="rehabilitation" <?= $statusFilter === 'rehabilitation' ? 'selected' : '' ?>>Rehabilitation</option>
                                    <option value="skill_development" <?= $statusFilter === 'skill_development' ? 'selected' : '' ?>>Skill Development</option>
                                    <option value="follow_up" <?= $statusFilter === 'follow_up' ? 'selected' : '' ?>>Follow Up</option>
                                    <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-2"></i>Filter
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="<?= url('/ngo/cases.php') ?>" class="btn btn-outline-secondary w-100">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Cases Table -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-folder-fill me-2"></i>Cases (<?= $total ?>)
                    </div>
                    <div class="card-body">
                        <?php if (empty($cases)): ?>
                            <p class="text-muted text-center py-4 mb-0">No cases found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Case #</th>
                                            <th>User</th>
                                            <th>Issue</th>
                                            <th>Assigned To</th>
                                            <th>Severity</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cases as $case): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($case['case_number']) ?></strong></td>
                                                <td>
                                                    <?= htmlspecialchars($case['user_name']) ?><br>
                                                    <small class="text-muted">ID: U-<?= str_pad((string)$case['user_id'], 5, '0', STR_PAD_LEFT) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= ucfirst(str_replace('_', ' ', $case['issue_category'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($case['team_member_name']): ?>
                                                        <?= htmlspecialchars($case['team_member_name']) ?><br>
                                                        <small class="text-muted"><?= ucfirst($case['team_member_role']) ?></small>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Unassigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $case['severity_level'] === 'high' || $case['severity_level'] === 'critical' ? 'danger' : ($case['severity_level'] === 'medium' ? 'warning' : 'success') ?>">
                                                        <?= ucfirst($case['severity_level']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= getStatusBadge($case['status']) ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $case['status'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= formatDate($case['created_at']) ?></td>
                                                <td>
                                                    <a href="<?= url('/ngo/case-details.php?id=' . $case['id']) ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-eye me-1"></i>View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($pagination['total_pages'] > 1): ?>
                                <nav class="mt-4">
                                    <ul class="pagination justify-content-center mb-0">
                                        <li class="page-item <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= $statusFilter ?>&search=<?= urlencode($searchQuery) ?>">Previous</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?>&status=<?= $statusFilter ?>&search=<?= urlencode($searchQuery) ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= $statusFilter ?>&search=<?= urlencode($searchQuery) ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
