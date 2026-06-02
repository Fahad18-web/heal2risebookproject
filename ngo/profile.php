<?php
/**
 * Heal2Rise Book - NGO Profile Management
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('ngo');

$ngoId = getCurrentUserId();
$db = getDB();

// Get NGO data
$stmt = $db->prepare("SELECT * FROM ngos WHERE id = ?");
$stmt->execute([$ngoId]);
$ngo = $stmt->fetch();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? 'update_profile';
        
        if ($action === 'update_profile') {
            $contactPerson = sanitize($_POST['contact_person'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $website = sanitize($_POST['website'] ?? '');
            $address = sanitize($_POST['address'] ?? '');
            $city = sanitize($_POST['city'] ?? '');
            $state = sanitize($_POST['state'] ?? '');
            $specialization = $_POST['specialization'] ?? [];
            $description = sanitize($_POST['description'] ?? '');
            $capacity = intval($_POST['capacity'] ?? 50);
            
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
            
            if (empty($errors)) {
                try {
                    $specializationString = implode(',', $specialization);
                    
                    $stmt = $db->prepare("UPDATE ngos SET contact_person = ?, phone = ?, website = ?, address = ?, city = ?, state = ?, specialization = ?, description = ?, capacity = ?, updated_at = NOW() WHERE id = ?");
                    
                    $result = $stmt->execute([
                        $contactPerson,
                        $phone,
                        $website,
                        $address,
                        $city,
                        $state,
                        $specializationString,
                        $description,
                        $capacity,
                        $ngoId
                    ]);
                    
                    if ($result) {
                        $success = true;
                        setFlashMessage('Profile updated successfully!', 'success');
                        
                        // Refresh data
                        $stmt = $db->prepare("SELECT * FROM ngos WHERE id = ?");
                        $stmt->execute([$ngoId]);
                        $ngo = $stmt->fetch();
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Failed to update profile.';
                }
            }
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (!verifyPassword($currentPassword, $ngo['password'])) {
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
                    $stmt = $db->prepare("UPDATE ngos SET password = ?, updated_at = NOW() WHERE id = ?");
                    $result = $stmt->execute([$hashedPassword, $ngoId]);
                    
                    if ($result) {
                        $success = true;
                        setFlashMessage('Password changed successfully!', 'success');
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Failed to change password.';
                }
            }
        } elseif ($action === 'upload_logo') {
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $result = uploadFile($_FILES['logo'], ['jpg', 'jpeg', 'png'], 2097152, 'uploads/ngos/');
                
                if ($result['success']) {
                    if ($ngo['logo'] !== 'default_ngo.png') {
                        @unlink(__DIR__ . '/../uploads/ngos/' . $ngo['logo']);
                    }
                    
                    $stmt = $db->prepare("UPDATE ngos SET logo = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$result['filename'], $ngoId]);
                    
                    $ngo['logo'] = $result['filename'];
                    $success = true;
                    setFlashMessage('Logo updated successfully!', 'success');
                } else {
                    $errors[] = $result['error'];
                }
            } else {
                $errors[] = 'Please select an image to upload.';
            }
        }
    }
}

$currentSpecializations = explode(',', $ngo['specialization'] ?? '');

$unreadCount = getUnreadNotificationCount('ngo', $ngoId);

$pageTitle = 'Organization Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="py-4">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
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
                        <a class="nav-link" href="<?= url('/ngo/dashboard.php') ?>">
                            <i class="bi bi-speedometer2"></i>Dashboard
                        </a>
                        <a class="nav-link active" href="<?= url('/ngo/profile.php') ?>">
                            <i class="bi bi-building"></i>Organization Profile
                        </a>
                        <a class="nav-link" href="<?= url('/ngo/cases.php') ?>">
                            <i class="bi bi-folder"></i>Assigned Cases
                        </a>
                        <a class="nav-link" href="<?= url('/ngo/team.php') ?>">
                            <i class="bi bi-people"></i>Team Members
                        </a>
                        <a class="nav-link" href="<?= url('/ngo/donations.php') ?>">
                            <i class="bi bi-cash-stack"></i>Donations
                        </a>
                        <a class="nav-link" href="<?= url('/ngo/notifications.php') ?>">
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
                    <i class="bi bi-shield-lock"></i>
                    <div>Organization profile data is securely handled and used only for verified case collaboration and care coordination.</div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="profile-tab" data-toggle="tab" href="#profile" role="tab">
                                    <i class="bi bi-building me-2"></i>Organization Info
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="security-tab" data-toggle="tab" href="#security" role="tab">
                                    <i class="bi bi-shield-lock me-2"></i>Security
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="logo-tab" data-toggle="tab" href="#logo" role="tab">
                                    <i class="bi bi-image me-2"></i>Logo
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
                                            <label class="form-label">Organization Name</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($ngo['organization_name']) ?>" disabled>
                                            <div class="form-text">Cannot be changed after registration.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Registration Number</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($ngo['registration_number']) ?>" disabled>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-3 mb-md-0">
                                            <label for="contact_person" class="form-label">Contact Person *</label>
                                            <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                                   value="<?= htmlspecialchars($ngo['contact_person']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="phone" class="form-label">Phone *</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   value="<?= htmlspecialchars($ngo['phone']) ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="website" class="form-label">Website</label>
                                        <input type="url" class="form-control" id="website" name="website" 
                                               value="<?= htmlspecialchars($ngo['website'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address *</label>
                                        <textarea class="form-control" id="address" name="address" rows="2" required><?= htmlspecialchars($ngo['address']) ?></textarea>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-3 mb-md-0">
                                            <label for="city" class="form-label">City *</label>
                                            <input type="text" class="form-control" id="city" name="city" 
                                                   value="<?= htmlspecialchars($ngo['city']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="state" class="form-label">State *</label>
                                            <input type="text" class="form-control" id="state" name="state" 
                                                   value="<?= htmlspecialchars($ngo['state']) ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Specializations *</label>
                                        <div class="row">
                                            <?php 
                                            $specs = ['depression', 'hopelessness', 'family_issues', 'marital_issues', 'skill_development', 'rehabilitation'];
                                            foreach ($specs as $spec): 
                                            ?>
                                                <div class="col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="specialization[]" 
                                                               value="<?= $spec ?>" id="spec_<?= $spec ?>"
                                                               <?= in_array($spec, $currentSpecializations) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="spec_<?= $spec ?>">
                                                            <?= ucfirst(str_replace('_', ' ', $spec)) ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="capacity" class="form-label">Maximum Cases Capacity</label>
                                        <input type="number" class="form-control" id="capacity" name="capacity" 
                                               value="<?= $ngo['capacity'] ?>" min="10" max="500">
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="description" class="form-label">About Organization</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($ngo['description'] ?? '') ?></textarea>
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
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password *</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-shield-check me-2"></i>Change Password
                                    </button>
                                </form>
                            </div>

                            <!-- Logo Tab -->
                            <div class="tab-pane fade" id="logo" role="tabpanel">
                                <h5 class="mb-4">Organization Logo</h5>
                                
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="upload_logo">
                                    
                                    <div class="text-center mb-4">
                                        <img src="<?= url('/uploads/ngos/' . $ngo['logo']) ?>" alt="Logo" class="profile-avatar avatar-lg" 
                                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($ngo['organization_name']) ?>&background=4A90A4&color=fff&size=150'">
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="logo" class="form-label">Select Logo</label>
                                        <input type="file" class="form-control" id="logo" name="logo" accept="image/jpeg,image/png">
                                        <div class="form-text">Max 2MB. JPG, PNG formats.</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-upload me-2"></i>Upload Logo
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
