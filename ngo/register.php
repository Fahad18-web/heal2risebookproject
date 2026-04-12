<?php
/**
 * Heal2Rise Book - NGO Registration
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Redirect if already logged in
if (isLoggedIn('ngo')) {
    redirect('/ngo/dashboard.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        // Sanitize inputs
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $organizationName = sanitize($_POST['organization_name'] ?? '');
        $registrationNumber = sanitize($_POST['registration_number'] ?? '');
        $contactPerson = sanitize($_POST['contact_person'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $website = sanitize($_POST['website'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $state = sanitize($_POST['state'] ?? '');
        $specialization = $_POST['specialization'] ?? [];
        $description = sanitize($_POST['description'] ?? '');
        $capacity = intval($_POST['capacity'] ?? 50);

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
        
        if (empty($organizationName)) {
            $errors[] = 'Please enter organization name.';
        }
        
        if (empty($registrationNumber)) {
            $errors[] = 'Please enter registration number.';
        }
        
        if (empty($contactPerson)) {
            $errors[] = 'Please enter contact person name.';
        }
        
        if (!validatePhone($phone)) {
            $errors[] = 'Please enter a valid phone number.';
        }
        
        if (empty($address) || empty($city) || empty($state)) {
            $errors[] = 'Please fill in complete address details.';
        }
        
        if (empty($specialization)) {
            $errors[] = 'Please select at least one specialization.';
        }

        // Check if username, email, or registration number already exists
        if (empty($errors)) {
            $db = getDB();
            
            $stmt = $db->prepare("SELECT id FROM ngos WHERE username = ? OR email = ? OR registration_number = ?");
            $stmt->execute([$username, $email, $registrationNumber]);
            
            if ($stmt->fetch()) {
                $errors[] = 'Username, email, or registration number already exists.';
            }
        }

        // Handle certificate upload
        $certificateDoc = null;
        if (isset($_FILES['certificate_doc']) && $_FILES['certificate_doc']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['certificate_doc'], ['pdf', 'jpg', 'jpeg', 'png'], 5242880, 'uploads/ngos/certificates/');
            if ($result['success']) {
                $certificateDoc = $result['filename'];
            } else {
                $errors[] = 'Certificate upload failed: ' . $result['error'];
            }
        }

        // Register NGO if no errors
        if (empty($errors)) {
            try {
                $hashedPassword = hashPassword($password);
                $specializationString = implode(',', $specialization);
                
                $stmt = $db->prepare("INSERT INTO ngos (username, email, password, organization_name, registration_number, contact_person, phone, website, address, city, state, specialization, description, certificate_doc, capacity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $result = $stmt->execute([
                    $username,
                    $email,
                    $hashedPassword,
                    $organizationName,
                    $registrationNumber,
                    $contactPerson,
                    $phone,
                    $website,
                    $address,
                    $city,
                    $state,
                    $specializationString,
                    $description,
                    $certificateDoc,
                    $capacity
                ]);
                
                if ($result) {
                    $ngoId = $db->lastInsertId();
                    
                    // Send notification to admin
                    sendNotification('admin', 1, 'New NGO Registration', "NGO '{$organizationName}' has registered and awaiting verification.", 'info');
                    
                    $success = true;
                    setFlashMessage('Registration successful! Please wait for admin verification.', 'success');
                }
            } catch (PDOException $e) {
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}

$pageTitle = 'NGO Registration';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="auth-wrapper">
    <div class="container">
        <div class="auth-card auth-card-lg">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-shield-check display-4 mb-3"></i>
                    <h3>Create a Secure NGO Account</h3>
                    <p>Your information is private and protected</p>
                    <div class="privacy-badge"><i class="bi bi-lock"></i>Verified & Confidential Onboarding</div>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            Registration successful! Your organization is pending verification by our admin team.
                        </div>
                        <div class="text-center">
                            <a href="<?= url('/ngo/login.php') ?>" class="btn btn-primary">Go to Login</a>
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
                            <i class="bi bi-shield-lock"></i>
                            <div>Organization details are securely reviewed by admins and only used for safe case assignment workflows.</div>
                        </div>

                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate aria-label="NGO registration form">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <!-- Account Information -->
                            <h6 class="text-primary mb-3"><i class="bi bi-key me-2"></i>Account Information</h6>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                                           required minlength="4" pattern="[a-zA-Z0-9_]+">
                                    <div class="invalid-feedback">Username must be at least 4 characters.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Official Email *</label>
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
                                </div>
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" required data-match="password">
                                    <div class="invalid-feedback">Passwords do not match.</div>
                                </div>
                            </div>

                            <!-- Organization Details -->
                            <h6 class="text-primary mb-3 mt-4"><i class="bi bi-building me-2"></i>Organization Details</h6>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="organization_name" class="form-label">Organization Name *</label>
                                    <input type="text" class="form-control" id="organization_name" name="organization_name" 
                                           value="<?= htmlspecialchars($_POST['organization_name'] ?? '') ?>" required>
                                    <div class="invalid-feedback">Please enter organization name.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="registration_number" class="form-label">Registration Number *</label>
                                    <input type="text" class="form-control" id="registration_number" name="registration_number" 
                                           value="<?= htmlspecialchars($_POST['registration_number'] ?? '') ?>" required>
                                    <div class="invalid-feedback">Please enter registration number.</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="contact_person" class="form-label">Contact Person *</label>
                                    <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                           value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>" required>
                                    <div class="invalid-feedback">Please enter contact person name.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                                    <div class="invalid-feedback">Please enter a valid phone number.</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="website" class="form-label">Website (Optional)</label>
                                <input type="url" class="form-control" id="website" name="website" 
                                       value="<?= htmlspecialchars($_POST['website'] ?? '') ?>" placeholder="https://">
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address *</label>
                                <textarea class="form-control" id="address" name="address" rows="2" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                                <div class="invalid-feedback">Please enter address.</div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="city" class="form-label">City *</label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" required>
                                    <div class="invalid-feedback">Please enter city.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="state" class="form-label">State *</label>
                                    <input type="text" class="form-control" id="state" name="state" 
                                           value="<?= htmlspecialchars($_POST['state'] ?? '') ?>" required>
                                    <div class="invalid-feedback">Please enter state.</div>
                                </div>
                            </div>

                            <!-- Specialization -->
                            <h6 class="text-primary mb-3 mt-4"><i class="bi bi-award me-2"></i>Specialization Areas</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Select areas of expertise *</label>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="specialization[]" value="depression" id="spec_depression"
                                                   <?= in_array('depression', $_POST['specialization'] ?? []) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="spec_depression">Depression</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="specialization[]" value="hopelessness" id="spec_hopelessness"
                                                   <?= in_array('hopelessness', $_POST['specialization'] ?? []) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="spec_hopelessness">Hopelessness/Anxiety</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="specialization[]" value="family_issues" id="spec_family"
                                                   <?= in_array('family_issues', $_POST['specialization'] ?? []) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="spec_family">Family Issues</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="specialization[]" value="marital_issues" id="spec_marital"
                                                   <?= in_array('marital_issues', $_POST['specialization'] ?? []) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="spec_marital">Marital Issues</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="specialization[]" value="skill_development" id="spec_skill"
                                                   <?= in_array('skill_development', $_POST['specialization'] ?? []) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="spec_skill">Skill Development</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="specialization[]" value="rehabilitation" id="spec_rehab"
                                                   <?= in_array('rehabilitation', $_POST['specialization'] ?? []) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="spec_rehab">Rehabilitation</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="capacity" class="form-label">Maximum Cases Capacity</label>
                                    <input type="number" class="form-control" id="capacity" name="capacity" 
                                           value="<?= htmlspecialchars($_POST['capacity'] ?? '50') ?>" min="10" max="500">
                                </div>
                                <div class="col-md-6">
                                    <label for="certificate_doc" class="form-label">Registration Certificate</label>
                                    <input type="file" class="form-control" id="certificate_doc" name="certificate_doc" 
                                           accept=".pdf,.jpg,.jpeg,.png">
                                    <div class="form-text">PDF, JPG, PNG (Max 5MB)</div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="description" class="form-label">About Your Organization</label>
                                <textarea class="form-control" id="description" name="description" rows="3" 
                                          maxlength="500" data-counter="desc-counter"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                <div class="form-text" id="desc-counter">500 characters remaining</div>
                            </div>

                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Your organization will be verified by our admin team before approval. This typically takes 1-3 business days.
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-building-add me-2"></i>Register Organization
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="mb-0">Already registered? <a href="<?= url('/ngo/login.php') ?>">Login here</a></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
