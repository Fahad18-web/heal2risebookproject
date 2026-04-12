<?php
/**
 * Admin - Verify NGO
 * Review and approve/reject NGO verification
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireLogin('admin');

$ngoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$ngoId) {
    $_SESSION['error'] = 'Invalid request.';
    redirect('/admin/ngos.php');
}

try {
    $pdo = getDB();
    
    // Get NGO info
    $stmt = $pdo->prepare("SELECT * FROM ngos WHERE id = ?");
    $stmt->execute([$ngoId]);
    $ngo = $stmt->fetch();
    
    if (!$ngo) {
        $_SESSION['error'] = 'NGO not found.';
        redirect('/admin/ngos.php');
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Invalid security token.';
            redirect('/admin/verify-ngo.php?id=' . $ngoId);
        }
        
        $action = $_POST['action'] ?? '';
        
        if (!in_array($action, ['approve', 'reject'])) {
            $_SESSION['error'] = 'Invalid action.';
            redirect('/admin/verify-ngo.php?id=' . $ngoId);
        }
        
        // Update verification status
        $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
        $isVerified = ($action === 'approve') ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE ngos SET verification_status = ?, is_verified = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $isVerified, $ngoId]);
        
        // Create notification for the NGO
        $notificationTitle = ($action === 'approve') 
            ? 'NGO Verified' 
            : 'NGO Verification Rejected';
        $notificationMessage = ($action === 'approve')
            ? 'Your organization has been verified. You can now receive and manage support cases.'
            : 'Your organization verification was rejected. Please contact support for more information.';
        
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, user_type, title, message, created_at) VALUES (?, 'ngo', ?, ?, NOW())");
        $stmt->execute([$ngoId, $notificationTitle, $notificationMessage]);
        
        $_SESSION['success'] = ($action === 'approve') 
            ? 'NGO has been verified successfully.' 
            : 'NGO verification has been rejected.';
        
        redirect('/admin/dashboard.php');
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    redirect('/admin/ngos.php');
}

$pageTitle = 'Verify NGO - ' . htmlspecialchars($ngo['organization_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Heal2Rise Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= url('/assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="bg-light saas-modern">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 bg-dark sidebar-dark min-vh-100">
                <div class="p-3 text-white">
                    <h5><i class="bi bi-shield-lock me-2"></i>Admin Panel</h5>
                </div>
                <nav class="nav flex-column px-2">
                    <a class="nav-link text-white-50" href="<?= url('/admin/dashboard.php') ?>">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                    <a class="nav-link text-white-50" href="<?= url('/admin/profile.php') ?>">
                        <i class="bi bi-person me-2"></i>My Profile
                    </a>
                    <a class="nav-link text-white-50" href="<?= url('/admin/users.php') ?>">
                        <i class="bi bi-people me-2"></i>Users
                    </a>
                    <a class="nav-link active text-white" href="<?= url('/admin/ngos.php') ?>">
                        <i class="bi bi-building me-2"></i>NGOs
                    </a>
                    <a class="nav-link text-white-50" href="<?= url('/admin/cases.php') ?>">
                        <i class="bi bi-folder me-2"></i>Cases
                    </a>
                    <a class="nav-link text-danger" href="<?= url('/logout.php') ?>">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <main id="main-content" class="col-md-9 col-lg-10 p-4 sidebar-content">
                <a href="<?= url('/admin/ngos.php') ?>" class="btn btn-outline-secondary btn-sm mb-3">
                    <i class="bi bi-arrow-left me-2"></i>Back to NGOs
                </a>
                
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-shield-exclamation me-2"></i>Review NGO Verification</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-3">Organization Details</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Organization Name:</th>
                                        <td><?= htmlspecialchars($ngo['organization_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Registration Number:</th>
                                        <td><?= htmlspecialchars($ngo['registration_number']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td><?= htmlspecialchars($ngo['email']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Phone:</th>
                                        <td><?= htmlspecialchars($ngo['phone']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Location:</th>
                                        <td><?= htmlspecialchars($ngo['city']) ?>, <?= htmlspecialchars($ngo['state']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Address:</th>
                                        <td><?= htmlspecialchars($ngo['address']) ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-3">Services & Specialization</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Specialization:</th>
                                        <td>
                                            <?php 
                                            $specs = explode(',', $ngo['specialization']);
                                            foreach ($specs as $spec): ?>
                                                <span class="badge bg-info me-1"><?= htmlspecialchars(trim($spec)) ?></span>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Capacity:</th>
                                        <td><?= $ngo['capacity'] ?> cases</td>
                                    </tr>
                                    <tr>
                                        <th>Registered On:</th>
                                        <td><?= formatDate($ngo['created_at']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Current Status:</th>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <?= ucfirst($ngo['verification_status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                                
                                <?php if ($ngo['description']): ?>
                                    <h6 class="text-muted mb-2 mt-3">Description</h6>
                                    <p class="small"><?= nl2br(htmlspecialchars($ngo['description'])) ?></p>
                                <?php endif; ?>
                                
                                <?php if ($ngo['certificate_doc']): ?>
                                    <a href="<?= url('/uploads/ngos/certificates/' . htmlspecialchars($ngo['certificate_doc'])) ?>" 
                                       target="_blank" class="btn btn-outline-primary btn-sm mt-2">
                                        <i class="bi bi-file-earmark-pdf me-2"></i>View Certificate
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="row">
                            <div class="col-12">
                                <h6 class="text-muted mb-3">Verification Decision</h6>
                                <form method="POST" class="d-flex gap-3">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    
                                    <button type="submit" name="action" value="approve" class="btn btn-success btn-lg" 
                                            onclick="return confirm('Are you sure you want to approve this NGO?')">
                                        <i class="bi bi-check-circle me-2"></i>Approve NGO
                                    </button>
                                    
                                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-lg"
                                            onclick="return confirm('Are you sure you want to reject this NGO?')">
                                        <i class="bi bi-x-circle me-2"></i>Reject NGO
                                    </button>
                                    
                                    <a href="<?= url('/admin/ngos.php') ?>" class="btn btn-outline-secondary btn-lg">
                                        <i class="bi bi-arrow-left me-2"></i>Cancel
                                    </a>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="<?= url('/assets/js/components.js') ?>"></script>
</body>
</html>
