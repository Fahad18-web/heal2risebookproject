<?php
/**
 * Heal2Rise Book - User Registration
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Redirect if already logged in
if (isLoggedIn('user')) {
    redirect('/user/dashboard.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        // Sanitize inputs
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $fullName = sanitize($_POST['full_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $dateOfBirth = $_POST['date_of_birth'] ?? '';
        $gender = $_POST['gender'] ?? 'other';
        $address = sanitize($_POST['address'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $state = sanitize($_POST['state'] ?? '');
        $issueCategory = $_POST['issue_category'] ?? 'other';
        $issueDescription = sanitize($_POST['issue_description'] ?? '');
        $emergencyContactName = sanitize($_POST['emergency_contact_name'] ?? '');
        $emergencyContactPhone = sanitize($_POST['emergency_contact_phone'] ?? '');
        $privacyConsent = isset($_POST['privacy_consent']) ? 1 : 0;

        // Validation
        if (empty($username) || strlen($username) < 4) {
            $errors[] = 'Username must be at least 4 characters.';
        }
        
        if (!validateEmail($email)) {
            $errors[] = 'Please enter a valid email address.';
        }
        
        if (!validatePassword($password)) {
            $errors[] = 'Password must be at least 8 characters with uppercase, lowercase, and a number.';
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }
        
        if (empty($fullName) || strlen($fullName) < 3) {
            $errors[] = 'Please enter your full name.';
        }
        
        if (!empty($phone) && !validatePhone($phone)) {
            $errors[] = 'Please enter a valid phone number.';
        }
        
        if (!$privacyConsent) {
            $errors[] = 'You must agree to the privacy policy to register.';
        }

        // Check if username or email already exists
        if (empty($errors)) {
            $db = getDB();
            
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $errors[] = 'Username or email already exists.';
            }
        }

        // Register user if no errors
        if (empty($errors)) {
            try {
                $hashedPassword = hashPassword($password);
                
                $stmt = $db->prepare("INSERT INTO users (username, email, password, full_name, phone, date_of_birth, gender, address, city, state, issue_category, issue_description, emergency_contact_name, emergency_contact_phone, privacy_consent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $result = $stmt->execute([
                    $username,
                    $email,
                    $hashedPassword,
                    $fullName,
                    $phone,
                    $dateOfBirth ?: null,
                    $gender,
                    $address,
                    $city,
                    $state,
                    $issueCategory,
                    $issueDescription,
                    $emergencyContactName,
                    $emergencyContactPhone,
                    $privacyConsent
                ]);
                
                if ($result) {
                    $userId = $db->lastInsertId();
                    
                    // Send notification to admin
                    sendNotification('admin', 1, 'New User Registration', "A new user '{$fullName}' has registered and awaiting verification.", 'info');
                    
                    $success = true;
                    setFlashMessage('Registration successful! Please wait for admin verification before logging in.', 'success');
                }
            } catch (PDOException $e) {
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}

$pageTitle = 'User Registration';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="auth-wrapper">
    <div class="container">
        <div class="auth-card auth-card-lg">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-shield-lock display-4 mb-3"></i>
                    <h3>Create a Secure Account</h3>
                    <p>Your information is private and protected</p>
                    <div class="privacy-badge"><i class="bi bi-lock"></i>Confidential & Encrypted</div>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            Registration successful! Your account is pending verification. You will be notified once approved.
                        </div>
                        <div class="text-center">
                            <a href="<?= url('/user/login.php') ?>" class="btn btn-primary">Go to Login</a>
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

                        <div class="security-note">
                            <i class="bi bi-shield-check"></i>
                            <div>Only essential information is collected, securely stored, and used to connect you with trusted support.</div>
                        </div>

                        <form method="POST" class="needs-validation" novalidate aria-label="User registration form">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <!-- Account Information -->
                            <h6 class="text-primary mb-3"><i class="bi bi-person-badge me-2"></i>Account Information</h6>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                                           required minlength="4" pattern="[a-zA-Z0-9_]+">
                                    <div class="invalid-feedback">Username must be at least 4 characters (letters, numbers, underscore).</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="password" class="form-label">Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" 
                                               required minlength="8" data-strength="password-strength">
                                        <button class="btn btn-outline-secondary password-toggle" type="button" data-target="password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="progress mt-2 progress-thin">
                                        <div class="progress-bar" id="password-strength" role="progressbar"></div>
                                    </div>
                                    <div class="form-text">Min 8 chars with uppercase, lowercase, and number.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" required data-match="password">
                                    <div class="invalid-feedback">Passwords do not match.</div>
                                </div>
                            </div>

                            <!-- Personal Information -->
                            <h6 class="text-primary mb-3 mt-4"><i class="bi bi-person me-2"></i>Personal Information</h6>
                            
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required minlength="3">
                                <div class="invalid-feedback">Please enter your full name.</div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                           value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>" 
                                           max="<?= date('Y-m-d', strtotime('-13 years')) ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Gender</label>
                                <div class="d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="gender" id="gender_male" value="male"
                                               <?= ($_POST['gender'] ?? '') === 'male' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="gender_male">Male</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="gender" id="gender_female" value="female"
                                               <?= ($_POST['gender'] ?? '') === 'female' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="gender_female">Female</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="gender" id="gender_other" value="other"
                                               <?= ($_POST['gender'] ?? 'other') === 'other' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="gender_other">Other</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="state" class="form-label">State</label>
                                    <input type="text" class="form-control" id="state" name="state" 
                                           value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Issue Information -->
                            <h6 class="text-primary mb-3 mt-4"><i class="bi bi-chat-heart me-2"></i>How Can We Help?</h6>
                            
                            <div class="mb-3">
                                <label for="issue_category" class="form-label">Primary Concern *</label>
                                <select class="form-select" id="issue_category" name="issue_category" required>
                                    <option value="">Select your primary concern</option>
                                    <option value="depression" <?= ($_POST['issue_category'] ?? '') === 'depression' ? 'selected' : '' ?>>Depression</option>
                                    <option value="hopelessness" <?= ($_POST['issue_category'] ?? '') === 'hopelessness' ? 'selected' : '' ?>>Hopelessness / Anxiety</option>
                                    <option value="family_issues" <?= ($_POST['issue_category'] ?? '') === 'family_issues' ? 'selected' : '' ?>>Family Issues</option>
                                    <option value="marital_issues" <?= ($_POST['issue_category'] ?? '') === 'marital_issues' ? 'selected' : '' ?>>Marital Issues</option>
                                    <option value="other" <?= ($_POST['issue_category'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                                <div class="invalid-feedback">Please select your primary concern.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="issue_description" class="form-label">Brief Description (Optional)</label>
                                <textarea class="form-control" id="issue_description" name="issue_description" rows="3" 
                                          maxlength="500" data-counter="desc-counter"
                                          placeholder="Share a brief description of what you're going through..."><?= htmlspecialchars($_POST['issue_description'] ?? '') ?></textarea>
                                <div class="form-text" id="desc-counter">500 characters remaining</div>
                            </div>

                            <!-- Emergency Contact -->
                            <h6 class="text-primary mb-3 mt-4"><i class="bi bi-telephone me-2"></i>Emergency Contact (Optional)</h6>
                            
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="emergency_contact_name" class="form-label">Contact Name</label>
                                    <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                           value="<?= htmlspecialchars($_POST['emergency_contact_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="emergency_contact_phone" class="form-label">Contact Phone</label>
                                    <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                           value="<?= htmlspecialchars($_POST['emergency_contact_phone'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Privacy Consent -->
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="privacy_consent" name="privacy_consent" required
                                           <?= isset($_POST['privacy_consent']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="privacy_consent">
                                        I agree to the <a href="<?= url('/privacy.php') ?>" target="_blank">Privacy Policy</a> and consent to my data being processed confidentially. *
                                    </label>
                                    <div class="invalid-feedback">You must agree to the privacy policy.</div>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="bi bi-shield-check me-2"></i>
                                <strong>Privacy Guarantee:</strong> Your information is encrypted and will never be shared without your consent.
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-person-plus me-2"></i>Create Account
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="mb-0">Already have an account? <a href="<?= url('/user/login.php') ?>">Login here</a></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
