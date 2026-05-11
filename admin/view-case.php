<?php
/**
 * Heal2Rise Book - View & Manage Case Details (Admin)
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('admin');

$caseId = intval($_GET['id'] ?? 0);
if (!$caseId) {
    redirect('/admin/cases.php');
    exit;
}

$db = getDB();
$adminId = getCurrentUserId();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('Invalid request.', 'danger');
    } else {
        $action = $_POST['action'] ?? '';
        
        // Assign NGO
        if ($action === 'assign_ngo') {
            $ngoId = intval($_POST['ngo_id'] ?? 0);
            if ($ngoId) {
                $result = adminAssignNGO($caseId, $ngoId);
                setFlashMessage($result['success'] ? 'NGO assigned successfully!' : $result['error'], $result['success'] ? 'success' : 'danger');
            }
        }
        
        // Assign Team Member
        if ($action === 'assign_team') {
            $teamMemberId = intval($_POST['team_member_id'] ?? 0);
            if ($teamMemberId) {
                $result = adminAssignTeamMember($caseId, $teamMemberId);
                setFlashMessage($result['success'] ? 'Team member assigned!' : $result['error'], $result['success'] ? 'success' : 'danger');
            }
        }
        
        // Update Status
        if ($action === 'update_status') {
            $newStatus = $_POST['status'] ?? '';
            $notes = sanitize($_POST['notes'] ?? '');
            $validStatuses = ['pending', 'assigned', 'in_progress', 'counseling', 'rehabilitation', 'skill_development', 'follow_up'];
            
            if (in_array($newStatus, $validStatuses) && $notes) {
                $db->prepare("UPDATE cases SET status = ? WHERE id = ?")->execute([$newStatus, $caseId]);
                $db->prepare("INSERT INTO case_progress (case_id, status, notes) VALUES (?, ?, ?)")
                   ->execute([$caseId, $newStatus, "Admin update: {$notes}"]);
                
                $stmt = $db->prepare("SELECT user_id, ngo_id, case_number FROM cases WHERE id = ?");
                $stmt->execute([$caseId]);
                $c = $stmt->fetch();
                sendNotification('user', $c['user_id'], 'Case Updated', "Your case #{$c['case_number']} status: " . ucfirst(str_replace('_', ' ', $newStatus)), 'info');
                if ($c['ngo_id']) {
                    sendNotification('ngo', $c['ngo_id'], 'Case Updated', "Case #{$c['case_number']} status updated to: " . ucfirst(str_replace('_', ' ', $newStatus)), 'info');
                }
                setFlashMessage('Case status updated!', 'success');
            }
        }
        
        // Add Admin Notes
        if ($action === 'add_notes') {
            $notes = sanitize($_POST['admin_notes'] ?? '');
            if ($notes) {
                $db->prepare("UPDATE cases SET admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n[', NOW(), '] ', ?) WHERE id = ?")->execute([$notes, $caseId]);
                $db->prepare("INSERT INTO case_progress (case_id, status, notes) VALUES (?, (SELECT status FROM cases WHERE id = ?), ?)")
                   ->execute([$caseId, $caseId, "Admin note: {$notes}"]);
                setFlashMessage('Notes added!', 'success');
            }
        }
        
        // Close Case
        if ($action === 'close_case') {
            $closureRemarks = sanitize($_POST['closure_remarks'] ?? '');
            $userOutcome = sanitize($_POST['user_outcome'] ?? 'recovered');
            
            if ($closureRemarks) {
                $result = adminCloseCase($caseId, $closureRemarks, $adminId, $userOutcome);
                setFlashMessage($result['success'] ? 'Case closed successfully!' : $result['error'], $result['success'] ? 'success' : 'danger');
            }
        }
        
        redirect('/admin/view-case.php?id=' . $caseId);
        exit;
    }
}

// Get case details with related data
$stmt = $db->prepare("
    SELECT c.*, 
           u.full_name as user_name, u.email as user_email, u.phone as user_phone, u.city as user_city,
           u.status as user_status,
           n.organization_name, n.email as ngo_email, n.phone as ngo_phone,
           tm.full_name as team_member_name, tm.role as team_member_role, tm.email as team_member_email,
           co.full_name as counselor_name, co.role as counselor_role, co.email as counselor_email
    FROM cases c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN ngos n ON c.ngo_id = n.id
    LEFT JOIN team_members tm ON c.team_member_id = tm.id
    LEFT JOIN team_members co ON c.counselor_id = co.id
    WHERE c.id = ?
");
$stmt->execute([$caseId]);
$case = $stmt->fetch();

if (!$case) {
    setFlashMessage('Case not found.', 'danger');
    redirect('/admin/cases.php');
    exit;
}

// Get case progress history
$stmt = $db->prepare("
    SELECT cp.*, tm.full_name as updated_by_name
    FROM case_progress cp
    LEFT JOIN team_members tm ON cp.updated_by = tm.id
    WHERE cp.case_id = ?
    ORDER BY cp.created_at DESC
");
$stmt->execute([$caseId]);
$progressHistory = $stmt->fetchAll();

// Get counseling sessions
$sessions = getCounselingSessions($caseId);

// Get programs
$programs = getCasePrograms($caseId);

// Get available NGOs for assignment
$stmt = $db->query("SELECT id, organization_name, city, state, current_cases, capacity FROM ngos WHERE is_verified = 1 AND status = 'active' ORDER BY organization_name");
$availableNGOs = $stmt->fetchAll();

// Get team members for assignment (from assigned NGO)
$availableTeamMembers = [];
if ($case['ngo_id']) {
    $stmt = $db->prepare("SELECT id, full_name, category, max_cases, is_available, status,
    (SELECT COUNT(*) FROM cases WHERE team_member_id = team_members.id AND status NOT IN ('closed', 'cancelled')) as cases_assigned 
    FROM team_members WHERE status = 'active' ORDER BY full_name");
    $stmt->execute();
    $availableTeamMembers = $stmt->fetchAll();
}

$pageTitle = 'Manage Case';
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
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?= url('/admin/dashboard.php') ?>">
                            <i class="bi bi-speedometer2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="<?= url('/admin/profile.php') ?>">
                            <i class="bi bi-person"></i>My Profile
                        </a>
                        <a class="nav-link" href="<?= url('/admin/users.php') ?>">
                            <i class="bi bi-people"></i>Users
                        </a>
                        <a class="nav-link" href="<?= url('/admin/ngos.php') ?>">
                            <i class="bi bi-building"></i>NGOs
                        </a>
                        <a class="nav-link active" href="<?= url('/admin/cases.php') ?>">
                            <i class="bi bi-folder"></i>Cases
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
            <div class="col-md-9 col-lg-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <a href="<?= url('/admin/cases.php') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                            <i class="bi bi-arrow-left me-2"></i>Back to Cases
                        </a>
                        <h4 class="mb-0">Case: <?= htmlspecialchars($case['case_number']) ?></h4>
                    </div>
                    <span class="badge bg-<?= getStatusBadge($case['status']) ?> fs-6">
                        <?= ucfirst(str_replace('_', ' ', $case['status'])) ?>
                    </span>
                </div>

                <div class="row">
                    <!-- Main Case Info -->
                    <div class="col-md-8 col-lg-8">
                        <!-- Case Details -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-info-circle me-2"></i>Case Details
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <strong>Case Number:</strong><br>
                                        <?= htmlspecialchars($case['case_number']) ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Issue Category:</strong><br>
                                        <span class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', $case['issue_category'])) ?></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Severity:</strong><br>
                                        <span class="badge bg-<?= $case['severity_level'] === 'high' || $case['severity_level'] === 'critical' ? 'danger' : ($case['severity_level'] === 'medium' ? 'warning' : 'success') ?>">
                                            <?= ucfirst($case['severity_level']) ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Priority:</strong><br>
                                        <span class="badge bg-<?= $case['priority'] === 'urgent' ? 'danger' : ($case['priority'] === 'high' ? 'warning' : 'secondary') ?>">
                                            <?= ucfirst($case['priority']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <strong>Created:</strong><br>
                                        <?= formatDate($case['created_at'], 'M d, Y H:i') ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Last Updated:</strong><br>
                                        <?= formatDate($case['updated_at'], 'M d, Y H:i') ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>User Status:</strong><br>
                                        <span class="badge bg-<?= getStatusBadge($case['user_status']) ?>">
                                            <?= ucfirst($case['user_status']) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <strong>Description:</strong>
                                <div class="bg-light p-3 rounded mt-2">
                                    <?= nl2br(htmlspecialchars($case['description'])) ?>
                                </div>
                                
                                <?php if ($case['admin_notes']): ?>
                                    <hr>
                                    <strong>Admin Notes:</strong>
                                    <div class="bg-warning bg-opacity-10 p-3 rounded mt-2">
                                        <?= nl2br(htmlspecialchars($case['admin_notes'])) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($case['closure_remarks']): ?>
                                    <hr>
                                    <strong>Closure Remarks:</strong>
                                    <div class="bg-success bg-opacity-10 p-3 rounded mt-2">
                                        <?= nl2br(htmlspecialchars($case['closure_remarks'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($case['status'] !== 'closed' && $case['status'] !== 'cancelled'): ?>
                        <!-- Admin Actions -->
                        <div class="card mb-4 border-primary">
                            <div class="card-header bg-primary text-white">
                                <i class="bi bi-gear me-2"></i>Admin Actions
                            </div>
                            <div class="card-body">
                                <ul class="nav nav-tabs mb-3" role="tablist">
                                    <?php if (!$case['ngo_id']): ?>
                                    <li class="nav-item">
                                        <a class="nav-link active" data-toggle="tab" href="#assignNgo" role="tab">Assign NGO</a>
                                    </li>
                                    <?php endif; ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?= $case['ngo_id'] ? 'active' : '' ?>" data-toggle="tab" href="#assignTeam" role="tab">
                                            <?= $case['team_member_id'] ? 'Reassign' : 'Assign' ?> Team Member
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#updateStatus" role="tab">Update Status</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#addNotes" role="tab">Add Notes</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link text-danger" data-toggle="tab" href="#closeCase" role="tab">Close Case</a>
                                    </li>
                                </ul>
                                
                                <div class="tab-content">
                                    <!-- Assign NGO Tab -->
                                    <?php if (!$case['ngo_id']): ?>
                                    <div class="tab-pane fade show active" id="assignNgo" role="tabpanel">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="assign_ngo">
                                            <div class="mb-3">
                                                <label class="form-label">Select NGO *</label>
                                                <select name="ngo_id" class="form-select" required>
                                                    <option value="">Choose an NGO...</option>
                                                    <?php foreach ($availableNGOs as $ngo): ?>
                                                        <option value="<?= $ngo['id'] ?>">
                                                            <?= htmlspecialchars($ngo['organization_name']) ?> 
                                                            (<?= htmlspecialchars($ngo['city']) ?>) 
                                                            - <?= $ngo['current_cases'] ?>/<?= $ngo['capacity'] ?> cases
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-building-check me-2"></i>Assign NGO
                                            </button>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Assign Team Member Tab -->
                                    <div class="tab-pane fade <?= $case['ngo_id'] ? 'show active' : '' ?>" id="assignTeam" role="tabpanel">
                                        <?php if (!$case['ngo_id']): ?>
                                            <div class="alert alert-warning mb-0">
                                                <i class="bi bi-exclamation-triangle me-2"></i>Assign an NGO first before assigning a team member.
                                            </div>
                                        <?php elseif (empty($availableTeamMembers)): ?>
                                            <div class="alert alert-warning mb-0">
                                                <i class="bi bi-exclamation-triangle me-2"></i>No team members found for the assigned NGO.
                                            </div>
                                        <?php else: ?>
                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="action" value="assign_team">
                                                <div class="mb-3">
                                                    <label class="form-label">Select Team Member *</label>
                                                    <select name="team_member_id" class="form-select" required>
                                                        <option value="">Choose a team member...</option>
                                                        <?php foreach ($availableTeamMembers as $member): ?>
                                                            <option value="<?= $member['id'] ?>" <?= $case['team_member_id'] == $member['id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($member['full_name']) ?> 
                                                                  (<?= ucfirst(str_replace('_', ' ', $member['category'])) ?>)
                                                                - <?= $member['cases_assigned'] ?>/<?= $member['max_cases'] ?> cases
                                                                <?= $member['is_available'] ? '✓' : '⚠' ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-person-check me-2"></i><?= $case['team_member_id'] ? 'Reassign' : 'Assign' ?> Team Member
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Update Status Tab -->
                                    <div class="tab-pane fade" id="updateStatus" role="tabpanel">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="update_status">
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">New Status *</label>
                                                    <select name="status" class="form-select" required>
                                                        <option value="pending" <?= $case['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="assigned" <?= $case['status'] === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                                                        <option value="in_progress" <?= $case['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                        <option value="counseling" <?= $case['status'] === 'counseling' ? 'selected' : '' ?>>Counseling</option>
                                                        <option value="rehabilitation" <?= $case['status'] === 'rehabilitation' ? 'selected' : '' ?>>Rehabilitation</option>
                                                        <option value="skill_development" <?= $case['status'] === 'skill_development' ? 'selected' : '' ?>>Skill Development</option>
                                                        <option value="follow_up" <?= $case['status'] === 'follow_up' ? 'selected' : '' ?>>Follow Up</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-8 mb-3">
                                                    <label class="form-label">Notes *</label>
                                                    <input type="text" name="notes" class="form-control" placeholder="Reason for status change..." required>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-warning">
                                                <i class="bi bi-arrow-repeat me-2"></i>Update Status
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <!-- Add Notes Tab -->
                                    <div class="tab-pane fade" id="addNotes" role="tabpanel">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="add_notes">
                                            <div class="mb-3">
                                                <label class="form-label">Admin Notes</label>
                                                <textarea name="admin_notes" class="form-control" rows="3" placeholder="Add internal notes..." required></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-outline-primary">
                                                <i class="bi bi-journal-plus me-2"></i>Add Notes
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <!-- Close Case Tab -->
                                    <div class="tab-pane fade" id="closeCase" role="tabpanel">
                                        <div class="alert alert-warning">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            <strong>Warning:</strong> Closing a case is final. Select the correct user outcome below.
                                        </div>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to close this case?');">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="close_case">
                                            <div class="mb-3">
                                                <label class="form-label">User Outcome *</label>
                                                <select name="user_outcome" class="form-select" required>
                                                    <option value="recovered">Recovered (Successfully finished treatment)</option>
                                                    <option value="active">Active (Case closed but user still needs help)</option>
                                                    <option value="cancelled">Cancelled/Rejected (Did not complete process)</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Closure Remarks *</label>
                                                <textarea name="closure_remarks" class="form-control" rows="4" 
                                                          placeholder="Summarize the case outcome, recovery status, and any follow-up recommendations..." required minlength="10"></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-danger">
                                                <i class="bi bi-check-circle me-2"></i>Close Case
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Counseling Sessions -->
                        <?php if (!empty($sessions)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-chat-heart me-2"></i>Counseling Sessions (<?= count($sessions) ?>)
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Counselor</th>
                                                <th>Type</th>
                                                <th>Duration</th>
                                                <th>Status</th>
                                                <th>Mood</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sessions as $session): ?>
                                            <tr>
                                                <td><?= formatDate($session['session_date']) ?></td>
                                                <td><?= htmlspecialchars($session['counselor_name']) ?></td>
                                                <td><?= ucfirst(str_replace('_', ' ', $session['session_type'])) ?></td>
                                                <td><?= $session['duration_minutes'] ?> min</td>
                                                <td><span class="badge bg-<?= getStatusBadge($session['status']) ?>"><?= ucfirst($session['status']) ?></span></td>
                                                <td><?= $session['mood_rating'] ? $session['mood_rating'] . '/10' : '-' ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Programs -->
                        <?php if (!empty($programs)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-mortarboard me-2"></i>Programs (<?= count($programs) ?>)
                            </div>
                            <div class="card-body">
                                <?php foreach ($programs as $program): ?>
                                <div class="border rounded p-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($program['program_name']) ?></h6>
                                            <span class="badge bg-<?= $program['program_type'] === 'rehabilitation' ? 'info' : 'primary' ?> me-2">
                                                <?= ucfirst(str_replace('_', ' ', $program['program_type'])) ?>
                                            </span>
                                            <span class="badge bg-<?= getStatusBadge($program['status']) ?>">
                                                <?= ucfirst($program['status']) ?>
                                            </span>
                                        </div>
                                        <small class="text-muted"><?= formatDate($program['created_at']) ?></small>
                                    </div>
                                    <?php if ($program['description']): ?>
                                        <p class="text-muted mt-2 mb-0 small"><?= htmlspecialchars($program['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Progress History -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-clock-history me-2"></i>Progress History
                            </div>
                            <div class="card-body">
                                <?php if (empty($progressHistory)): ?>
                                    <p class="text-muted mb-0">No progress updates yet.</p>
                                <?php else: ?>
                                    <div class="timeline">
                                        <?php foreach ($progressHistory as $progress): ?>
                                            <div class="timeline-item mb-3 pb-3 border-bottom">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <span class="badge bg-<?= getStatusBadge($progress['status']) ?> mb-2">
                                                            <?= ucfirst(str_replace('_', ' ', $progress['status'])) ?>
                                                        </span>
                                                        <p class="mb-1"><?= nl2br(htmlspecialchars($progress['notes'])) ?></p>
                                                        <small class="text-muted">
                                                            By: <?= htmlspecialchars($progress['updated_by_name'] ?? 'System/Admin') ?>
                                                        </small>
                                                    </div>
                                                    <small class="text-muted"><?= formatDate($progress['created_at'], 'M d, Y H:i') ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Info -->
                    <div class="col-md-4 col-lg-4">
                        <!-- Status Summary -->
                        <div class="card mb-4 border-<?= getStatusBadge($case['status']) ?>">
                            <div class="card-header bg-<?= getStatusBadge($case['status']) ?> text-white">
                                <i class="bi bi-flag me-2"></i>Case Status
                            </div>
                            <div class="card-body text-center">
                                <h4 class="text-<?= getStatusBadge($case['status']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $case['status'])) ?>
                                </h4>
                                <?php if ($case['actual_end_date']): ?>
                                    <p class="text-muted mb-0">Closed on <?= formatDate($case['actual_end_date']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- User Info -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-person me-2"></i>User Information
                            </div>
                            <div class="card-body">
                                <p><strong>Name:</strong> <?= htmlspecialchars($case['user_name']) ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($case['user_email']) ?></p>
                                <p><strong>Phone:</strong> <?= htmlspecialchars($case['user_phone'] ?? 'N/A') ?></p>
                                <p class="mb-0"><strong>City:</strong> <?= htmlspecialchars($case['user_city'] ?? 'N/A') ?></p>
                                <a href="<?= url('/admin/view-user.php?id=' . $case['user_id']) ?>" class="btn btn-sm btn-outline-primary mt-3">
                                    View Full Profile
                                </a>
                            </div>
                        </div>

                        <!-- NGO Info -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-building me-2"></i>Assigned NGO
                            </div>
                            <div class="card-body">
                                <?php if ($case['ngo_id']): ?>
                                    <p><strong>Organization:</strong> <?= htmlspecialchars($case['organization_name']) ?></p>
                                    <p><strong>Email:</strong> <?= htmlspecialchars($case['ngo_email']) ?></p>
                                    <p class="mb-0"><strong>Phone:</strong> <?= htmlspecialchars($case['ngo_phone']) ?></p>
                                    <a href="<?= url('/admin/view-ngo.php?id=' . $case['ngo_id']) ?>" class="btn btn-sm btn-outline-primary mt-3">
                                        View NGO Details
                                    </a>
                                <?php else: ?>
                                    <p class="text-warning mb-0"><i class="bi bi-exclamation-triangle me-2"></i>No NGO assigned yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Team Member Info -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-person-badge me-2"></i>Team Member
                            </div>
                            <div class="card-body">
                                <?php if ($case['team_member_id']): ?>
                                    <p><strong>Name:</strong> <?= htmlspecialchars($case['team_member_name']) ?></p>
                                    <p><strong>Role:</strong> <?= ucfirst(str_replace('_', ' ', $case['team_member_role'])) ?></p>
                                    <p class="mb-0"><strong>Email:</strong> <?= htmlspecialchars($case['team_member_email']) ?></p>
                                <?php else: ?>
                                    <p class="text-muted mb-0">Not assigned yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Counselor Info -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-heart-pulse me-2"></i>Counselor
                            </div>
                            <div class="card-body">
                                <?php if ($case['counselor_id']): ?>
                                    <p><strong>Name:</strong> <?= htmlspecialchars($case['counselor_name']) ?></p>
                                    <p><strong>Role:</strong> <?= ucfirst(str_replace('_', ' ', $case['counselor_role'])) ?></p>
                                    <p class="mb-0"><strong>Email:</strong> <?= htmlspecialchars($case['counselor_email']) ?></p>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No counselor assigned yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
