<?php
$pageTitle = "My Profile - Team Member";
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('team_member');

$db = getDB();
$userId = getCurrentUserId();
$userData = $_SESSION['user_data'] ?? [];

$unreadMessages = getUnreadMessageCount('team_member', $userId);

require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="py-4">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 mb-4">
                <div class="dashboard-sidebar">
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?= url('/team/dashboard.php') ?>">
                            <i class="bi bi-speedometer2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="<?= url('/team/cases.php') ?>">
                            <i class="bi bi-folder"></i>My Cases
                        </a>
                        <a class="nav-link" href="<?= url('/team/chat.php') ?>">
                            <i class="bi bi-chat-dots"></i>Messages
                            <?php if ($unreadMessages > 0): ?>
                                <span class="badge bg-danger ms-2"><?= $unreadMessages ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link active" href="<?= url('/team/profile.php') ?>">
                            <i class="bi bi-person"></i>My Profile
                        </a>
                        <hr>
                        <a class="nav-link text-danger" href="<?= url('/logout.php') ?>">
                            <i class="bi bi-box-arrow-left"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <h4 class="mb-4">My Profile</h4>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title border-bottom pb-3 mb-4">Profile Information</h5>
                        <table class="table table-borderless">
                            <tbody>
                                <tr>
                                    <th style="width: 30%" class="text-muted">Name</th>
                                    <td class="fw-medium"><?= htmlspecialchars($userData['name'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Email</th>
                                    <td class="fw-medium"><?= htmlspecialchars($userData['email'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Category</th>
                                    <td class="fw-medium">
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $userData['category'] ?? 'N/A'))) ?>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>