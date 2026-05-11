<?php
/**
 * Heal2Rise Book - Admin Portal: Create Team Member
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('admin');

$pageTitle = 'Manage Team Members';
$db = getDB();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('Invalid request. Please try again.', 'danger');
    } else {
        $action = $_POST['action'] ?? '';

        // ── Approve self-registered pending team member ─────────────
        if ($action === 'approve') {
            $memberId = intval($_POST['member_id'] ?? 0);
            if ($memberId) {
                $db->prepare("UPDATE team_members SET status = 'active' WHERE id = ? AND status = 'inactive'")->execute([$memberId]);
                $mem = $db->prepare("SELECT full_name FROM team_members WHERE id = ?");
                $mem->execute([$memberId]);
                $memData = $mem->fetch();
                sendNotification('team_member', $memberId, 'Account Approved',
                    'Your registration has been approved by the admin. You can now log in.', 'success');
                setFlashMessage("Team member '{$memData['full_name']}' approved successfully.", 'success');
            }
            redirect('/admin/create-team-member.php');
            exit;
        }

        // ── Reject self-registered pending team member ──────────────
        if ($action === 'reject') {
            $memberId = intval($_POST['member_id'] ?? 0);
            if ($memberId) {
                $mem = $db->prepare("SELECT full_name FROM team_members WHERE id = ? AND status = 'inactive'");
                $mem->execute([$memberId]);
                $memData = $mem->fetch();
                if ($memData) {
                    $db->prepare("DELETE FROM team_members WHERE id = ? AND status = 'inactive'")->execute([$memberId]);
                    setFlashMessage("Registration for '{$memData['full_name']}' rejected and removed.", 'warning');
                }
            }
            redirect('/admin/create-team-member.php');
            exit;
        }

        // ── Deactivate Team Member ──────────────────────────────────
        if ($action === 'deactivate') {
            $memberId = intval($_POST['member_id'] ?? 0);
            
            $stmt = $db->prepare("UPDATE team_members SET status = 'inactive' WHERE id = ?");
            if ($stmt->execute([$memberId])) {
                setFlashMessage('Team member deactivated successfully.', 'success');
            } else {
                setFlashMessage('Failed to deactivate team member.', 'danger');
            }
            redirect('/admin/create-team-member.php');
            exit;
        }
        
        // ── Create Team Member ──────────────────────────────────────
        if ($action === 'create') {
            $fullName      = sanitize($_POST['full_name']      ?? '');
            $email         = sanitize($_POST['email']          ?? '');
            $phone         = sanitize($_POST['phone']          ?? '');
            $password      = $_POST['password']                ?? '';
            $category      = $_POST['category']                ?? '';
            $qualification = sanitize($_POST['qualification']  ?? '');

            $errors = [];

            // ── Validation ──────────────────────────────────────────
            if (empty($fullName) || empty($email) || empty($password) || empty($category)) {
                $errors[] = 'Please fill in all required fields (Name, Email, Password, Category).';
            }

            if (!validateEmail($email)) {
                $errors[] = 'Please enter a valid email address.';
            }

            if ($phone && !validatePhone($phone)) {
                $errors[] = 'Please enter a valid phone number.';
            }

            if (!validatePassword($password)) {
                $errors[] = 'Password must be at least 8 characters with 1 uppercase, 1 lowercase, and 1 number.';
            }

            // ── Check email already exists ──────────────────────────
            if (empty($errors)) {
                $stmt = $db->prepare("SELECT id FROM team_members WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors[] = 'This email is already registered to a team member.';
                }
            }

            // ── Check category slot already filled ──────────────────
            if (empty($errors)) {
                $stmt = $db->prepare("SELECT id FROM team_members WHERE category = ? AND status = 'active'");
                $stmt->execute([$category]);
                if ($stmt->fetch()) {
                    $errors[] = 'There is already an active team member for this category. Deactivate them first.';
                }
            }

            // ── Save to database ────────────────────────────────────
            if (empty($errors)) {
                $hashedPassword = hashPassword($password);

                // ✅ FIX — ngo_id COMPLETELY REMOVED from INSERT
                // ngo_id column dropped in migration — team members are independent
                // role column kept for backward compat but maps from category
                $roleMap = [
                    'mental_health_counselor'   => 'counselor',
                    'psychiatrist'              => 'psychiatrist',
                    'social_worker'             => 'social_worker',
                    'rehabilitation_specialist' => 'coordinator',
                    'skill_development_trainer' => 'volunteer',
                ];
                $role = $roleMap[$category] ?? 'counselor';

                $stmt = $db->prepare("
                    INSERT INTO team_members 
                        (full_name, email, phone, password, role, qualification, category, status) 
                    VALUES 
                        (?, ?, ?, ?, ?, ?, ?, 'active')
                ");

                if ($stmt->execute([$fullName, $email, $phone, $hashedPassword, $role, $qualification, $category])) {
                    // Notify admin log
                    sendNotification(
                        'admin', 
                        getCurrentUserId(), 
                        'Team Member Created', 
                        "New team member '{$fullName}' added as " . ucfirst(str_replace('_', ' ', $category)) . ".", 
                        'success'
                    );
                    setFlashMessage("Team member '{$fullName}' created successfully.", 'success');
                    redirect('/admin/create-team-member.php');
                    exit;
                } else {
                    $errors[] = 'Database error. Failed to create team member. Please try again.';
                }
            }

            // ── Save errors to session and redirect back ────────────
            if (!empty($errors)) {
                $_SESSION['form_errors'] = $errors;
                $_SESSION['form_data']   = $_POST;
                redirect('/admin/create-team-member.php');
                exit;
            }
        }
    }
}

// ── Load page data ──────────────────────────────────────────────────
$errors   = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data']   ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

// Active members
$stmt = $db->prepare("SELECT id, category, full_name, email, status FROM team_members WHERE status = 'active'");
$stmt->execute();
$activeMembers = $stmt->fetchAll();

// Pending (self-registered, awaiting approval)
$stmtPending = $db->prepare("SELECT id, full_name, email, category, role, qualification, created_at FROM team_members WHERE status = 'inactive' ORDER BY created_at DESC");
$stmtPending->execute();
$pendingMembers = $stmtPending->fetchAll();

// Map by category
$filledSlots = [];
foreach ($activeMembers as $member) {
    $filledSlots[$member['category']] = $member;
}

// 5 fixed categories
$categories = [
    'mental_health_counselor'   => 'Mental Health Counselor',
    'psychiatrist'              => 'Psychiatrist',
    'social_worker'             => 'Social Worker',
    'rehabilitation_specialist' => 'Rehabilitation Specialist',
    'skill_development_trainer' => 'Skill Dev Trainer',
];

// Empty slots only (for create form dropdown)
$emptyCategories = [];
foreach ($categories as $key => $label) {
    if (!isset($filledSlots[$key])) {
        $emptyCategories[$key] = $label;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 mb-4">
            <div class="dashboard-sidebar">
                <div class="text-center mb-4">
                    <i class="bi bi-shield-lock-fill display-4 text-primary"></i>
                    <h5 class="mt-2 mb-0">Admin Panel</h5>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="<?= url('/admin/dashboard.php') ?>">
                        <i class="bi bi-speedometer2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="<?= url('/admin/users.php') ?>">
                        <i class="bi bi-people"></i>Users
                    </a>
                    <a class="nav-link" href="<?= url('/admin/ngos.php') ?>">
                        <i class="bi bi-building"></i>NGOs
                    </a>
                    <a class="nav-link" href="<?= url('/admin/cases.php') ?>">
                        <i class="bi bi-folder"></i>Cases
                    </a>
                    <a class="nav-link active" href="<?= url('/admin/create-team-member.php') ?>">
                        <i class="bi bi-people-fill"></i>Team Members
                    </a>
                    <a class="nav-link" href="<?= url('/admin/chat.php') ?>">
                        <i class="bi bi-chat-dots"></i>Messages
                    </a>
                    <hr>
                    <a class="nav-link text-danger" href="<?= url('/logout.php') ?>">
                        <i class="bi bi-box-arrow-right"></i>Logout
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <main class="col-md-9 col-lg-10 px-md-4 py-4">
            <h2 class="mb-4">
                <i class="bi bi-people-fill me-2 text-primary"></i>
                Manage Team Members
            </h2>

            <!-- Flash Message -->
            <?php $flash = getFlashMessage(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
                    <?= htmlspecialchars($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Validation Errors -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- ── SECTION 1: 5 Category Slots ─────────────────────── -->
            <h5 class="mb-3">Team Slot Status</h5>
            <div class="row mb-4">
                <?php
                // Map pending members by category for slot display
                $pendingByCategory = [];
                foreach ($pendingMembers as $pm) {
                    $pendingByCategory[$pm['category']][] = $pm;
                }
                foreach ($categories as $key => $label):
                    $isFilled  = isset($filledSlots[$key]);
                    $hasPending = !empty($pendingByCategory[$key]);
                ?>
                    <div class="col-12 col-sm-6 col-md-4 col-lg mb-3">
                        <div class="card shadow-sm h-100 border-<?= $isFilled ? 'success' : ($hasPending ? 'warning' : 'secondary') ?>">
                            <div class="card-body text-center py-3 px-2 text-break d-flex flex-column justify-content-center">
                                <p class="text-muted small text-uppercase mb-2 fw-bold"><?= $label ?></p>
                                <?php if ($isFilled): ?>
                                    <div class="text-success fw-bold mb-1">
                                        <i class="bi bi-person-check-fill fs-4"></i>
                                    </div>
                                    <p class="mb-0 small fw-semibold"><?= htmlspecialchars($filledSlots[$key]['full_name']) ?></p>
                                    <span class="badge bg-success mt-1">Active</span>
                                <?php elseif ($hasPending): ?>
                                    <div class="text-warning fw-bold mb-1">
                                        <i class="bi bi-person-exclamation fs-4"></i>
                                    </div>
                                    <p class="mb-0 small fw-semibold text-warning"><?= count($pendingByCategory[$key]) ?> Pending</p>
                                    <span class="badge bg-warning text-dark mt-1">Awaiting Approval</span>
                                <?php else: ?>
                                    <div class="text-secondary mb-1">
                                        <i class="bi bi-person-dash fs-4"></i>
                                    </div>
                                    <p class="mb-0 small text-muted">No member assigned</p>
                                    <span class="badge bg-secondary mt-1">Vacant</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ── SECTION 2: Pending Approvals ──────────────────────── -->
            <?php if (!empty($pendingMembers)): ?>
            <div class="card shadow-sm mb-4 border-warning">
                <div class="card-header bg-warning text-dark d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history me-2"></i>Pending Registrations
                        <span class="badge bg-dark ms-2"><?= count($pendingMembers) ?></span>
                    </h5>
                    <small>These members registered themselves and are awaiting your approval.</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Category</th>
                                <th>Qualification</th>
                                <th>Registered</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingMembers as $pm): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($pm['full_name']) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($pm['email']) ?></td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $pm['category']))) ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars($pm['qualification'] ?: '—') ?></td>
                                <td class="text-muted small"><?= $pm['created_at'] ? date('d M Y', strtotime($pm['created_at'])) : '—' ?></td>
                                <td class="text-end">
                                    <!-- Approve -->
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="member_id" value="<?= $pm['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success me-1"
                                            onclick="return confirm('Approve <?= htmlspecialchars($pm['full_name']) ?>?')">
                                            <i class="bi bi-check-lg me-1"></i>Approve
                                        </button>
                                    </form>
                                    <!-- Reject -->
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="member_id" value="<?= $pm['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Reject and delete registration for <?= htmlspecialchars($pm['full_name']) ?>? This cannot be undone.')">
                                            <i class="bi bi-x-lg me-1"></i>Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── SECTION 2: Create Form ────────────────────────────── -->
            <?php if (!empty($emptyCategories)): ?>
                <div class="card shadow-sm mb-4 border-0">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-person-plus-fill me-2"></i>Create New Team Member
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="create">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        Category Slot <span class="text-danger">*</span>
                                    </label>
                                    <select name="category" class="form-select" required>
                                        <option value="">-- Select Vacant Slot --</option>
                                        <?php foreach ($emptyCategories as $key => $label): ?>
                                            <option value="<?= $key ?>" 
                                                <?= ($formData['category'] ?? '') === $key ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Only empty slots are shown.</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        Full Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="full_name" class="form-control" required
                                           value="<?= htmlspecialchars($formData['full_name'] ?? '') ?>"
                                           placeholder="e.g., Dr. Jane Doe">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        Email Address <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" name="email" class="form-control" required
                                           value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                                           placeholder="name@example.com">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">
                                        Password <span class="text-danger">*</span>
                                    </label>
                                    <input type="password" name="password" class="form-control" required
                                           placeholder="Min 8 chars, 1 uppercase, 1 number">
                                    <div class="form-text">
                                        Team member will use this to log into the team portal.
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Phone Number</label>
                                    <input type="text" name="phone" class="form-control"
                                           value="<?= htmlspecialchars($formData['phone'] ?? '') ?>"
                                           placeholder="e.g., 03001234567">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Qualifications / Degrees</label>
                                    <input type="text" name="qualification" class="form-control"
                                           value="<?= htmlspecialchars($formData['qualification'] ?? '') ?>"
                                           placeholder="e.g., Ph.D. in Psychology">
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-2">
                                <button type="submit" class="btn btn-primary btn-lg px-5">
                                    <i class="bi bi-save me-2"></i>Create Team Member
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php else: ?>
                <div class="alert alert-success text-center mb-4 fs-6">
                    <i class="bi bi-stars me-2"></i>
                    All 5 team slots are filled. Deactivate a member below to open a slot.
                </div>
            <?php endif; ?>

            <!-- ── SECTION 3: Active Roster Table ───────────────────── -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-list-task me-2"></i>Active Team Roster
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Category Slot</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $key => $label): ?>
                                    <tr>
                                        <td><strong><?= $label ?></strong></td>
                                        <?php if (isset($filledSlots[$key])): ?>
                                            <td><?= htmlspecialchars($filledSlots[$key]['full_name']) ?></td>
                                            <td><?= htmlspecialchars($filledSlots[$key]['email']) ?></td>
                                            <td class="text-end">
                                                <form method="POST" class="d-inline"
                                                      onsubmit="return confirm('Deactivate <?= htmlspecialchars($filledSlots[$key]['full_name']) ?>? This will empty the slot.');">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="action"    value="deactivate">
                                                    <input type="hidden" name="member_id" value="<?= $filledSlots[$key]['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-person-x me-1"></i>Deactivate
                                                    </button>
                                                </form>
                                            </td>
                                        <?php else: ?>
                                            <td colspan="2" class="text-muted fst-italic">Currently Vacant</td>
                                            <td class="text-end">
                                                <span class="badge bg-secondary">Empty</span>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>