<?php
/**
 * Heal2Rise Book - NGO Team Members Management
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
$action = $_GET['action'] ?? 'list';
$memberId = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $formAction = $_POST['form_action'] ?? '';
        
        if ($formAction === 'add_member') {
            $fullName = sanitize($_POST['full_name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $role = $_POST['role'] ?? '';
            $qualification = sanitize($_POST['qualification'] ?? '');
            $experienceYears = intval($_POST['experience_years'] ?? 0);
            $specialization = sanitize($_POST['specialization'] ?? '');
            $maxCases = intval($_POST['max_cases'] ?? 10);
            
            if (empty($fullName)) {
                $errors[] = 'Please enter full name.';
            }
            
            if (!validateEmail($email)) {
                $errors[] = 'Please enter a valid email.';
            }
            
            if (empty($role)) {
                $errors[] = 'Please select a role.';
            }
            
            if (empty($errors)) {
                // Check if email exists
                $stmt = $db->prepare("SELECT id FROM team_members WHERE email = ? AND ngo_id = ?");
                $stmt->execute([$email, $ngoId]);
                if ($stmt->fetch()) {
                    $errors[] = 'A team member with this email already exists.';
                } else {
                    $stmt = $db->prepare("INSERT INTO team_members (ngo_id, full_name, email, phone, role, qualification, experience_years, specialization, max_cases) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([$ngoId, $fullName, $email, $phone, $role, $qualification, $experienceYears, $specialization, $maxCases]);
                    
                    if ($result) {
                        setFlashMessage('Team member added successfully!', 'success');
                        redirect('/ngo/team.php');
                        exit;
                    } else {
                        $errors[] = 'Failed to add team member.';
                    }
                }
            }
        } elseif ($formAction === 'edit_member') {
            $editId = intval($_POST['member_id'] ?? 0);
            $fullName = sanitize($_POST['full_name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $role = $_POST['role'] ?? '';
            $qualification = sanitize($_POST['qualification'] ?? '');
            $experienceYears = intval($_POST['experience_years'] ?? 0);
            $specialization = sanitize($_POST['specialization'] ?? '');
            $maxCases = intval($_POST['max_cases'] ?? 10);
            $isAvailable = isset($_POST['is_available']) ? 1 : 0;
            
            if (empty($fullName) || empty($email) || empty($role)) {
                $errors[] = 'Please fill in all required fields.';
            }
            
            if (empty($errors)) {
                $stmt = $db->prepare("UPDATE team_members SET full_name = ?, email = ?, phone = ?, role = ?, qualification = ?, experience_years = ?, specialization = ?, max_cases = ?, is_available = ? WHERE id = ? AND ngo_id = ?");
                $result = $stmt->execute([$fullName, $email, $phone, $role, $qualification, $experienceYears, $specialization, $maxCases, $isAvailable, $editId, $ngoId]);
                
                if ($result) {
                    setFlashMessage('Team member updated successfully!', 'success');
                    redirect('/ngo/team.php');
                    exit;
                } else {
                    $errors[] = 'Failed to update team member.';
                }
            }
        } elseif ($formAction === 'delete_member') {
            $deleteId = intval($_POST['member_id'] ?? 0);
            
            $stmt = $db->prepare("DELETE FROM team_members WHERE id = ? AND ngo_id = ?");
            $result = $stmt->execute([$deleteId, $ngoId]);
            
            if ($result) {
                setFlashMessage('Team member removed successfully!', 'success');
            }
            redirect('/ngo/team.php');
            exit;
        }
    }
}

// Get team members
$stmt = $db->prepare("SELECT * FROM team_members WHERE ngo_id = ? ORDER BY full_name");
$stmt->execute([$ngoId]);
$teamMembers = $stmt->fetchAll();

// Get member for editing
$editMember = null;
if ($action === 'edit' && $memberId) {
    $stmt = $db->prepare("SELECT * FROM team_members WHERE id = ? AND ngo_id = ?");
    $stmt->execute([$memberId, $ngoId]);
    $editMember = $stmt->fetch();
    
    if (!$editMember) {
        redirect('/ngo/team.php');
        exit;
    }
}

$pageTitle = 'Team Members';
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
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?= url('/ngo/dashboard.php') ?>">
                            <i class="bi bi-speedometer2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="<?= url('/ngo/profile.php') ?>">
                            <i class="bi bi-building"></i>Organization Profile
                        </a>
                        <a class="nav-link" href="<?= url('/ngo/cases.php') ?>">
                            <i class="bi bi-folder"></i>Assigned Cases
                        </a>
                        <a class="nav-link active" href="<?= url('/ngo/team.php') ?>">
                            <i class="bi bi-people"></i>Team Members
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
                <?php if ($action === 'add' || $action === 'edit'): ?>
                    <!-- Add/Edit Form -->
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-person-<?= $action === 'add' ? 'plus' : 'gear' ?> me-2"></i>
                            <?= $action === 'add' ? 'Add New Team Member' : 'Edit Team Member' ?>
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

                            <form method="POST" class="needs-validation" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="form_action" value="<?= $action === 'add' ? 'add_member' : 'edit_member' ?>">
                                <?php if ($editMember): ?>
                                    <input type="hidden" name="member_id" value="<?= $editMember['id'] ?>">
                                <?php endif; ?>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="full_name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?= htmlspecialchars($editMember['full_name'] ?? $_POST['full_name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?= htmlspecialchars($editMember['email'] ?? $_POST['email'] ?? '') ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?= htmlspecialchars($editMember['phone'] ?? $_POST['phone'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="role" class="form-label">Role *</label>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="">Select Role</option>
                                            <option value="counselor" <?= ($editMember['role'] ?? '') === 'counselor' ? 'selected' : '' ?>>Counselor</option>
                                            <option value="psychiatrist" <?= ($editMember['role'] ?? '') === 'psychiatrist' ? 'selected' : '' ?>>Psychiatrist</option>
                                            <option value="social_worker" <?= ($editMember['role'] ?? '') === 'social_worker' ? 'selected' : '' ?>>Social Worker</option>
                                            <option value="coordinator" <?= ($editMember['role'] ?? '') === 'coordinator' ? 'selected' : '' ?>>Coordinator</option>
                                            <option value="volunteer" <?= ($editMember['role'] ?? '') === 'volunteer' ? 'selected' : '' ?>>Volunteer</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="qualification" class="form-label">Qualification</label>
                                        <input type="text" class="form-control" id="qualification" name="qualification" 
                                               value="<?= htmlspecialchars($editMember['qualification'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="experience_years" class="form-label">Years of Experience</label>
                                        <input type="number" class="form-control" id="experience_years" name="experience_years" 
                                               value="<?= $editMember['experience_years'] ?? 0 ?>" min="0">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="specialization" class="form-label">Specialization</label>
                                        <input type="text" class="form-control" id="specialization" name="specialization" 
                                               value="<?= htmlspecialchars($editMember['specialization'] ?? '') ?>"
                                               placeholder="e.g., Depression, Anxiety, Family Therapy">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="max_cases" class="form-label">Max Cases</label>
                                        <input type="number" class="form-control" id="max_cases" name="max_cases" 
                                               value="<?= $editMember['max_cases'] ?? 10 ?>" min="1" max="50">
                                    </div>
                                </div>
                                
                                <?php if ($editMember): ?>
                                    <div class="mb-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_available" name="is_available"
                                                   <?= $editMember['is_available'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_available">Available for new cases</label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle me-2"></i><?= $action === 'add' ? 'Add Member' : 'Update Member' ?>
                                    </button>
                                    <a href="<?= url('/ngo/team.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Team List -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-people-fill me-2"></i>Team Members (<?= count($teamMembers) ?>)</span>
                            <a href="<?= url('/ngo/team.php?action=add') ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-person-plus me-2"></i>Add Member
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($teamMembers)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-person-plus display-1 text-muted"></i>
                                    <p class="text-muted mt-3">No team members yet.</p>
                                    <a href="<?= url('/ngo/team.php?action=add') ?>" class="btn btn-primary">Add First Member</a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Role</th>
                                                <th>Email</th>
                                                <th>Cases</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($teamMembers as $member): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($member['full_name']) ?>&background=4A90A4&color=fff" 
                                                                 class="rounded-circle me-2" width="40" height="40">
                                                            <div>
                                                                <strong><?= htmlspecialchars($member['full_name']) ?></strong>
                                                                <?php if ($member['qualification']): ?>
                                                                    <br><small class="text-muted"><?= htmlspecialchars($member['qualification']) ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?= ucfirst(str_replace('_', ' ', $member['role'])) ?></td>
                                                    <td><?= htmlspecialchars($member['email']) ?></td>
                                                    <td><?= $member['cases_assigned'] ?>/<?= $member['max_cases'] ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $member['is_available'] ? 'success' : 'secondary' ?>">
                                                            <?= $member['is_available'] ? 'Available' : 'Busy' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="<?= url('/ngo/team.php?action=edit&id=' . $member['id']) ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to remove this team member?');">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="form_action" value="delete_member">
                                                            <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
