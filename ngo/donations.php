<?php
/**
 * Heal2Rise Book - NGO Donations
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('ngo');

$ngoId = getCurrentUserId();
$db = getDB();

$stmt = $db->prepare("SELECT organization_name, logo, verification_status FROM ngos WHERE id = ?");
$stmt->execute([$ngoId]);
$ngo = $stmt->fetch();

$summary = getNGODonationSummary($ngoId);
$donations = getNGODonations($ngoId, 100);

$unreadCount = getUnreadNotificationCount('ngo', $ngoId);

$pageTitle = 'Donations';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="py-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="dashboard-sidebar">
                    <div class="text-center mb-4">
                        <img src="<?= url('/uploads/ngos/' . $ngo['logo']) ?>" alt="Logo" class="profile-avatar"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($ngo['organization_name']) ?>&background=4A90A4&color=fff'">
                        <h5 class="mb-1"><?= htmlspecialchars($ngo['organization_name']) ?></h5>
                        <span class="badge bg-<?= getStatusBadge($ngo['verification_status']) ?>">
                            <?= ucfirst($ngo['verification_status']) ?>
                        </span>
                    </div>

                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?= url('/ngo/dashboard.php') ?>"><i class="bi bi-speedometer2"></i>Dashboard</a>
                        <a class="nav-link" href="<?= url('/ngo/cases.php') ?>"><i class="bi bi-folder"></i>Assigned Cases</a>
                        <a class="nav-link" href="<?= url('/ngo/team.php') ?>"><i class="bi bi-people"></i>Team Members</a>
                        <a class="nav-link active" href="<?= url('/ngo/donations.php') ?>"><i class="bi bi-cash-stack"></i>Donations</a>
                        <a class="nav-link" href="<?= url('/ngo/notifications.php') ?>">
                            <i class="bi bi-bell"></i>Notifications
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger ms-2"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                        <hr>
                        <a class="nav-link text-danger" href="<?= url('/logout.php') ?>"><i class="bi bi-box-arrow-right"></i>Logout</a>
                    </nav>
                </div>
            </div>

            <div class="col-md-8 col-lg-9">
                <h2 class="page-title-soft">Donation Management</h2>

                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="stat-card success">
                            <h3>PKR <?= number_format(floatval($summary['total_received'] ?? 0), 0) ?></h3>
                            <p>Total Received</p>
                            <i class="bi bi-cash-coin stat-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card warning">
                            <h3>PKR <?= number_format(floatval($summary['total_pending'] ?? 0), 0) ?></h3>
                            <p>Pending Verification</p>
                            <i class="bi bi-hourglass-split stat-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card info">
                            <h3><?= intval($summary['total_donations'] ?? 0) ?></h3>
                            <p>Total Donations</p>
                            <i class="bi bi-heart stat-icon"></i>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-list-ul me-2"></i>Recent Donations</span>
                        <a href="<?= url('/donation.php?ngo_id=' . $ngoId) ?>" class="btn btn-sm btn-primary">Share Donation Link</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($donations)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-1 text-muted"></i>
                                <p class="text-muted mt-3 mb-0">No donations recorded yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Donation #</th>
                                            <th>Donor</th>
                                            <th>Case</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($donations as $donation): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($donation['donation_number']) ?></strong></td>
                                                <td>
                                                    <?= htmlspecialchars($donation['donor_name']) ?><br>
                                                    <small class="text-muted"><?= htmlspecialchars($donation['donor_email']) ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($donation['case_number'] ?? 'General') ?></td>
                                                <td><strong>PKR <?= number_format(floatval($donation['amount']), 2) ?></strong></td>
                                                <td><?= ucfirst(str_replace('_', ' ', $donation['payment_method'])) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= getStatusBadge($donation['payment_status']) ?>">
                                                        <?= ucfirst($donation['payment_status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= formatDate($donation['created_at'], 'M d, Y H:i') ?></td>
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
