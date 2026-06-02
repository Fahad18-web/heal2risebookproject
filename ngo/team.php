<?php
/**
 * Heal2Rise Book - NGO Team Members Management
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

$errors = [];

// Get team members assigned to this NGO's cases
$stmt = $db->prepare("
    SELECT DISTINCT tm.id, tm.full_name, tm.category, tm.email, tm.phone, tm.qualification 
    FROM team_members tm 
    JOIN cases c ON c.team_member_id = tm.id 
    WHERE c.ngo_id = ? 
    ORDER BY tm.full_name
");
$stmt->execute([$ngoId]);
$teamMembers = $stmt->fetchAll();

$pageTitle = 'Assigned Team Members';
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
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?= url('/ngo/dashboard.php') ?>">
                            <i class="bi bi-speedometer2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="<?= url('/ngo/profile.php') ?>">
                            <i class="bi bi-building"></i>Organization Profile
                        </a>
                        <a class="nav-link" href="<?= url('/ngo/cases.php') ?>">
                            <i class="bi bi-folder"></i>Assigned Cases
                        </a>
                        <a class="nav-link active" href="<?= url('/ngo/team.php') ?>">
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
                <!-- Team List -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-people-fill me-2"></i>Assigned Team Members (<?= count($teamMembers) ?>)</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($teamMembers)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-people display-1 text-muted"></i>
                                <p class="text-muted mt-3">No team members are currently assigned to your cases.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Category</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($teamMembers as $member): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($member['full_name']) ?>&background=4A90A4&color=fff" 
                                                             class="rounded-circle me-3" width="40" height="40" alt="Avatar">
                                                        <div>
                                                            <strong class="d-block text-dark"><?= htmlspecialchars($member['full_name']) ?></strong>
                                                            <?php if (!empty($member['qualification'])): ?>
                                                                <small class="text-muted"><?= htmlspecialchars($member['qualification']) ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">
                                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $member['category'] ?? 'Unspecified'))) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="mailto:<?= htmlspecialchars($member['email']) ?>" class="text-decoration-none">
                                                        <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($member['email']) ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php if (!empty($member['phone'])): ?>
                                                        <a href="tel:<?= htmlspecialchars($member['phone']) ?>" class="text-decoration-none text-muted">
                                                            <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($member['phone']) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
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
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
