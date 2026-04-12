<?php
/**
 * Heal2Rise Book - Admin Profile Management
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('admin');

$adminId = getCurrentUserId();
$db = getDB();

// Get admin data
$stmt = $db->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

// Get pending counts for sidebar badges
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE verification_status = 'pending'");
$pendingUsers = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM ngos WHERE verification_status = 'pending'");
$pendingNgos = $stmt->fetchColumn();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? 'update_profile';

        if ($action === 'update_profile') {
            $fullName = sanitize($_POST['full_name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');

            if (empty($fullName) || strlen($fullName) < 3) {
                $errors[] = 'Please enter your full name (at least 3 characters).';
            }

            if (empty($email) || !validateEmail($email)) {
                $errors[] = 'Please enter a valid email address.';
            }

            // Check if email is taken by another admin
            if (empty($errors) && $email !== $admin['email']) {
                $stmt = $db->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
                $stmt->execute([$email, $adminId]);
                if ($stmt->fetch()) {
                    $errors[] = 'This email is already in use.';
                }
            }

            if (empty($errors)) {
                try {
                    $stmt = $db->prepare("UPDATE admins SET full_name = ?, email = ?, updated_at = NOW() WHERE id = ?");
                    $result = $stmt->execute([$fullName, $email, $adminId]);

                    if ($result) {
                        $_SESSION['user_data']['name'] = $fullName;
                        $success = true;
                        setFlashMessage('Profile updated successfully!', 'success');

                        // Refresh admin data
                        $stmt = $db->prepare("SELECT * FROM admins WHERE id = ?");
                        $stmt->execute([$adminId]);
                        $admin = $stmt->fetch();
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Failed to update profile. Please try again.';
                }
            }
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (!verifyPassword($currentPassword, $admin['password'])) {
                $errors[] = 'Current password is incorrect.';
            }

            if (!validatePassword($newPassword)) {
                $errors[] = 'New password must be at least 8 characters with uppercase, lowercase, and a number.';
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = 'New passwords do not match.';
            }

            if (empty($errors)) {
                try {
                    $hashedPassword = hashPassword($newPassword);
                    $stmt = $db->prepare("UPDATE admins SET password = ?, updated_at = NOW() WHERE id = ?");
                    $result = $stmt->execute([$hashedPassword, $adminId]);

                    if ($result) {
                        $success = true;
                        setFlashMessage('Password changed successfully!', 'success');
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Failed to change password. Please try again.';
                }
            }
        }
    }
}

$pageTitle = 'Admin Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="py-4">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 mb-4">
                <div class="dashboard-sidebar">
                    <div class="text-center mb-4">
                        <i class="bi bi-shield-lock-fill display-4 text-primary"></i>
                        <h5 class="mt-2 mb-0">Admin Panel</h5>
                        <small class="text-muted"><?= htmlspecialchars($admin['full_name']) ?></small>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?= url('/admin/dashboard.php') ?>">
                            <i class="bi bi-speedometer2"></i>Dashboard
                        </a>
                        <a class="nav-link active" href="<?= url('/admin/profile.php') ?>">
                            <i class="bi bi-person"></i>My Profile
                        </a>
                        <a class="nav-link" href="<?= url('/admin/users.php') ?>">
                            <i class="bi bi-people"></i>Users
                            <?php if ($pendingUsers > 0): ?>
                                <span class="badge bg-warning ms-auto"><?= $pendingUsers ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link" href="<?= url('/admin/ngos.php') ?>">
                            <i class="bi bi-building"></i>NGOs
                            <?php if ($pendingNgos > 0): ?>
                                <span class="badge bg-warning ms-auto"><?= $pendingNgos ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link" href="<?= url('/admin/cases.php') ?>">
                            <i class="bi bi-folder"></i>Cases
                        </a>
                        <hr>
                        <a class="nav-link text-danger" href="<?= url('/logout.php') ?>">
                            <i class="bi bi-box-arrow-right"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="card mb-4">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="profile-tab" data-toggle="tab" href="#profile" role="tab">
                                    <i class="bi bi-person me-2"></i>Profile Info
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="security-tab" data-toggle="tab" href="#security" role="tab">
                                    <i class="bi bi-shield-lock me-2"></i>Security
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= $error ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="tab-content" id="profileTabsContent">
                            <!-- Profile Info Tab -->
                            <div class="tab-pane fade show active" id="profile" role="tabpanel">
                                <form method="POST" class="needs-validation" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-3 mb-md-0">
                                            <label for="username" class="form-label">Username</label>
                                            <input type="text" class="form-control" id="username" 
                                                   value="<?= htmlspecialchars($admin['username']) ?>" disabled>
                                            <div class="form-text">Username cannot be changed.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email *</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?= htmlspecialchars($admin['email']) ?>" required>
                                            <div class="invalid-feedback">Please enter a valid email.</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?= htmlspecialchars($admin['full_name']) ?>" required minlength="3">
                                        <div class="invalid-feedback">Please enter your full name.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label text-muted">Account Created</label>
                                        <p class="form-control-plaintext"><?= formatDate($admin['created_at']) ?></p>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle me-2"></i>Update Profile
                                    </button>
                                </form>
                            </div>

                            <!-- Security Tab -->
                            <div class="tab-pane fade" id="security" role="tabpanel">
                                <h5 class="mb-4">Change Password</h5>
                                
                                <form method="POST" class="needs-validation" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password *</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            <button class="btn btn-outline-secondary password-toggle" type="button" data-target="current_password">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback">Please enter your current password.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password *</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                                   required minlength="8" data-strength="new-password-strength">
                                            <button class="btn btn-outline-secondary password-toggle" type="button" data-target="new_password">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <div class="progress mt-2 progress-thin">
                                            <div class="progress-bar" id="new-password-strength" role="progressbar"></div>
                                        </div>
                                        <div class="form-text">Min 8 chars with uppercase, lowercase, and number.</div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                        <input type="password" class="form-control" id="confirm_password" 
                                               name="confirm_password" required data-match="new_password">
                                        <div class="invalid-feedback">Passwords do not match.</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-shield-check me-2"></i>Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
