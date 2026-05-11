<?php
/**
 * Heal2Rise Book - View NGO Details (Admin)
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('admin');

$ngoId = intval($_GET['id'] ?? 0);
if (!$ngoId) {
    redirect('/admin/ngos.php');
    exit;
}

$db = getDB();

// Get NGO details
$stmt = $db->prepare("SELECT * FROM ngos WHERE id = ?");
$stmt->execute([$ngoId]);
$ngo = $stmt->fetch();

if (!$ngo) {
    setFlashMessage('NGO not found.', 'danger');
    redirect('/admin/ngos.php');
    exit;
}

// ✅ FIXED — ngo_id removed from team_members query
// Team members ab cases table ke zariye dhunde jaate hain
$stmt = $db->prepare("
    SELECT DISTINCT tm.id, tm.full_name, tm.category, tm.email, tm.status
    FROM team_members tm
    JOIN cases c ON c.team_member_id = tm.id
    WHERE c.ngo_id = ?
    ORDER BY tm.full_name
");
$stmt->execute([$ngoId]);
$teamMembers = $stmt->fetchAll();

// Get NGO's cases
$stmt = $db->prepare("
    SELECT c.*, u.full_name as user_name, tm.full_name as team_member_name
    FROM cases c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN team_members tm ON c.team_member_id = tm.id
    WHERE c.ngo_id = ?
    ORDER BY c.created_at DESC
    LIMIT 10
");
$stmt->execute([$ngoId]);
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
            
            $stmt = $db->prepare("UPDATE ngos SET verification_status = ?, is_verified = ? WHERE id = ?");
            $result = $stmt->execute([$status, $isVerified, $ngoId]);
            
            if ($result) {
                $message = $action === 'approve' 
                    ? 'Your organization has been verified. You can now receive and manage cases.'
                    : 'Your organization verification was rejected. Please contact support.';
                $type = $action === 'approve' ? 'success' : 'warning';
                sendNotification('ngo', $ngoId, 'Verification Update', $message, $type);
                
                setFlashMessage('NGO ' . $action . 'd successfully!', 'success');
                redirect('/admin/view-ngo.php?id=' . $ngoId);
                exit;
            }
        }
    }
}

$pageTitle = 'View NGO';
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
                        <a class="nav-link" href="<?= url('/admin/users.php') ?>">
                            <i class="bi bi-people"></i>Users
                        </a>
                        <a class="nav-link active" href="<?= url('/admin/ngos.php') ?>">
                            <i class="bi bi-building"></i>NGOs
                        </a>
                        <a class="nav-link" href="<?= url('/admin/cases.php') ?>">
                            <i class="bi bi-folder"></i>Cases
                        </a>
                        <!-- ✅ ADDED — Team Members link -->
                        <a class="nav-link" href="<?= url('/admin/create-team-member.php') ?>">
                            <i class="bi bi-people-fill"></i>Team Members
                        </a>
                        <!-- ✅ ADDED — Messages / Chat link -->
                        <a class="nav-link" href="<?= url('/admin/chat.php') ?>">
                            <i class="bi bi-chat-dots"></i>Messages
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
                        <a href="<?= url('/admin/ngos.php') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                            <i class="bi bi-arrow-left me-2"></i>Back to NGOs
                        </a>
                        <h4 class="mb-0">NGO Details</h4>
                    </div>
                    <?php if ($ngo['verification_status'] === 'pending'): ?>
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
                    <!-- NGO Profile Card -->
                    <div class="col-md-5 col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($ngo['organization_name']) ?>&background=4A90A4&color=fff&size=100" 
                                     class="rounded-circle mb-3" width="100" height="100">
                                <h5><?= htmlspecialchars($ngo['organization_name']) ?></h5>
                                <p class="text-muted">Reg: <?= htmlspecialchars($ngo['registration_number']) ?></p>
                                <span class="badge bg-<?= getStatusBadge($ngo['verification_status']) ?> mb-3">
                                    <?= ucfirst($ngo['verification_status']) ?>
                                </span>
                                
                                <hr>
                                
                                <div class="text-start">
                                    <p><i class="bi bi-person me-2 text-primary"></i><?= htmlspecialchars($ngo['contact_person']) ?></p>
                                    <p><i class="bi bi-envelope me-2 text-primary"></i><?= htmlspecialchars($ngo['email']) ?></p>
                                    <p><i class="bi bi-telephone me-2 text-primary"></i><?= htmlspecialchars($ngo['phone']) ?></p>
                                    <p><i class="bi bi-geo-alt me-2 text-primary"></i><?= htmlspecialchars($ngo['city'] . ', ' . $ngo['state']) ?></p>
                                    <?php if ($ngo['website']): ?>
                                        <p><i class="bi bi-globe me-2 text-primary"></i><a href="<?= htmlspecialchars($ngo['website']) ?>" target="_blank"><?= htmlspecialchars($ngo['website']) ?></a></p>
                                    <?php endif; ?>
                                </div>

                                <hr>

                                <div class="row text-center">
                                    <div class="col-6">
                                        <h4 class="text-primary"><?= $ngo['current_cases'] ?></h4>
                                        <small class="text-muted">Current Cases</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-primary"><?= $ngo['capacity'] ?></h4>
                                        <small class="text-muted">Capacity</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- NGO Details -->
                    <div class="col-md-7 col-lg-8">
                        <!-- Organization Details -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-building me-2"></i>Organization Details
                            </div>
                            <div class="card-body">
                                <p><strong>Address:</strong> <?= htmlspecialchars($ngo['address']) ?></p>
                                <p><strong>City:</strong> <?= htmlspecialchars($ngo['city']) ?></p>
                                <p><strong>State:</strong> <?= htmlspecialchars($ngo['state']) ?></p>
                                <p><strong>Registered:</strong> <?= formatDate($ngo['created_at']) ?></p>
                                
                                <p><strong>Specializations:</strong></p>
                                <div class="mb-3">
                                    <?php foreach (explode(',', $ngo['specialization']) as $spec): ?>
                                        <span class="badge bg-info me-1"><?= ucfirst(str_replace('_', ' ', $spec)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if ($ngo['description']): ?>
                                    <p><strong>Description:</strong></p>
                                    <div class="bg-light p-3 rounded">
                                        <?= nl2br(htmlspecialchars($ngo['description'])) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($ngo['certificate_doc']): ?>
                                    <p class="mt-3"><strong>Certificate:</strong> 
                                        <a href="<?= url('/uploads/ngos/certificates/' . htmlspecialchars($ngo['certificate_doc'])) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-file-earmark me-2"></i>View Document
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Team Members -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-people me-2"></i>Team Members Assigned to This NGO's Cases (<?= count($teamMembers) ?>)
                            </div>
                            <div class="card-body">
                                <?php if (empty($teamMembers)): ?>
                                    <p class="text-muted mb-0">No team members assigned to this NGO's cases yet.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <!-- ✅ FIXED — "Role" → "Category" -->
                                                    <th>Category</th>
                                                    <th>Email</th>
                                                    <!-- ✅ FIXED — "Cases" column removed (cases_assigned/max_cases don't exist) -->
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($teamMembers as $member): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($member['full_name']) ?></td>
                                                        <!-- ✅ ALREADY CORRECT — category use ho raha hai -->
                                                        <td><?= ucfirst(str_replace('_', ' ', $member['category'])) ?></td>
                                                        <td><?= htmlspecialchars($member['email']) ?></td>
                                                        <!-- ✅ FIXED — cases_assigned/max_cases td removed -->
                                                        <!-- ✅ FIXED — is_available removed, status column use ho raha hai -->
                                                        <td>
                                                            <span class="badge bg-<?= $member['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                                <?= ucfirst($member['status']) ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Cases -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-folder me-2"></i>Recent Cases
                            </div>
                            <div class="card-body">
                                <?php if (empty($cases)): ?>
                                    <p class="text-muted mb-0">No cases assigned to this NGO.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Case #</th>
                                                    <th>User</th>
                                                    <th>Assigned To</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($cases as $case): ?>
                                                    <tr>
                                                        <td><a href="<?= url('/admin/view-case.php?id=' . $case['id']) ?>"><?= htmlspecialchars($case['case_number']) ?></a></td>
                                                        <td><?= htmlspecialchars($case['user_name']) ?></td>
                                                        <td><?= htmlspecialchars($case['team_member_name'] ?? 'Not Assigned') ?></td>
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