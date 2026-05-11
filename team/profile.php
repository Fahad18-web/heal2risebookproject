<?php
$pageTitle = "My Profile - Team Member";
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('team_member');

$db = getDB();
$userId = getCurrentUserId();
$userData = $_SESSION['user_data'] ?? [];

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5 mt-4" id="main-content">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= url('/team/dashboard.php') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">My Profile</li>
                </ol>
            </nav>
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h2 mb-0">My Profile</h1>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 mx-auto">
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
<?php require_once __DIR__ . '/../includes/footer.php'; ?>