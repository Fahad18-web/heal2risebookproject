<?php
/**
 * Heal2Rise Book - Request Support
 * User can request to connect with an NGO
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('user');

$userId = getCurrentUserId();
$db = getDB();

// Check if user is verified
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($user['verification_status'] !== 'approved') {
    setFlashMessage('Your account must be verified before requesting support.', 'warning');
    redirect('/user/dashboard.php');
    exit;
}

// Check if user has active case
$stmt = $db->prepare("SELECT id FROM cases WHERE user_id = ? AND status NOT IN ('closed', 'cancelled')");
$stmt->execute([$userId]);
if ($stmt->fetch()) {
    setFlashMessage('You already have an active case. Please wait for it to be resolved.', 'warning');
    redirect('/user/dashboard.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $issueCategory = $_POST['issue_category'] ?? '';
        $severity = $_POST['severity'] ?? 'medium';
        $description = sanitize($_POST['description'] ?? '');
        
        if (empty($issueCategory)) {
            $errors[] = 'Please select your primary concern.';
        }
        
        if (empty($description) || strlen($description) < 20) {
            $errors[] = 'Please provide a description of at least 20 characters.';
        }
        
        if (empty($errors)) {
            $result = createAndAssignCase($userId, $issueCategory, $description, $severity);
            
            if ($result['success']) {
                $success = true;
                setFlashMessage('Your support request has been submitted. Case #' . $result['case_number'] . ' created.', 'success');
            } else {
                $errors[] = $result['error'];
            }
        }
    }
}

$pageTitle = 'Request Support';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="py-4">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="card">
                    <div class="card-header bg-gradient-primary text-white text-center py-4">
                        <i class="bi bi-heart-pulse-fill display-4 mb-2"></i>
                        <h3 class="mb-1">Request Support</h3>
                        <p class="mb-0 opacity-75">We're here to help you on your journey to healing</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($success): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-check-circle-fill text-success display-1"></i>
                                <h4 class="mt-3">Support Request Submitted!</h4>
                                <p class="text-muted">Your case has been created and assigned to a suitable NGO. 
                                   You will be notified when a team member is assigned to your case.</p>
                                <a href="<?= url('/user/dashboard.php') ?>" class="btn btn-primary mt-3">
                                    <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                                </a>
                            </div>
                        <?php else: ?>
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= $error ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <div class="alert alert-info mb-4">
                                <i class="bi bi-shield-check me-2"></i>
                                <strong>Privacy Protected:</strong> Your information is kept strictly confidential and only shared with assigned support professionals.
                            </div>

                            <form method="POST" class="needs-validation" novalidate aria-label="Request support form">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                
                                <div class="mb-4">
                                    <label for="issue_category" class="form-label">What are you dealing with? *</label>
                                    <select class="form-select form-select-lg" id="issue_category" name="issue_category" required>
                                        <option value="">Select your primary concern</option>
                                        <option value="depression">Depression</option>
                                        <option value="hopelessness">Hopelessness / Anxiety</option>
                                        <option value="family_issues">Family Issues</option>
                                        <option value="marital_issues">Marital Issues</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <div class="invalid-feedback">Please select your primary concern.</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="severity" class="form-label">How would you rate the severity?</label>
                                    <div class="d-flex gap-3 flex-wrap">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="severity" id="severity_low" value="low">
                                            <label class="form-check-label" for="severity_low">
                                                <span class="badge bg-success">Low</span> I'm managing but need support
                                            </label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="severity" id="severity_medium" value="medium" checked>
                                            <label class="form-check-label" for="severity_medium">
                                                <span class="badge bg-warning">Medium</span> It's affecting my daily life
                                            </label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="severity" id="severity_high" value="high">
                                            <label class="form-check-label" for="severity_high">
                                                <span class="badge bg-danger">High</span> I need immediate help
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="description" class="form-label">Tell us more about your situation *</label>
                                    <textarea class="form-control" id="description" name="description" rows="5" 
                                              required minlength="20" maxlength="1000" data-counter="desc-counter"
                                              placeholder="Please share what you're going through. This helps us connect you with the right support..."></textarea>
                                    <div class="form-text" id="desc-counter">1000 characters remaining</div>
                                    <div class="invalid-feedback">Please provide at least 20 characters.</div>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Important:</strong> If you're in immediate danger or having thoughts of self-harm, please call emergency services or a crisis helpline immediately.
                                </div>
                                
                                <div class="d-flex gap-3">
                                    <button type="submit" class="btn btn-primary btn-lg flex-grow-1">
                                        <i class="bi bi-send me-2"></i>Submit Request
                                    </button>
                                    <a href="<?= url('/user/dashboard.php') ?>" class="btn btn-outline-secondary btn-lg">Cancel</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
