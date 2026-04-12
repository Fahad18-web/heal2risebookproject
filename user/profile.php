<?php
/**
 * Heal2Rise Book - User Profile Management
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require user login
requireLogin('user');

$userId = getCurrentUserId();
$db = getDB();

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? 'update_profile';
        
        if ($action === 'update_profile') {
            // Profile update
            $fullName = sanitize($_POST['full_name'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $dateOfBirth = $_POST['date_of_birth'] ?? '';
            $gender = $_POST['gender'] ?? 'other';
            $address = sanitize($_POST['address'] ?? '');
            $city = sanitize($_POST['city'] ?? '');
            $state = sanitize($_POST['state'] ?? '');
            $emergencyContactName = sanitize($_POST['emergency_contact_name'] ?? '');
            $emergencyContactPhone = sanitize($_POST['emergency_contact_phone'] ?? '');
            
            // Validation
            if (empty($fullName) || strlen($fullName) < 3) {
                $errors[] = 'Please enter your full name.';
            }
            
            if (!empty($phone) && !validatePhone($phone)) {
                $errors[] = 'Please enter a valid phone number.';
            }
            
            if (empty($errors)) {
                try {
                    $stmt = $db->prepare("UPDATE users SET full_name = ?, phone = ?, date_of_birth = ?, gender = ?, address = ?, city = ?, state = ?, emergency_contact_name = ?, emergency_contact_phone = ?, updated_at = NOW() WHERE id = ?");
                    
                    $result = $stmt->execute([
                        $fullName,
                        $phone,
                        $dateOfBirth ?: null,
                        $gender,
                        $address,
                        $city,
                        $state,
                        $emergencyContactName,
                        $emergencyContactPhone,
                        $userId
                    ]);
                    
                    if ($result) {
                        // Update session data
                        $_SESSION['user_data']['name'] = $fullName;
                        
                        $success = true;
                        setFlashMessage('Profile updated successfully!', 'success');
                        
                        // Refresh user data
                        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        $user = $stmt->fetch();
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Failed to update profile. Please try again.';
                }
            }
        } elseif ($action === 'change_password') {
            // Password change
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (!verifyPassword($currentPassword, $user['password'])) {
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
                    $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                    $result = $stmt->execute([$hashedPassword, $userId]);
                    
                    if ($result) {
                        $success = true;
                        setFlashMessage('Password changed successfully!', 'success');
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Failed to change password. Please try again.';
                }
            }
        } elseif ($action === 'upload_picture') {
            // Profile picture upload
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $result = uploadFile($_FILES['profile_picture'], ['jpg', 'jpeg', 'png'], 2097152, 'uploads/users/');
                
                if ($result['success']) {
                    // Delete old picture if not default
                    if ($user['profile_picture'] !== 'default.png') {
                        @unlink(__DIR__ . '/../uploads/users/' . $user['profile_picture']);
                    }
                    
                    $stmt = $db->prepare("UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$result['filename'], $userId]);
                    
                    $user['profile_picture'] = $result['filename'];
                    $success = true;
                    setFlashMessage('Profile picture updated successfully!', 'success');
                } else {
                    $errors[] = $result['error'];
                }
            } else {
                $errors[] = 'Please select an image to upload.';
            }
        }
    }
}

$pageTitle = 'My Profile';
$unreadCount = getUnreadNotificationCount('user', $userId);
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="py-4">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="dashboard-sidebar">
                    <div class="text-center mb-4">
                        <img src="<?= url('/uploads/users/' . $user['profile_picture']) ?>" alt="Profile" class="profile-avatar" 
                             id="profile-preview"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['full_name']) ?>&background=4A90A4&color=fff'">
                        <h5 class="mb-1"><?= htmlspecialchars($user['full_name']) ?></h5>
                        <p class="text-muted small mb-2">@<?= htmlspecialchars($user['username']) ?></p>
                        <span class="badge bg-<?= getStatusBadge($user['verification_status']) ?>">
                            <?= ucfirst($user['verification_status']) ?>
                        </span>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?= url('/user/dashboard.php') ?>">
                            <i class="bi bi-speedometer2"></i>Dashboard
                        </a>
                        <a class="nav-link active" href="<?= url('/user/profile.php') ?>">
                            <i class="bi bi-person"></i>My Profile
                        </a>
                        <a class="nav-link" href="<?= url('/user/cases.php') ?>">
                            <i class="bi bi-folder"></i>My Cases
                        </a>
                        <a class="nav-link" href="<?= url('/user/notifications.php') ?>">
                            <i class="bi bi-bell"></i>Notifications
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger ms-2"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                        <hr>
                        <a class="nav-link text-danger" href="<?= url('/logout.php') ?>">
                            <i class="bi bi-box-arrow-right"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-8 col-lg-9">
                <div class="security-note">
                    <i class="bi bi-lock"></i>
                    <div>Your profile details are protected and only visible to authorized staff involved in your support journey.</div>
                </div>

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
                            <li class="nav-item">
                                <a class="nav-link" id="picture-tab" data-toggle="tab" href="#picture" role="tab">
                                    <i class="bi bi-image me-2"></i>Profile Picture
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
                                                   value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                            <div class="form-text">Username cannot be changed.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" 
                                                   value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                            <div class="form-text">Email cannot be changed.</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?= htmlspecialchars($user['full_name']) ?>" required minlength="3">
                                        <div class="invalid-feedback">Please enter your full name.</div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-3 mb-md-0">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                                   value="<?= $user['date_of_birth'] ?? '' ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Gender</label>
                                        <div class="d-flex gap-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="gender" id="gender_male" value="male"
                                                       <?= ($user['gender'] ?? '') === 'male' ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="gender_male">Male</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="gender" id="gender_female" value="female"
                                                       <?= ($user['gender'] ?? '') === 'female' ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="gender_female">Female</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="gender" id="gender_other" value="other"
                                                       <?= ($user['gender'] ?? 'other') === 'other' ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="gender_other">Other</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-3 mb-md-0">
                                            <label for="city" class="form-label">City</label>
                                            <input type="text" class="form-control" id="city" name="city" 
                                                   value="<?= htmlspecialchars($user['city'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="state" class="form-label">State</label>
                                            <input type="text" class="form-control" id="state" name="state" 
                                                   value="<?= htmlspecialchars($user['state'] ?? '') ?>">
                                        </div>
                                    </div>
                                    
                                    <h6 class="text-primary mt-4 mb-3"><i class="bi bi-telephone me-2"></i>Emergency Contact</h6>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6 mb-3 mb-md-0">
                                            <label for="emergency_contact_name" class="form-label">Contact Name</label>
                                            <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                                   value="<?= htmlspecialchars($user['emergency_contact_name'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="emergency_contact_phone" class="form-label">Contact Phone</label>
                                            <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                                   value="<?= htmlspecialchars($user['emergency_contact_phone'] ?? '') ?>">
                                        </div>
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

                            <!-- Profile Picture Tab -->
                            <div class="tab-pane fade" id="picture" role="tabpanel">
                                <h5 class="mb-4">Update Profile Picture</h5>
                                
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="upload_picture">
                                    
                                    <div class="text-center mb-4">
                                        <img src="<?= url('/uploads/users/' . $user['profile_picture']) ?>" alt="Profile" 
                                            class="profile-avatar mb-3 avatar-lg" id="picture-preview"
                                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['full_name']) ?>&background=4A90A4&color=fff&size=150'">
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="profile_picture" class="form-label">Select Image</label>
                                        <input type="file" class="form-control" id="profile_picture" name="profile_picture" 
                                               accept="image/jpeg,image/png" data-preview="picture-preview">
                                        <div class="form-text">Max file size: 2MB. Allowed formats: JPG, PNG</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-upload me-2"></i>Upload Picture
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Info Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-info-circle me-2"></i>Account Information
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Account Status:</strong> 
                                    <span class="badge bg-<?= getStatusBadge($user['status']) ?>">
                                        <?= ucfirst($user['status']) ?>
                                    </span>
                                </p>
                                <p><strong>Verification:</strong> 
                                    <span class="badge bg-<?= getStatusBadge($user['verification_status']) ?>">
                                        <?= ucfirst($user['verification_status']) ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Member Since:</strong> <?= formatDate($user['created_at']) ?></p>
                                <p><strong>Last Updated:</strong> <?= formatDate($user['updated_at']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
