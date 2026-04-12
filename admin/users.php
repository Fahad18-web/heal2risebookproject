<?php
/**
 * Heal2Rise Book - Admin Users Management
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('admin');

$db = getDB();

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('Invalid request.', 'danger');
    } else {
        $userId = intval($_POST['user_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        
        if ($userId && in_array($action, ['approve', 'reject'])) {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $isVerified = $action === 'approve' ? 1 : 0;
            
            $stmt = $db->prepare("UPDATE users SET verification_status = ?, is_verified = ? WHERE id = ?");
            $result = $stmt->execute([$status, $isVerified, $userId]);
            
            if ($result) {
                // Send notification to user
                $message = $action === 'approve' 
                    ? 'Your account has been verified. You can now access all features.'
                    : 'Your account verification was rejected. Please contact support for more information.';
                $type = $action === 'approve' ? 'success' : 'warning';
                sendNotification('user', $userId, 'Account Verification Update', $message, $type);
                
                setFlashMessage('User ' . $action . 'd successfully!', 'success');
            }
        }
    }
    redirect('/admin/users.php');
    exit;
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = sanitize($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;

// Build query
$where = [];
$params = [];

if ($statusFilter !== 'all') {
    $where[] = "verification_status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $where[] = "(full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total
$stmt = $db->prepare("SELECT COUNT(*) FROM users $whereClause");
$stmt->execute($params);
$total = $stmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

// Get users
$stmt = $db->prepare("SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Pending count
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE verification_status = 'pending'");
$pendingCount = $stmt->fetchColumn();

$pageTitle = 'Manage Users';
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
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?= url('/admin/dashboard.php') ?>">
                            <i class="bi bi-speedometer2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="<?= url('/admin/profile.php') ?>">
                            <i class="bi bi-person"></i>My Profile
                        </a>
                        <a class="nav-link active" href="<?= url('/admin/users.php') ?>">
                            <i class="bi bi-people"></i>Users
                        </a>
                        <a class="nav-link" href="<?= url('/admin/ngos.php') ?>">
                            <i class="bi bi-building"></i>NGOs
                        </a>
                        <a class="nav-link" href="<?= url('/admin/cases.php') ?>">
                            <i class="bi bi-folder"></i>Cases
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
                    <h4 class="mb-0">Manage Users</h4>
                    <span class="badge bg-warning"><?= $pendingCount ?> Pending Verifications</span>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" 
                                       value="<?= htmlspecialchars($searchQuery) ?>" 
                                       placeholder="Name, email, or username...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-2"></i>Filter
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="<?= url('/admin/users.php') ?>" class="btn btn-outline-secondary w-100">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-people-fill me-2"></i>Users (<?= $total ?>)
                    </div>
                    <div class="card-body">
                        <?php if (empty($users)): ?>
                            <p class="text-muted text-center py-4 mb-0">No users found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Contact</th>
                                            <th>Issue</th>
                                            <th>Location</th>
                                            <th>Status</th>
                                            <th>Registered</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['full_name']) ?>&background=4A90A4&color=fff" 
                                                             class="rounded-circle me-2" width="40" height="40">
                                                        <div>
                                                            <strong><?= htmlspecialchars($user['full_name']) ?></strong><br>
                                                            <small class="text-muted">@<?= htmlspecialchars($user['username']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($user['email']) ?><br>
                                                    <small class="text-muted"><?= htmlspecialchars($user['phone'] ?? '-') ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= ucfirst(str_replace('_', ' ', $user['issue_category'] ?? 'N/A')) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($user['city'] ?? '-') ?>, <?= htmlspecialchars($user['state'] ?? '-') ?></td>
                                                <td>
                                                    <span class="badge bg-<?= getStatusBadge($user['verification_status']) ?>">
                                                        <?= ucfirst($user['verification_status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= formatDate($user['created_at']) ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="<?= url('/admin/view-user.php?id=' . $user['id']) ?>" class="btn btn-outline-primary" title="View">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($user['verification_status'] === 'pending'): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                                <input type="hidden" name="action" value="approve">
                                                                <button type="submit" class="btn btn-success" title="Approve">
                                                                    <i class="bi bi-check-lg"></i>
                                                                </button>
                                                            </form>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                                <button type="submit" class="btn btn-danger" title="Reject">
                                                                    <i class="bi bi-x-lg"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
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
