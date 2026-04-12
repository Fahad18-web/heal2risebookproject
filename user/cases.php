<?php
/**
 * Heal2Rise Book - User My Cases
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('user');

$userId = getCurrentUserId();
$db = getDB();

// Get all user's cases
$stmt = $db->prepare("
    SELECT c.*, n.organization_name, n.phone as ngo_phone, n.email as ngo_email,
           tm.full_name as team_member_name, tm.role as team_member_role
    FROM cases c
    LEFT JOIN ngos n ON c.ngo_id = n.id
    LEFT JOIN team_members tm ON c.team_member_id = tm.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$userId]);
$cases = $stmt->fetchAll();

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
                        <i class="bi bi-person-circle display-4 text-primary"></i>
                        <h6 class="mt-2 mb-0"><?= htmlspecialchars($_SESSION['user_data']['name'] ?? 'User') ?></h6>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?= url('/user/dashboard.php') ?>">
                            <i class="bi bi-speedometer2"></i>Dashboard
                        </a>
                        <a class="nav-link active" href="<?= url('/user/cases.php') ?>">
                            <i class="bi bi-folder"></i>My Cases
                        </a>
                        <a class="nav-link" href="<?= url('/user/request-support.php') ?>">
                            <i class="bi bi-plus-circle"></i>Request Support
                        </a>
                        <a class="nav-link" href="<?= url('/user/profile.php') ?>">
                            <i class="bi bi-person"></i>Profile
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
                    <h4 class="mb-0">My Support Cases</h4>
                    <a href="<?= url('/user/request-support.php') ?>" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-2"></i>Request New Support
                    </a>
                </div>

                <?php if (empty($cases)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-folder-x display-1 text-muted mb-3"></i>
                            <h5>No Cases Yet</h5>
                            <p class="text-muted">You haven't requested any support yet.</p>
                            <a href="<?= url('/user/request-support.php') ?>" class="btn btn-primary">
                                <i class="bi bi-plus-lg me-2"></i>Request Support Now
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($cases as $case): ?>
                            <div class="col-md-6 col-lg-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span><strong><?= htmlspecialchars($case['case_number']) ?></strong></span>
                                        <span class="badge bg-<?= getStatusBadge($case['status']) ?>">
                                            <?= ucfirst(str_replace('_', ' ', $case['status'])) ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <span class="badge bg-info me-2"><?= ucfirst(str_replace('_', ' ', $case['issue_category'])) ?></span>
                                            <span class="badge bg-<?= $case['severity_level'] === 'high' || $case['severity_level'] === 'critical' ? 'danger' : ($case['severity_level'] === 'medium' ? 'warning' : 'success') ?>">
                                                <?= ucfirst($case['severity_level']) ?> Severity
                                            </span>
                                        </div>
                                        
                                        <p class="text-muted small mb-3">
                                            <?= htmlspecialchars(substr($case['description'], 0, 150)) ?>...
                                        </p>
                                        
                                        <hr>
                                        
                                        <?php if ($case['organization_name']): ?>
                                            <p class="mb-1"><strong>NGO:</strong> <?= htmlspecialchars($case['organization_name']) ?></p>
                                        <?php else: ?>
                                            <p class="mb-1 text-muted"><em>NGO assignment pending...</em></p>
                                        <?php endif; ?>
                                        
                                        <?php if ($case['team_member_name']): ?>
                                            <p class="mb-1"><strong>Counselor:</strong> <?= htmlspecialchars($case['team_member_name']) ?> (<?= ucfirst($case['team_member_role']) ?>)</p>
                                        <?php endif; ?>
                                        
                                        <p class="mb-0 text-muted small"><strong>Created:</strong> <?= formatDate($case['created_at']) ?></p>
                                    </div>
                                    <div class="card-footer">
                                        <a href="<?= url('/user/case-details.php?id=' . $case['id']) ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye me-1"></i>View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
