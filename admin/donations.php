<?php
/**
 * Heal2Rise Book - Admin Donations
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('admin');

$adminId = getCurrentUserId();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        setFlashMessage('Invalid security token.', 'danger');
        redirect('/admin/donations.php');
        exit;
    }

    $donationId = intval($_POST['donation_id'] ?? 0);
    $newStatus = sanitize($_POST['new_status'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    $result = updateDonationPaymentStatus($donationId, $newStatus, $adminId, $notes);
    if ($result['success']) {
        setFlashMessage('Donation status updated successfully.', 'success');
    } else {
        setFlashMessage($result['error'] ?? 'Failed to update donation status.', 'danger');
    }
    redirect('/admin/donations.php');
    exit;
}

$statusFilter = sanitize($_GET['status'] ?? '');
if (!in_array($statusFilter, ['pending', 'completed', 'failed', 'refunded'], true)) {
    $statusFilter = null;
}

$donations = getAllDonations($statusFilter, 200);

$summaryQuery = "SELECT
    COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END), 0) AS total_completed,
    COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END), 0) AS total_pending,
    COUNT(*) AS total_count,
    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) AS pending_count
    FROM donations";
$summary = $db->query($summaryQuery)->fetch();

$pageTitle = 'Donations';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="py-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-4">
                <div class="dashboard-sidebar">
                    <div class="text-center mb-4">
                        <i class="bi bi-shield-lock-fill display-4 text-primary"></i>
                        <h5 class="mt-2 mb-0">Admin Panel</h5>
                        <small class="text-muted"><?= htmlspecialchars($_SESSION['user_data']['name'] ?? 'Administrator') ?></small>
                    </div>

                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?= url('/admin/dashboard.php') ?>"><i class="bi bi-speedometer2"></i>Dashboard</a>
                        <a class="nav-link" href="<?= url('/admin/users.php') ?>"><i class="bi bi-people"></i>Users</a>
                        <a class="nav-link" href="<?= url('/admin/ngos.php') ?>"><i class="bi bi-building"></i>NGOs</a>
                        <a class="nav-link" href="<?= url('/admin/cases.php') ?>"><i class="bi bi-folder"></i>Cases</a>
                        <a class="nav-link active" href="<?= url('/admin/donations.php') ?>"><i class="bi bi-cash-stack"></i>Donations</a>
                        <hr>
                        <a class="nav-link text-danger" href="<?= url('/logout.php') ?>"><i class="bi bi-box-arrow-right"></i>Logout</a>
                    </nav>
                </div>
            </div>

            <div class="col-md-9 col-lg-10">
                <h4 class="mb-4">Donation Oversight</h4>

                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stat-card success">
                            <h3>PKR <?= number_format(floatval($summary['total_completed'] ?? 0), 0) ?></h3>
                            <p>Verified Amount</p>
                            <i class="bi bi-cash-coin stat-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card warning">
                            <h3>PKR <?= number_format(floatval($summary['total_pending'] ?? 0), 0) ?></h3>
                            <p>Pending Amount</p>
                            <i class="bi bi-hourglass-split stat-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card info">
                            <h3><?= intval($summary['total_count'] ?? 0) ?></h3>
                            <p>Total Donations</p>
                            <i class="bi bi-heart stat-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card primary">
                            <h3><?= intval($summary['pending_count'] ?? 0) ?></h3>
                            <p>Needs Action</p>
                            <i class="bi bi-exclamation-circle stat-icon"></i>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-list-check me-2"></i>Donations</span>
                        <div>
                            <a class="btn btn-sm <?= $statusFilter === null ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= url('/admin/donations.php') ?>">All</a>
                            <a class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-warning' : 'btn-outline-warning' ?>" href="<?= url('/admin/donations.php?status=pending') ?>">Pending</a>
                            <a class="btn btn-sm <?= $statusFilter === 'completed' ? 'btn-success' : 'btn-outline-success' ?>" href="<?= url('/admin/donations.php?status=completed') ?>">Completed</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($donations)): ?>
                            <p class="text-muted text-center py-4 mb-0">No donation records found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Donation #</th>
                                            <th>NGO</th>
                                            <th>Donor</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th style="min-width: 260px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($donations as $donation): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($donation['donation_number']) ?></strong><br>
                                                    <small class="text-muted">Case: <?= htmlspecialchars($donation['case_number'] ?? 'General') ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($donation['organization_name']) ?></td>
                                                <td>
                                                    <?= htmlspecialchars($donation['donor_name']) ?><br>
                                                    <small class="text-muted"><?= htmlspecialchars($donation['donor_email']) ?></small>
                                                </td>
                                                <td><strong>PKR <?= number_format(floatval($donation['amount']), 2) ?></strong></td>
                                                <td>
                                                    <span class="badge bg-<?= getStatusBadge($donation['payment_status']) ?>">
                                                        <?= ucfirst($donation['payment_status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= formatDate($donation['created_at'], 'M d, Y H:i') ?></td>
                                                <td>
                                                    <form method="POST" class="d-flex gap-2">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="donation_id" value="<?= intval($donation['id']) ?>">
                                                        <select class="form-select form-select-sm" name="new_status" required>
                                                            <?php foreach (['pending', 'completed', 'failed', 'refunded'] as $status): ?>
                                                                <option value="<?= $status ?>" <?= $donation['payment_status'] === $status ? 'selected' : '' ?>>
                                                                    <?= ucfirst($status) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <input type="text" class="form-control form-control-sm" name="notes" placeholder="Note (optional)" maxlength="255">
                                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                                    </form>
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
