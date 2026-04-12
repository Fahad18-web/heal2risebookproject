<?php
/**
 * Heal2Rise Book - View User Details (Admin)
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('admin');

$userId = intval($_GET['id'] ?? 0);
if (!$userId) {
    redirect('/admin/users.php');
    exit;
}

$db = getDB();

// Get user details
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    setFlashMessage('User not found.', 'danger');
    redirect('/admin/users.php');
    exit;
}

// Get user's cases
$stmt = $db->prepare("
    SELECT c.*, n.organization_name, tm.full_name as team_member_name
    FROM cases c
    LEFT JOIN ngos n ON c.ngo_id = n.id
    LEFT JOIN team_members tm ON c.team_member_id = tm.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$userId]);
$cases = $stmt->fetchAll();

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('Invalid request.', 'danger');
    } else {
        $action = $_POST['action'] ?? '';
        
        if (in_array($action, ['approve', 'reject'])) {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $isVerified = $action === 'approve' ? 1 : 0;
            
            $stmt = $db->prepare("UPDATE users SET verification_status = ?, is_verified = ? WHERE id = ?");
            $result = $stmt->execute([$status, $isVerified, $userId]);
            
            if ($result) {
                $message = $action === 'approve' 
                    ? 'Your account has been verified. You can now access all features.'
                    : 'Your account verification was rejected. Please contact support.';
                $type = $action === 'approve' ? 'success' : 'warning';
                sendNotification('user', $userId, 'Account Verification Update', $message, $type);
                
                setFlashMessage('User ' . $action . 'd successfully!', 'success');
                redirect('/admin/view-user.php?id=' . $userId);
                exit;
            }
        }
    }
}

$pageTitle = 'View User';
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
                    <div>
                        <a href="<?= url('/admin/users.php') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                            <i class="bi bi-arrow-left me-2"></i>Back to Users
                        </a>
                        <h4 class="mb-0">User Details</h4>
                    </div>
                    <?php if ($user['verification_status'] === 'pending'): ?>
                        <div class="btn-group">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-lg me-2"></i>Approve
                                </button>
                            </form>
                            <form method="POST" class="d-inline ms-2">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-x-lg me-2"></i>Reject
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="row">
                    <!-- User Profile Card -->
                    <div class="col-md-5 col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['full_name']) ?>&background=4A90A4&color=fff&size=100" 
                                     class="rounded-circle mb-3" width="100" height="100">
                                <h5><?= htmlspecialchars($user['full_name']) ?></h5>
                                <p class="text-muted">@<?= htmlspecialchars($user['username']) ?></p>
                                <span class="badge bg-<?= getStatusBadge($user['verification_status']) ?> mb-3">
                                    <?= ucfirst($user['verification_status']) ?>
                                </span>
                                
                                <hr>
                                
                                <div class="text-start">
                                    <p><i class="bi bi-envelope me-2 text-primary"></i><?= htmlspecialchars($user['email']) ?></p>
                                    <p><i class="bi bi-telephone me-2 text-primary"></i><?= htmlspecialchars($user['phone'] ?? 'Not provided') ?></p>
                                    <p><i class="bi bi-geo-alt me-2 text-primary"></i><?= htmlspecialchars(($user['city'] ?? '') . ', ' . ($user['state'] ?? '')) ?></p>
                                    <p><i class="bi bi-calendar me-2 text-primary"></i>Registered: <?= formatDate($user['created_at']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- User Details -->
                    <div class="col-md-7 col-lg-8">
                        <!-- Personal Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-person me-2"></i>Personal Information
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Full Name:</strong> <?= htmlspecialchars($user['full_name']) ?></p>
                                        <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
                                        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                                        <p><strong>Phone:</strong> <?= htmlspecialchars($user['phone'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Date of Birth:</strong> <?= $user['date_of_birth'] ? formatDate($user['date_of_birth']) : 'N/A' ?></p>
                                        <p><strong>Gender:</strong> <?= ucfirst($user['gender'] ?? 'N/A') ?></p>
                                        <p><strong>City:</strong> <?= htmlspecialchars($user['city'] ?? 'N/A') ?></p>
                                        <p><strong>State:</strong> <?= htmlspecialchars($user['state'] ?? 'N/A') ?></p>
                                    </div>
                                </div>
                                <p><strong>Address:</strong> <?= htmlspecialchars($user['address'] ?? 'N/A') ?></p>
                            </div>
                        </div>

                        <!-- Issue Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-chat-heart me-2"></i>Issue Information
                            </div>
                            <div class="card-body">
                                <p><strong>Primary Concern:</strong> 
                                    <span class="badge bg-info"><?= ucfirst(str_replace('_', ' ', $user['issue_category'] ?? 'N/A')) ?></span>
                                </p>
                                <p><strong>Description:</strong></p>
                                <div class="bg-light p-3 rounded">
                                    <?= nl2br(htmlspecialchars($user['issue_description'] ?? 'No description provided.')) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Emergency Contact -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-telephone-forward me-2"></i>Emergency Contact
                            </div>
                            <div class="card-body">
                                <p><strong>Contact Name:</strong> <?= htmlspecialchars($user['emergency_contact_name'] ?? 'N/A') ?></p>
                                <p><strong>Contact Phone:</strong> <?= htmlspecialchars($user['emergency_contact_phone'] ?? 'N/A') ?></p>
                            </div>
                        </div>

                        <!-- Cases -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-folder me-2"></i>User Cases (<?= count($cases) ?>)
                            </div>
                            <div class="card-body">
                                <?php if (empty($cases)): ?>
                                    <p class="text-muted mb-0">No cases for this user.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Case #</th>
                                                    <th>NGO</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($cases as $case): ?>
                                                    <tr>
                                                        <td><a href="<?= url('/admin/view-case.php?id=' . $case['id']) ?>"><?= htmlspecialchars($case['case_number']) ?></a></td>
                                                        <td><?= htmlspecialchars($case['organization_name'] ?? 'Not Assigned') ?></td>
                                                        <td><span class="badge bg-<?= getStatusBadge($case['status']) ?>"><?= ucfirst(str_replace('_', ' ', $case['status'])) ?></span></td>
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
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
