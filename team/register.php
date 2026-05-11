<?php
/**
 * Heal2Rise Book - Team Member Registration
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Redirect if already logged in
if (isLoggedIn('team_member')) {
    redirect('/team/dashboard.php');
    exit;
}

$db = getDB();
$errors = [];

// Fetch verified and active NGOs for the dropdown
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request payload. Please refresh and try again.';
    } else {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $phone = sanitize($_POST['phone'] ?? '');
        $role = sanitize($_POST['role'] ?? '');
        $qualification = sanitize($_POST['qualification'] ?? '');
        $specialization = sanitize($_POST['specialization'] ?? '');
        $experience = intval($_POST['experience_years'] ?? 0);
        
        $category = sanitize($_POST['category'] ?? '');
        $validCategories = ['mental_health_counselor','psychiatrist','social_worker','rehabilitation_specialist','skill_development_trainer'];

        if (empty($fullName)) $errors[] = 'Full name is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
        if (empty($password)) $errors[] = 'Password is required.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters long.';
        if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';
        if (empty($role)) $errors[] = 'Please select your role.';
        if (!in_array($category, $validCategories)) $errors[] = 'Please select a valid professional category.';
        
        // Ensure email isn't taken
        if (empty($errors)) {
            $stmtCheck = $db->prepare("SELECT id FROM team_members WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetch()) {
                $errors[] = 'This email is already registered as a team member.';
            }
        }
        
        if (empty($errors)) {
            try {
                $hashedPassword = hashPassword($password);
                $stmtInsert = $db->prepare("
                    INSERT INTO team_members 
                    (full_name, email, password, phone, role, category, qualification, experience_years, specialization, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'inactive')
                ");
                $stmtInsert->execute([
                    $fullName,
                    $email,
                    $hashedPassword,
                    $phone,
                    $role,
                    $category,
                    $qualification,
                    $experience,
                    $specialization
                ]);
                
                // Notify ALL admins so they can review and approve
                $adminRows = $db->query("SELECT id FROM admins")->fetchAll();
                foreach ($adminRows as $admin) {
                    sendNotification(
                        'admin',
                        $admin['id'],
                        'New Team Member Registration',
                        "'{$fullName}' has registered as a " . ucfirst(str_replace('_', ' ', $category)) . ". Please review and approve their account.",
                        'info'
                    );
                }
                setFlashMessage('Registration completed successfully! Your account is pending admin approval before you can sign in.', 'success');
                redirect('/team/login.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Error saving your profile: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Team Member Registration';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="auth-wrapper">
    <div class="container">
        <div class="auth-card auth-card-lg">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-person-vcard display-4 mb-3"></i>
                    <h3>Join as a Team Member</h3>
                    <p>Register under an approved NGO</p>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger pb-0">
                                <ul class="mb-2">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= url('/team/register.php') ?>" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <h5 class="mb-4 border-bottom pb-2 fw-bold text-secondary">Account Information</h5>
                            
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="full_name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                                    </div>
                                    <div class="form-text mt-1"><small>Must be at least 8 characters</small></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                                    </div>
                                </div>
                            </div>

                            <h5 class="mb-4 mt-5 border-bottom pb-2 fw-bold text-secondary">Professional Details</h5>
                            
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="category" class="form-label fw-semibold">Professional Category <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-tags"></i></span>
                                        <select class="form-select" id="category" name="category" required>
                                            <option value="">-- Select Your Category --</option>
                                            <option value="mental_health_counselor" <?= (($_POST['category'] ?? '') === 'mental_health_counselor') ? 'selected' : '' ?>>Mental Health Counselor</option>
                                            <option value="psychiatrist"            <?= (($_POST['category'] ?? '') === 'psychiatrist')            ? 'selected' : '' ?>>Psychiatrist</option>
                                            <option value="social_worker"           <?= (($_POST['category'] ?? '') === 'social_worker')           ? 'selected' : '' ?>>Social Worker</option>
                                            <option value="rehabilitation_specialist" <?= (($_POST['category'] ?? '') === 'rehabilitation_specialist') ? 'selected' : '' ?>>Rehabilitation Specialist</option>
                                            <option value="skill_development_trainer" <?= (($_POST['category'] ?? '') === 'skill_development_trainer') ? 'selected' : '' ?>>Skill Development Trainer</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="role" class="form-label fw-semibold">Your Role <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-briefcase"></i></span>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="">-- Select Role --</option>
                                            <option value="counselor" <?= (isset($_POST['role']) && $_POST['role'] === 'counselor') ? 'selected' : '' ?>>Counselor</option>
                                            <option value="psychiatrist" <?= (isset($_POST['role']) && $_POST['role'] === 'psychiatrist') ? 'selected' : '' ?>>Psychiatrist</option>
                                            <option value="social_worker" <?= (isset($_POST['role']) && $_POST['role'] === 'social_worker') ? 'selected' : '' ?>>Social Worker</option>
                                            <option value="coordinator" <?= (isset($_POST['role']) && $_POST['role'] === 'coordinator') ? 'selected' : '' ?>>Coordinator</option>
                                            <option value="volunteer" <?= (isset($_POST['role']) && $_POST['role'] === 'volunteer') ? 'selected' : '' ?>>Volunteer</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <label for="phone" class="form-label fw-semibold">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                        <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-5 mb-3 mb-md-0">
                                    <label for="qualification" class="form-label fw-semibold">Qualification</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-mortarboard"></i></span>
                                        <input type="text" class="form-control" id="qualification" name="qualification" value="<?= htmlspecialchars($_POST['qualification'] ?? '') ?>" placeholder="e.g. MS Psychology">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label for="experience_years" class="form-label fw-semibold">Experience (Yrs)</label>
                                    <input type="number" class="form-control" id="experience_years" name="experience_years" min="0" max="50" value="<?= htmlspecialchars($_POST['experience_years'] ?? '0') ?>">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="specialization" class="form-label fw-semibold">Specialization</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-star"></i></span>
                                    <input type="text" class="form-control" id="specialization" name="specialization" value="<?= htmlspecialchars($_POST['specialization'] ?? '') ?>" placeholder="e.g. Cognitive Behavioral Therapy, Family Trauma">
                                </div>
                            </div>

                            <div class="d-grid mt-5">
                                <button type="submit" class="btn btn-primary btn-lg fw-bold shadow-sm">
                                    <i class="bi bi-person-plus-fill me-2"></i>Submit Registration
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer">
                        <p class="mb-0">Already configured by your NGO? <a href="<?= url('/team/login.php') ?>">Sign in here</a></p>
                    </div>
                </div>
            </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>