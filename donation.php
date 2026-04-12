<?php
/**
 * Heal2Rise Book - Donation Page
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

$db = getDB();
$userId = isLoggedIn('user') ? getCurrentUserId() : null;

$selectedCaseId = intval($_GET['case_id'] ?? 0);
$selectedNgoId = intval($_GET['ngo_id'] ?? 0);
$selectedCase = null;

if ($selectedCaseId > 0) {
    $stmt = $db->prepare("SELECT c.id, c.case_number, c.ngo_id, n.organization_name
                          FROM cases c
                          JOIN ngos n ON c.ngo_id = n.id
                          WHERE c.id = ? AND n.is_verified = 1 AND n.status = 'active'");
    $stmt->execute([$selectedCaseId]);
    $selectedCase = $stmt->fetch();

    if (!$selectedCase) {
        setFlashMessage('Invalid case selected for donation.', 'danger');
        redirect('/donation.php');
        exit;
    }

    $selectedNgoId = intval($selectedCase['ngo_id']);
}

$stmt = $db->query("SELECT id, organization_name, city, state
                    FROM ngos
                    WHERE is_verified = 1 AND status = 'active'
                    ORDER BY organization_name ASC");
$ngos = $stmt->fetchAll();

$relatedCases = [];
if ($selectedNgoId > 0) {
    $stmt = $db->prepare("SELECT id, case_number
                          FROM cases
                          WHERE ngo_id = ?
                          ORDER BY created_at DESC
                          LIMIT 30");
    $stmt->execute([$selectedNgoId]);
    $relatedCases = $stmt->fetchAll();
}

$defaultName = '';
$defaultEmail = '';
$defaultPhone = '';
if ($userId) {
    $stmt = $db->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();
    if ($currentUser) {
        $defaultName = $currentUser['full_name'];
        $defaultEmail = $currentUser['email'];
        $defaultPhone = $currentUser['phone'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        setFlashMessage('Invalid security token. Please try again.', 'danger');
        redirect('/donation.php');
        exit;
    }

    $ngoId = intval($_POST['ngo_id'] ?? 0);
    $caseId = intval($_POST['case_id'] ?? 0);

    $result = createDonation([
        'ngo_id' => $ngoId,
        'case_id' => $caseId > 0 ? $caseId : null,
        'donor_user_id' => $userId,
        'donor_name' => sanitize($_POST['donor_name'] ?? ''),
        'donor_email' => sanitize($_POST['donor_email'] ?? ''),
        'donor_phone' => sanitize($_POST['donor_phone'] ?? ''),
        'amount' => sanitize($_POST['amount'] ?? ''),
        'purpose' => sanitize($_POST['purpose'] ?? ''),
        'payment_method' => sanitize($_POST['payment_method'] ?? ''),
        'transaction_reference' => sanitize($_POST['transaction_reference'] ?? ''),
        'notes' => sanitize($_POST['notes'] ?? '')
    ]);

    if ($result['success']) {
        if ($result['payment_status'] === 'completed') {
            setFlashMessage('Thank you for your donation. Reference: ' . $result['donation_number'], 'success');
        } else {
            setFlashMessage('Donation submitted and pending verification. Reference: ' . $result['donation_number'], 'warning');
        }
        redirect('/donation.php');
        exit;
    }

    setFlashMessage($result['error'] ?? 'Failed to submit donation.', 'danger');
    redirect('/donation.php');
    exit;
}

$pageTitle = 'Donate';
require_once __DIR__ . '/includes/header.php';
?>

<main id="main-content" class="py-4">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold mb-2"><i class="bi bi-heart-fill text-danger me-2"></i>Support Healing Through Donation</h2>
                            <p class="text-muted mb-0">Your contribution helps NGOs provide counseling, rehabilitation, and critical support services.</p>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Select NGO <span class="text-danger">*</span></label>
                                    <select class="form-select" name="ngo_id" required <?= $selectedCase ? 'disabled' : '' ?>>
                                        <option value="">Choose an NGO</option>
                                        <?php foreach ($ngos as $ngo): ?>
                                            <option value="<?= $ngo['id'] ?>" <?= $selectedNgoId === intval($ngo['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($ngo['organization_name']) ?> (<?= htmlspecialchars($ngo['city']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($selectedCase): ?>
                                        <input type="hidden" name="ngo_id" value="<?= intval($selectedNgoId) ?>">
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Related Case (Optional)</label>
                                    <select class="form-select" name="case_id" <?= $selectedCase ? 'disabled' : '' ?>>
                                        <option value="0">General donation</option>
                                        <?php foreach ($relatedCases as $case): ?>
                                            <option value="<?= $case['id'] ?>" <?= $selectedCaseId === intval($case['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($case['case_number']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($selectedCase): ?>
                                        <input type="hidden" name="case_id" value="<?= intval($selectedCaseId) ?>">
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Your Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="donor_name" maxlength="120" value="<?= htmlspecialchars($defaultName) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="donor_email" maxlength="120" value="<?= htmlspecialchars($defaultEmail) ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="donor_phone" maxlength="20" value="<?= htmlspecialchars($defaultPhone) ?>" placeholder="03001234567">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Amount (PKR) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="amount" min="1" step="0.01" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                    <select class="form-select" name="payment_method" required>
                                        <option value="">Choose payment method</option>
                                        <option value="card">Card</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="mobile_wallet">Mobile Wallet</option>
                                        <option value="cash">Cash</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Transaction Reference</label>
                                    <input type="text" class="form-control" name="transaction_reference" maxlength="120" placeholder="Optional reference number">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Purpose</label>
                                <input type="text" class="form-control" name="purpose" maxlength="255" placeholder="Example: Therapy sessions, emergency support">
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="3" placeholder="Optional message to NGO"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-credit-card me-2"></i>Submit Donation
                            </button>
                        </form>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Donations submitted via bank transfer or wallet are marked pending until verification.
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
