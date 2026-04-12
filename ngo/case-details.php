<?php
/**
 * Heal2Rise Book - NGO Case Details
 * Manage cases: assign team, recommend counselor, schedule sessions, recommend programs
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('ngo');

$ngoId = getCurrentUserId();
$caseId = intval($_GET['id'] ?? 0);

if (!$caseId) {
    redirect('/ngo/dashboard.php');
    exit;
}

$db = getDB();

// Get case details (only if assigned to this NGO)
$stmt = $db->prepare("
    SELECT c.*, 
           u.full_name as user_name, u.email as user_email, u.phone as user_phone, 
           u.city as user_city, u.state as user_state, u.gender as user_gender,
           u.issue_description as user_issue_description,
           u.emergency_contact_name, u.emergency_contact_phone,
           tm.full_name as team_member_name, tm.role as team_member_role, tm.id as team_member_id,
           co.full_name as counselor_name, co.role as counselor_role, co.id as counselor_id_val
    FROM cases c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN team_members tm ON c.team_member_id = tm.id
    LEFT JOIN team_members co ON c.counselor_id = co.id
    WHERE c.id = ? AND c.ngo_id = ?
");
$stmt->execute([$caseId, $ngoId]);
$case = $stmt->fetch();

if (!$case) {
    setFlashMessage('Case not found or access denied.', 'danger');
    redirect('/ngo/dashboard.php');
    exit;
}

// Get all team members for assignment
$stmt = $db->prepare("SELECT * FROM team_members WHERE ngo_id = ? AND status = 'active' ORDER BY full_name");
$stmt->execute([$ngoId]);
$allTeamMembers = $stmt->fetchAll();

// Get counselors/psychiatrists for counselor recommendation
$stmt = $db->prepare("SELECT * FROM team_members WHERE ngo_id = ? AND role IN ('counselor', 'psychiatrist') AND status = 'active' ORDER BY full_name");
$stmt->execute([$ngoId]);
$counselors = $stmt->fetchAll();

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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('Invalid request.', 'danger');
    } else {
        $action = $_POST['action'] ?? '';
        
        // Assign team member
        if ($action === 'assign_team_member') {
            $teamMemberId = intval($_POST['team_member_id'] ?? 0);
            
            if ($teamMemberId) {
                $stmt = $db->prepare("UPDATE cases SET team_member_id = ?, status = 'assigned', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$teamMemberId, $caseId]);
                
                $stmt = $db->prepare("UPDATE team_members SET cases_assigned = cases_assigned + 1 WHERE id = ?");
                $stmt->execute([$teamMemberId]);
                
                $stmt = $db->prepare("SELECT full_name FROM team_members WHERE id = ?");
                $stmt->execute([$teamMemberId]);
                $memberName = $stmt->fetchColumn();
                
                sendNotification('user', $case['user_id'], 'Team Member Assigned', 
                    "A team member ({$memberName}) has been assigned to your case.", 'info');
                
                $stmt = $db->prepare("INSERT INTO case_progress (case_id, status, notes, updated_by) VALUES (?, 'assigned', ?, ?)");
                $stmt->execute([$caseId, "Team member assigned: {$memberName}", $teamMemberId]);
                
                setFlashMessage('Team member assigned successfully!', 'success');
            }
        }
        
        // Update case status
        if ($action === 'update_status') {
            $newStatus = $_POST['status'] ?? '';
            $notes = sanitize($_POST['notes'] ?? '');
            $validStatuses = ['assigned', 'in_progress', 'counseling', 'rehabilitation', 'skill_development', 'follow_up'];
            
            if (in_array($newStatus, $validStatuses) && $notes) {
                $stmt = $db->prepare("UPDATE cases SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newStatus, $caseId]);
                
                $stmt = $db->prepare("INSERT INTO case_progress (case_id, status, notes, updated_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$caseId, $newStatus, $notes, $case['team_member_id']]);
                
                sendNotification('user', $case['user_id'], 'Case Status Updated', 
                    "Your case status has been updated to: " . ucfirst(str_replace('_', ' ', $newStatus)), 'info');
                
                setFlashMessage('Case status updated!', 'success');
            }
        }
        
        // Add notes
        if ($action === 'add_notes') {
            $notes = sanitize($_POST['notes'] ?? '');
            
            if ($notes) {
                $stmt = $db->prepare("INSERT INTO case_progress (case_id, status, notes, updated_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$caseId, $case['status'], $notes, $case['team_member_id']]);
                setFlashMessage('Notes added successfully!', 'success');
            }
        }
        
        // Recommend counselor
        if ($action === 'recommend_counselor') {
            $counselorId = intval($_POST['counselor_id'] ?? 0);
            if ($counselorId) {
                $result = assignCounselor($caseId, $counselorId, $case['team_member_id']);
                setFlashMessage($result['success'] ? 'Counselor assigned and case moved to counseling!' : $result['error'], 
                               $result['success'] ? 'success' : 'danger');
            }
        }
        
        // Schedule counseling session
        if ($action === 'schedule_session') {
            $counselorId = $case['counselor_id'] ?? intval($_POST['session_counselor_id'] ?? 0);
            $sessionDate = $_POST['session_date'] ?? '';
            $sessionTime = $_POST['session_time'] ?? '';
            $sessionType = $_POST['session_type'] ?? 'regular';
            $duration = intval($_POST['duration'] ?? 60);
            
            if ($counselorId && $sessionDate) {
                $result = createCounselingSession($caseId, $counselorId, $sessionDate, $sessionTime, $sessionType, $duration);
                setFlashMessage($result['success'] ? 'Session scheduled!' : $result['error'], 
                               $result['success'] ? 'success' : 'danger');
            }
        }
        
        // Complete counseling session
        if ($action === 'complete_session') {
            $sessionId = intval($_POST['session_id'] ?? 0);
            $sessionNotes = sanitize($_POST['session_notes'] ?? '');
            $recommendations = sanitize($_POST['session_recommendations'] ?? '');
            $moodRating = intval($_POST['mood_rating'] ?? 0) ?: null;
            $nextDate = $_POST['next_session_date'] ?? null;
            
            if ($sessionId && $sessionNotes) {
                completeCounselingSession($sessionId, $sessionNotes, $recommendations, $moodRating, $nextDate ?: null);
                setFlashMessage('Session completed!', 'success');
            }
        }
        
        // Recommend program
        if ($action === 'recommend_program') {
            $programType = $_POST['program_type'] ?? '';
            $programName = sanitize($_POST['program_name'] ?? '');
            $programDesc = sanitize($_POST['program_description'] ?? '');
            $startDate = $_POST['program_start_date'] ?? null;
            $endDate = $_POST['program_end_date'] ?? null;
            
            if ($programType && $programName) {
                $result = recommendProgram($caseId, $programType, $programName, $programDesc, $case['team_member_id'], $startDate ?: null, $endDate ?: null);
                setFlashMessage($result['success'] ? 'Program recommended!' : $result['error'], 
                               $result['success'] ? 'success' : 'danger');
            }
        }
        
        // Update program status
        if ($action === 'update_program') {
            $programId = intval($_POST['program_id'] ?? 0);
            $programStatus = $_POST['program_status'] ?? '';
            $progressNotes = sanitize($_POST['progress_notes'] ?? '');
            
            if ($programId && $programStatus) {
                updateProgramStatus($programId, $programStatus, $progressNotes);
                setFlashMessage('Program status updated!', 'success');
            }
        }
        
        redirect('/ngo/case-details.php?id=' . $caseId);
        exit;
    }
}

$pageTitle = 'Case Details';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="py-4">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 mb-4">
                <div class="dashboard-sidebar">
                    <div class="text-center mb-4">
                        <i class="bi bi-building display-4 text-primary"></i>
                        <h6 class="mt-2 mb-0"><?= htmlspecialchars($_SESSION['user_data']['name'] ?? 'NGO') ?></h6>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?= url('/ngo/dashboard.php') ?>">
                            <i class="bi bi-speedometer2"></i>Dashboard
                        </a>
                        <a class="nav-link active" href="<?= url('/ngo/cases.php') ?>">
                            <i class="bi bi-folder"></i>Cases
                        </a>
                        <a class="nav-link" href="<?= url('/ngo/team.php') ?>">
                            <i class="bi bi-people"></i>Team
                        </a>
                        <a class="nav-link" href="<?= url('/ngo/profile.php') ?>">
                            <i class="bi bi-gear"></i>Settings
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
                        <a href="<?= url('/ngo/cases.php') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                            <i class="bi bi-arrow-left me-2"></i>Back to Cases
                        </a>
                        <h4 class="mb-0">Case: <?= htmlspecialchars($case['case_number']) ?></h4>
                    </div>
                    <div>
                        <span class="badge bg-<?= $case['severity_level'] === 'high' || $case['severity_level'] === 'critical' ? 'danger' : ($case['severity_level'] === 'medium' ? 'warning' : 'success') ?> me-2">
                            <?= ucfirst($case['severity_level']) ?> Severity
                        </span>
                        <span class="badge bg-<?= getStatusBadge($case['status']) ?> fs-6">
                            <?= ucfirst(str_replace('_', ' ', $case['status'])) ?>
                        </span>
                    </div>
                </div>

                <div class="row">
                    <!-- Main Case Info -->
                    <div class="col-md-8 col-lg-8">
                        <!-- Case Details -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-info-circle me-2"></i>Case Information
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <strong>Issue Category:</strong><br>
                                        <span class="badge bg-info"><?= ucfirst(str_replace('_', ' ', $case['issue_category'])) ?></span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Priority:</strong><br>
                                        <span class="badge bg-<?= $case['priority'] === 'urgent' ? 'danger' : ($case['priority'] === 'high' ? 'warning' : 'secondary') ?>">
                                            <?= ucfirst($case['priority']) ?>
                                        </span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Created:</strong><br>
                                        <?= formatDate($case['created_at'], 'M d, Y H:i') ?>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <strong>Case Description:</strong>
                                <div class="bg-light p-3 rounded mt-2 mb-3">
                                    <?= nl2br(htmlspecialchars($case['description'])) ?>
                                </div>
                                
                                <?php if ($case['user_issue_description']): ?>
                                    <strong>User's Initial Issue Description:</strong>
                                    <div class="bg-light p-3 rounded mt-2">
                                        <?= nl2br(htmlspecialchars($case['user_issue_description'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($case['status'] !== 'closed' && $case['status'] !== 'cancelled'): ?>
                        <!-- Team Assignment -->
                        <?php if (!$case['team_member_id']): ?>
                            <div class="card mb-4 border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <i class="bi bi-person-plus me-2"></i>Assign Team Member
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="assign_team_member">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Select Team Member</label>
                                            <select name="team_member_id" class="form-select" required>
                                                <option value="">Choose a team member...</option>
                                                <?php foreach ($allTeamMembers as $member): ?>
                                                    <option value="<?= $member['id'] ?>">
                                                        <?= htmlspecialchars($member['full_name']) ?> 
                                                        (<?= ucfirst($member['role']) ?>) 
                                                        - <?= $member['cases_assigned'] ?>/<?= $member['max_cases'] ?> cases
                                                        <?= $member['is_available'] ? '✓ Available' : '⚠ Busy' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-warning">
                                            <i class="bi bi-person-check me-2"></i>Assign Member
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Update Status -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="bi bi-arrow-repeat me-2"></i>Update Case Status
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label class="form-label">New Status</label>
                                                <select name="status" class="form-select" required>
                                                    <option value="in_progress" <?= $case['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                    <option value="counseling" <?= $case['status'] === 'counseling' ? 'selected' : '' ?>>Counseling</option>
                                                    <option value="rehabilitation" <?= $case['status'] === 'rehabilitation' ? 'selected' : '' ?>>Rehabilitation</option>
                                                    <option value="skill_development" <?= $case['status'] === 'skill_development' ? 'selected' : '' ?>>Skill Development</option>
                                                    <option value="follow_up" <?= $case['status'] === 'follow_up' ? 'selected' : '' ?>>Follow Up</option>
                                                </select>
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label">Notes</label>
                                                <input type="text" name="notes" class="form-control" placeholder="Add update notes..." required>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary mt-3">
                                            <i class="bi bi-save me-2"></i>Update Status
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Recommend Counselor -->
                        <?php if ($case['team_member_id'] && !$case['counselor_id']): ?>
                        <div class="card mb-4 border-info">
                            <div class="card-header bg-info text-white">
                                <i class="bi bi-heart-pulse me-2"></i>Recommend a Counselor
                            </div>
                            <div class="card-body">
                                <?php if (empty($counselors)): ?>
                                    <div class="alert alert-warning mb-0">
                                        <i class="bi bi-exclamation-triangle me-2"></i>No counselors or psychiatrists available. Please add team members with these roles first.
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-3">If counseling is needed, recommend a counselor to the case. This will move the case to "Counseling" status.</p>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="recommend_counselor">
                                        <div class="mb-3">
                                            <label class="form-label">Select Counselor *</label>
                                            <select name="counselor_id" class="form-select" required>
                                                <option value="">Choose a counselor...</option>
                                                <?php foreach ($counselors as $c): ?>
                                                    <option value="<?= $c['id'] ?>">
                                                        <?= htmlspecialchars($c['full_name']) ?> 
                                                        (<?= ucfirst($c['role']) ?>)
                                                        <?php if ($c['specialization']): ?> - <?= htmlspecialchars($c['specialization']) ?><?php endif; ?>
                                                        - <?= $c['experience_years'] ?> yrs exp
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-info text-white">
                                            <i class="bi bi-person-heart me-2"></i>Assign Counselor
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Schedule Counseling Session -->
                        <?php if ($case['counselor_id']): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-calendar-plus me-2"></i>Schedule Counseling Session
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="schedule_session">
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Date *</label>
                                            <input type="date" name="session_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                                        </div>
                                        <div class="col-md-2 mb-3">
                                            <label class="form-label">Time</label>
                                            <input type="time" name="session_time" class="form-control">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Type</label>
                                            <select name="session_type" class="form-select">
                                                <option value="initial_assessment">Initial Assessment</option>
                                                <option value="regular" selected>Regular</option>
                                                <option value="follow_up">Follow Up</option>
                                                <option value="emergency">Emergency</option>
                                                <option value="group">Group</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 mb-3">
                                            <label class="form-label">Duration</label>
                                            <select name="duration" class="form-select">
                                                <option value="30">30 min</option>
                                                <option value="45">45 min</option>
                                                <option value="60" selected>60 min</option>
                                                <option value="90">90 min</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 mb-3 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="bi bi-plus-lg me-1"></i>Schedule
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Counseling Sessions List -->
                        <?php if (!empty($sessions)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-chat-heart me-2"></i>Counseling Sessions (<?= count($sessions) ?>)
                            </div>
                            <div class="card-body">
                                <?php foreach ($sessions as $session): ?>
                                <div class="border rounded p-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong><?= formatDate($session['session_date']) ?></strong>
                                            <?php if ($session['session_time']): ?>
                                                at <?= date('h:i A', strtotime($session['session_time'])) ?>
                                            <?php endif; ?>
                                            <span class="badge bg-secondary ms-2"><?= ucfirst(str_replace('_', ' ', $session['session_type'])) ?></span>
                                            <span class="badge bg-<?= getStatusBadge($session['status']) ?> ms-1"><?= ucfirst($session['status']) ?></span>
                                        </div>
                                        <small class="text-muted"><?= $session['duration_minutes'] ?> min</small>
                                    </div>
                                    <p class="text-muted small mb-1">
                                        <i class="bi bi-person me-1"></i><?= htmlspecialchars($session['counselor_name']) ?> (<?= ucfirst($session['counselor_role']) ?>)
                                    </p>
                                    
                                    <?php if ($session['notes']): ?>
                                        <p class="small mb-1"><strong>Notes:</strong> <?= htmlspecialchars($session['notes']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($session['recommendations']): ?>
                                        <p class="small mb-1"><strong>Recommendations:</strong> <?= htmlspecialchars($session['recommendations']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($session['mood_rating']): ?>
                                        <p class="small mb-1"><strong>Mood Rating:</strong> <?= $session['mood_rating'] ?>/10</p>
                                    <?php endif; ?>
                                    
                                    <?php if ($session['status'] === 'scheduled'): ?>
                                    <hr>
                                    <form method="POST" class="mt-2">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="complete_session">
                                        <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                        <div class="row">
                                            <div class="col-md-6 mb-2">
                                                <textarea name="session_notes" class="form-control form-control-sm" rows="2" placeholder="Session notes..." required></textarea>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <input type="text" name="session_recommendations" class="form-control form-control-sm mb-1" placeholder="Recommendations...">
                                                <div class="d-flex gap-2">
                                                    <input type="number" name="mood_rating" class="form-control form-control-sm" placeholder="Mood 1-10" min="1" max="10">
                                                    <input type="date" name="next_session_date" class="form-control form-control-sm" min="<?= date('Y-m-d') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <button type="submit" class="btn btn-success btn-sm w-100">
                                                    <i class="bi bi-check-lg me-1"></i>Complete
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Recommend Program -->
                        <?php if ($case['team_member_id'] && $case['status'] !== 'closed'): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-mortarboard me-2"></i>Recommend Rehabilitation / Skill Development Program
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="recommend_program">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Program Type *</label>
                                            <select name="program_type" class="form-select" required>
                                                <option value="">Select type...</option>
                                                <option value="rehabilitation">Rehabilitation</option>
                                                <option value="skill_development">Skill Development</option>
                                            </select>
                                        </div>
                                        <div class="col-md-8 mb-3">
                                            <label class="form-label">Program Name *</label>
                                            <input type="text" name="program_name" class="form-control" placeholder="e.g., Emotional Resilience Training" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea name="program_description" class="form-control" rows="2" placeholder="Describe the program and its goals..."></textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Start Date</label>
                                            <input type="date" name="program_start_date" class="form-control">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">End Date</label>
                                            <input type="date" name="program_end_date" class="form-control">
                                        </div>
                                        <div class="col-md-4 mb-3 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="bi bi-plus-lg me-2"></i>Recommend Program
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Programs List -->
                        <?php if (!empty($programs)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-list-check me-2"></i>Programs (<?= count($programs) ?>)
                            </div>
                            <div class="card-body">
                                <?php foreach ($programs as $program): ?>
                                <div class="border rounded p-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($program['program_name']) ?></h6>
                                            <span class="badge bg-<?= $program['program_type'] === 'rehabilitation' ? 'info' : 'primary' ?> me-1">
                                                <?= ucfirst(str_replace('_', ' ', $program['program_type'])) ?>
                                            </span>
                                            <span class="badge bg-<?= getStatusBadge($program['status']) ?>">
                                                <?= ucfirst($program['status']) ?>
                                            </span>
                                        </div>
                                        <small class="text-muted">
                                            <?php if ($program['start_date']): ?>
                                                <?= formatDate($program['start_date']) ?>
                                                <?php if ($program['end_date']): ?> - <?= formatDate($program['end_date']) ?><?php endif; ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <?php if ($program['description']): ?>
                                        <p class="small text-muted mb-2"><?= htmlspecialchars($program['description']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($program['progress_notes']): ?>
                                        <p class="small mb-2"><strong>Progress:</strong> <?= nl2br(htmlspecialchars($program['progress_notes'])) ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!in_array($program['status'], ['completed', 'dropped'])): ?>
                                    <form method="POST" class="mt-2">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="update_program">
                                        <input type="hidden" name="program_id" value="<?= $program['id'] ?>">
                                        <div class="row g-2">
                                            <div class="col-md-3">
                                                <select name="program_status" class="form-select form-select-sm" required>
                                                    <option value="enrolled" <?= $program['status'] === 'enrolled' ? 'selected' : '' ?>>Enrolled</option>
                                                    <option value="in_progress" <?= $program['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                    <option value="completed">Completed</option>
                                                    <option value="dropped">Dropped</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" name="progress_notes" class="form-control form-control-sm" placeholder="Progress notes...">
                                            </div>
                                            <div class="col-md-3">
                                                <button type="submit" class="btn btn-outline-primary btn-sm w-100">Update</button>
                                            </div>
                                        </div>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <!-- Add Notes -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-journal-plus me-2"></i>Add Progress Notes
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="add_notes">
                                    
                                    <div class="mb-3">
                                        <textarea name="notes" class="form-control" rows="3" placeholder="Enter progress notes..." required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="bi bi-plus-lg me-2"></i>Add Notes
                                    </button>
                                </form>
                            </div>
                        </div>

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
                                                            By: <?= htmlspecialchars($progress['updated_by_name'] ?? 'System') ?>
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

                    <!-- Sidebar -->
                    <div class="col-md-4 col-lg-4">
                        <!-- User Info -->
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <i class="bi bi-person-heart me-2"></i>Client Information
                            </div>
                            <div class="card-body">
                                <p><strong>Name:</strong> <?= htmlspecialchars($case['user_name']) ?></p>
                                <p><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($case['user_email']) ?>"><?= htmlspecialchars($case['user_email']) ?></a></p>
                                <p><strong>Phone:</strong> <a href="tel:<?= htmlspecialchars($case['user_phone'] ?? '') ?>"><?= htmlspecialchars($case['user_phone'] ?? 'N/A') ?></a></p>
                                <p><strong>Gender:</strong> <?= ucfirst($case['user_gender'] ?? 'N/A') ?></p>
                                <p class="mb-0"><strong>Location:</strong> <?= htmlspecialchars(($case['user_city'] ?? '') . ', ' . ($case['user_state'] ?? '')) ?></p>
                            </div>
                        </div>

                        <!-- Emergency Contact -->
                        <div class="card mb-4">
                            <div class="card-header bg-danger text-white">
                                <i class="bi bi-telephone-forward me-2"></i>Emergency Contact
                            </div>
                            <div class="card-body">
                                <p><strong>Name:</strong> <?= htmlspecialchars($case['emergency_contact_name'] ?? 'N/A') ?></p>
                                <p class="mb-0"><strong>Phone:</strong> 
                                    <?php if ($case['emergency_contact_phone']): ?>
                                        <a href="tel:<?= htmlspecialchars($case['emergency_contact_phone']) ?>"><?= htmlspecialchars($case['emergency_contact_phone']) ?></a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <!-- Assigned Team Member -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-person-badge me-2"></i>Team Member
                            </div>
                            <div class="card-body">
                                <?php if ($case['team_member_id']): ?>
                                    <p><strong>Name:</strong> <?= htmlspecialchars($case['team_member_name']) ?></p>
                                    <p class="mb-0"><strong>Role:</strong> <?= ucfirst(str_replace('_', ' ', $case['team_member_role'])) ?></p>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No team member assigned yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Assigned Counselor -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-heart-pulse me-2"></i>Counselor
                            </div>
                            <div class="card-body">
                                <?php if ($case['counselor_id']): ?>
                                    <p><strong>Name:</strong> <?= htmlspecialchars($case['counselor_name']) ?></p>
                                    <p><strong>Role:</strong> <?= ucfirst(str_replace('_', ' ', $case['counselor_role'])) ?></p>
                                    <p class="mb-0">
                                        <strong>Sessions:</strong> <?= count($sessions) ?> total,
                                        <?= count(array_filter($sessions, fn($s) => $s['status'] === 'completed')) ?> completed
                                    </p>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No counselor assigned yet. Team member can recommend one.</p>
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
