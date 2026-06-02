<?php
/**
 * Heal2Rise Book - Team Member Case Detail (with Session Management)
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('team_member');

$db = getDB();
$teamMemberId = getCurrentUserId();
$caseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$caseId) {
    setFlashMessage('Invalid case ID.', 'danger');
    redirect('/team/dashboard.php');
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('Invalid request. Please try again.', 'danger');
    } else {
        $action = $_POST['action'] ?? '';

        // --- Update Progress ---
        if ($action === 'update_progress') {
            $progressPercentage = (int)($_POST['progress_percentage'] ?? 0);
            $progressNotes = sanitize($_POST['progress_notes'] ?? '');

            if (strlen($progressNotes) < 20) {
                setFlashMessage('Progress notes must be at least 20 characters.', 'danger');
            } elseif ($progressPercentage < 0 || $progressPercentage > 100) {
                setFlashMessage('Invalid progress percentage.', 'danger');
            } else {
                $stmt = $db->prepare("UPDATE cases SET progress_percentage = ?, progress_notes = ? WHERE id = ? AND team_member_id = ?");
                if ($stmt->execute([$progressPercentage, $progressNotes, $caseId, $teamMemberId])) {
                    $statusText = "Progress updated to {$progressPercentage}%";
                    $db->prepare("INSERT INTO case_progress (case_id, status, notes, updated_by) VALUES (?, ?, ?, ?)")
                       ->execute([$caseId, $statusText, $progressNotes, $teamMemberId]);

                    $stmtCaseDet = $db->prepare("SELECT user_id, ngo_id, case_number FROM cases WHERE id = ?");
                    $stmtCaseDet->execute([$caseId]);
                    $cDet = $stmtCaseDet->fetch();
                    if ($cDet) {
                        sendNotification('user', $cDet['user_id'], 'Case Progress Updated', "Your case #{$cDet['case_number']} progress is now {$progressPercentage}%.", 'info');
                        sendNotification('ngo', $cDet['ngo_id'], 'Case Progress Updated', "Case #{$cDet['case_number']} progress updated to {$progressPercentage}%.", 'info');
                    }
                    if ($progressPercentage === 100) {
                        requestSatisfactionCheck($caseId, $teamMemberId);
                    }
                    setFlashMessage('Case progress updated successfully.', 'success');
                } else {
                    setFlashMessage('Failed to update case progress.', 'danger');
                }
            }
            redirect("/team/case-details.php?id={$caseId}");

        // --- Schedule Session ---
        } elseif ($action === 'schedule_session') {
            $sessionDate = $_POST['session_date'] ?? '';
            $sessionTime = $_POST['session_time'] ?? '';
            $sessionType = $_POST['session_type'] ?? 'regular';
            $duration    = intval($_POST['duration'] ?? 60);

            if (empty($sessionDate)) {
                setFlashMessage('Session date is required.', 'danger');
            } else {
                $result = createCounselingSession($caseId, $teamMemberId, $sessionDate, $sessionTime, $sessionType, $duration);
                setFlashMessage(
                    $result['success'] ? 'Session scheduled successfully!' : ($result['error'] ?? 'Failed to schedule session.'),
                    $result['success'] ? 'success' : 'danger'
                );
            }
            redirect("/team/case-details.php?id={$caseId}");

        // --- Complete Session ---
        } elseif ($action === 'complete_session') {
            $sessionId       = intval($_POST['session_id'] ?? 0);
            $sessionNotes    = sanitize($_POST['session_notes'] ?? '');
            $recommendations = sanitize($_POST['session_recommendations'] ?? '');
            $moodRating      = intval($_POST['mood_rating'] ?? 0) ?: null;
            $nextDate        = $_POST['next_session_date'] ?? null;

            if ($sessionId && strlen($sessionNotes) >= 5) {
                completeCounselingSession($sessionId, $sessionNotes, $recommendations, $moodRating, $nextDate ?: null);
                setFlashMessage('Session marked as completed.', 'success');
            } else {
                setFlashMessage('Session notes are required (min 5 characters).', 'danger');
            }
            redirect("/team/case-details.php?id={$caseId}");

        // --- Cancel Session ---
        } elseif ($action === 'cancel_session') {
            $sessionId = intval($_POST['session_id'] ?? 0);
            if ($sessionId) {
                $stmt = $db->prepare("UPDATE counseling_sessions SET status = 'cancelled' WHERE id = ? AND counselor_id = ?");
                $stmt->execute([$sessionId, $teamMemberId]);
                setFlashMessage('Session cancelled.', 'warning');
            }
            redirect("/team/case-details.php?id={$caseId}");

        // --- Request Closure ---
        // --- Request Closure ---
        } elseif ($action === 'request_closure') {
            if (notifyAdminForClosure($caseId, $teamMemberId)) {
                setFlashMessage('Closure request sent to admin successfully.', 'success');
            } else {
                setFlashMessage('Failed to send closure request.', 'danger');
            }
            redirect("/team/case-details.php?id={$caseId}");

        // --- Fix B: Recommend Counselor ---
        } elseif ($action === 'recommend_counselor') {
            $counselorId = intval($_POST['counselor_id'] ?? 0);
            if ($counselorId) {
                $result = assignCounselor($caseId, $counselorId, $teamMemberId);
                setFlashMessage(
                    $result['success'] ? 'Counselor assigned — case moved to counseling stage.' : ($result['error'] ?? 'Failed to assign counselor.'),
                    $result['success'] ? 'success' : 'danger'
                );
            } else {
                setFlashMessage('Please select a counselor.', 'danger');
            }
            redirect("/team/case-details.php?id={$caseId}");

        // --- Fix C: Recommend Program ---
        } elseif ($action === 'recommend_program') {
            $programType = $_POST['program_type'] ?? '';
            $programName = sanitize($_POST['program_name'] ?? '');
            $programDesc = sanitize($_POST['program_description'] ?? '');
            $startDate   = $_POST['program_start_date'] ?? null;
            $endDate     = $_POST['program_end_date'] ?? null;
            if ($programType && $programName) {
                $result = recommendProgram($caseId, $programType, $programName, $programDesc, $teamMemberId, $startDate ?: null, $endDate ?: null);
                setFlashMessage($result ? 'Program recommended successfully.' : 'Failed to recommend program.', $result ? 'success' : 'danger');
            } else {
                setFlashMessage('Program type and name are required.', 'danger');
            }
            redirect("/team/case-details.php?id={$caseId}");

        // --- Fix C: Update Program Status ---
        } elseif ($action === 'update_program_status') {
            $programId     = intval($_POST['program_id'] ?? 0);
            $programStatus = $_POST['program_status'] ?? '';
            $programNotes  = sanitize($_POST['program_notes'] ?? '');
            $validStatuses = ['recommended', 'enrolled', 'in_progress', 'completed', 'dropped'];
            if ($programId && in_array($programStatus, $validStatuses)) {
                updateProgramStatus($programId, $programStatus, $programNotes);
                setFlashMessage('Program status updated.', 'success');
            } else {
                setFlashMessage('Invalid program status.', 'danger');
            }
            redirect("/team/case-details.php?id={$caseId}");
        }
    }
}

// Fetch case details
$stmtCase = $db->prepare("
    SELECT c.*,
           u.full_name as user_name, u.id as user_id,
           n.organization_name as ngo_name, n.id as ngo_id
    FROM cases c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN ngos n ON c.ngo_id = n.id
    WHERE c.id = ? AND c.team_member_id = ?
");
$stmtCase->execute([$caseId, $teamMemberId]);
$case = $stmtCase->fetch();

if (!$case) {
    setFlashMessage('Case not found or access denied.', 'danger');
    redirect('/team/dashboard.php');
}

// Fetch counseling sessions
$sessions = getCounselingSessions($caseId);

// Compute session stats
$totalSessions     = count($sessions);
$completedSessions = count(array_filter($sessions, fn($s) => $s['status'] === 'completed'));
$scheduledSessions = count(array_filter($sessions, fn($s) => $s['status'] === 'scheduled'));
$nextSession       = null;
foreach (array_reverse($sessions) as $s) {
    if ($s['status'] === 'scheduled' && $s['session_date'] >= date('Y-m-d')) {
        $nextSession = $s;
        break;
    }
}

// Fetch progress history
$stmtHistory = $db->prepare("SELECT * FROM case_progress WHERE case_id = ? ORDER BY created_at DESC");
$stmtHistory->execute([$caseId]);
$progressHistory = $stmtHistory->fetchAll();

// Fetch satisfaction data if progress is 100%
$satisfaction = null;
if ((int)$case['progress_percentage'] === 100) {
    $stmtSat = $db->prepare("SELECT * FROM satisfaction_requests WHERE case_id = ? ORDER BY id DESC LIMIT 1");
    $stmtSat->execute([$caseId]);
    $satisfaction = $stmtSat->fetch();
}

$unreadMessages = getUnreadMessageCount('team_member', $teamMemberId);

// Fix A & C: Fetch NGO programs for this case
$programs = getCasePrograms($caseId);

// Fix B: Fetch available counselors (only if no counselor assigned yet)
$availableCounselors = [];
if (!$case['counselor_id'] && $case['status'] !== 'closed' && $case['status'] !== 'cancelled') {
    $stmtCounselors = $db->prepare("
        SELECT id, full_name, role, specialization, experience_years
        FROM team_members
        WHERE role IN ('counselor', 'psychiatrist')
          AND status = 'active'
          AND cases_assigned < max_cases
        ORDER BY full_name ASC
    ");
    $stmtCounselors->execute();
    $availableCounselors = $stmtCounselors->fetchAll();
}

$pageTitle = 'Case Details - ' . htmlspecialchars($case['case_number']);
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="py-4">
<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 mb-4">
            <div class="dashboard-sidebar">
                <nav class="nav flex-column">
                    <a class="nav-link" href="<?= url('/team/dashboard.php') ?>">
                        <i class="bi bi-speedometer2"></i>Dashboard
                    </a>
                    <a class="nav-link active" href="<?= url('/team/cases.php') ?>">
                        <i class="bi bi-folder"></i>My Cases
                    </a>
                    <a class="nav-link" href="<?= url('/team/chat.php') ?>">
                        <i class="bi bi-chat-dots"></i>Messages
                        <?php if ($unreadMessages > 0): ?>
                            <span class="badge bg-danger ms-2"><?= $unreadMessages ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="nav-link" href="<?= url('/team/profile.php') ?>">
                        <i class="bi bi-person"></i>My Profile
                    </a>
                    <hr>
                    <a class="nav-link text-danger" href="<?= url('/logout.php') ?>">
                        <i class="bi bi-box-arrow-left"></i>Logout
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">

            <?php $flash = getFlashMessage(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
                    <?= $flash['message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">
                        <i class="bi bi-folder2-open text-primary me-2"></i>
                        Case: <?= htmlspecialchars($case['case_number']) ?>
                    </h4>
                    <span class="badge bg-<?= getStatusBadge($case['status']) ?> fs-6">
                        <?= ucfirst(str_replace('_', ' ', $case['status'])) ?>
                    </span>
                </div>
                <a href="<?= url('/team/cases.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to My Cases
                </a>
            </div>

            <div class="row">

                <!-- LEFT COLUMN -->
                <div class="col-lg-8">

                    <!-- Case Overview -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-info-circle me-2 text-primary"></i>Case Overview</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <small class="text-muted text-uppercase fw-bold d-block">Patient</small>
                                    <span><?= htmlspecialchars($case['user_name']) ?></span>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted text-uppercase fw-bold d-block">NGO</small>
                                    <span><?= htmlspecialchars($case['ngo_name']) ?></span>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted text-uppercase fw-bold d-block">Issue Category</small>
                                    <span><?= ucfirst(str_replace('_', ' ', $case['issue_category'])) ?></span>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted text-uppercase fw-bold d-block">Severity</small>
                                    <span class="badge bg-<?= $case['severity_level'] === 'critical' || $case['severity_level'] === 'high' ? 'danger' : ($case['severity_level'] === 'medium' ? 'warning' : 'success') ?>">
                                        <?= ucfirst($case['severity_level']) ?>
                                    </span>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted text-uppercase fw-bold d-block mb-1">Progress</small>
                                    <div class="progress" style="height:10px;">
                                        <div class="progress-bar bg-primary" style="width:<?= (int)$case['progress_percentage'] ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?= (int)$case['progress_percentage'] ?>% complete</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SESSION STATS -->
                    <div class="row g-3 mb-4">
                        <div class="col-4">
                            <div class="card text-center shadow-sm border-0 text-bg-primary">
                                <div class="card-body py-3">
                                    <h3 class="mb-0 text-primary"><?= $totalSessions ?></h3>
                                    <small class="text-primary opacity-75">Total Sessions</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card text-center shadow-sm border-0 text-bg-success">
                                <div class="card-body py-3">
                                    <h3 class="mb-0 text-success"><?= $completedSessions ?></h3>
                                    <small class="text-success opacity-75">Completed</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card text-center shadow-sm border-0 text-bg-warning">
                                <div class="card-body py-3">
                                    <h3 class="mb-0 text-warning"><?= $scheduledSessions ?></h3>
                                    <small class="text-warning opacity-75">Upcoming</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SCHEDULE NEW SESSION (only if case is not closed) -->
                    <?php if ($case['status'] !== 'closed' && $case['status'] !== 'cancelled'): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-calendar-plus me-2 text-primary"></i>Schedule New Session</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="schedule_session">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Session Date <span class="text-danger">*</span></label>
                                        <input type="date" name="session_date" class="form-control"
                                               min="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Time</label>
                                        <input type="time" name="session_time" class="form-control">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Duration (min)</label>
                                        <select name="duration" class="form-select">
                                            <option value="30">30 min</option>
                                            <option value="45">45 min</option>
                                            <option value="60" selected>60 min</option>
                                            <option value="90">90 min</option>
                                            <option value="120">120 min</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Session Type</label>
                                        <select name="session_type" class="form-select">
                                            <option value="initial_assessment">Initial Assessment</option>
                                            <option value="regular" selected>Regular</option>
                                            <option value="follow_up">Follow Up</option>
                                            <option value="emergency">Emergency</option>
                                            <option value="group">Group</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-calendar-check me-1"></i> Schedule Session
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- SESSION LIST -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-calendar3 me-2 text-primary"></i>Counseling Sessions</h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($sessions)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                    No sessions scheduled yet.
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($sessions as $session): ?>
                                    <div class="list-group-item p-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="fw-semibold mb-1">
                                                    <?= date('D, d M Y', strtotime($session['session_date'])) ?>
                                                    <?php if ($session['session_time']): ?>
                                                        &nbsp;<i class="bi bi-clock text-muted"></i>
                                                        <?= date('h:i A', strtotime($session['session_time'])) ?>
                                                    <?php endif; ?>
                                                    <span class="badge bg-<?= getStatusBadge($session['status']) ?> ms-2">
                                                        <?= ucfirst($session['status']) ?>
                                                    </span>
                                                </div>
                                                <small class="text-muted">
                                                    <?= ucfirst(str_replace('_', ' ', $session['session_type'])) ?>
                                                    &middot; <?= $session['duration_minutes'] ?> min
                                                </small>
                                                <?php if ($session['notes']): ?>
                                                    <p class="small mb-1 mt-2"><strong>Notes:</strong> <?= htmlspecialchars_decode($session['notes'], ENT_QUOTES) ?></p>
                                                <?php endif; ?>
                                                <?php if ($session['recommendations']): ?>
                                                    <p class="small mb-1"><strong>Recommendations:</strong> <?= htmlspecialchars_decode($session['recommendations'], ENT_QUOTES) ?></p>
                                                <?php endif; ?>
                                                <?php if ($session['mood_rating']): ?>
                                                    <p class="small mb-0">
                                                        <strong>Mood:</strong>
                                                        <?php for ($i = 1; $i <= 10; $i++): ?>
                                                            <i class="bi bi-circle-fill small <?= $i <= $session['mood_rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                                        <?php endfor; ?>
                                                        <?= $session['mood_rating'] ?>/10
                                                    </p>
                                                <?php endif; ?>
                                                <?php if ($session['next_session_date']): ?>
                                                    <p class="small mb-0 text-info">
                                                        <i class="bi bi-calendar-arrow-up me-1"></i>
                                                        Next: <?= date('d M Y', strtotime($session['next_session_date'])) ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <?php if ($session['status'] === 'scheduled'): ?>
                                        <hr class="my-2">
                                        <form method="POST" class="mb-2">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="complete_session">
                                            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                            <div class="row g-2 align-items-end">
                                                <div class="col-md-5">
                                                    <label class="form-label small fw-semibold mb-1">Session Notes <span class="text-danger">*</span></label>
                                                    <textarea name="session_notes" class="form-control form-control-sm" rows="2"
                                                              placeholder="How did the session go..." required minlength="5"></textarea>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small fw-semibold mb-1">Recommendations</label>
                                                    <input type="text" name="session_recommendations"
                                                           class="form-control form-control-sm mb-1"
                                                           placeholder="Next steps, exercises...">
                                                    <div class="d-flex gap-2">
                                                        <input type="number" name="mood_rating" class="form-control form-control-sm"
                                                               placeholder="Mood 1-10" min="1" max="10">
                                                        <input type="date" name="next_session_date"
                                                               class="form-control form-control-sm"
                                                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-3 d-flex flex-column gap-2">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="bi bi-check-lg me-1"></i>Mark Complete
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="cancel_session">
                                            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm"
                                                    onclick="return confirm('Cancel this session?')">
                                                <i class="bi bi-x-circle me-1"></i>Cancel Session
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Update Progress -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-graph-up me-2 text-info"></i>Update Case Progress</h6>
                        </div>
                        <div class="card-body">
                            <form action="" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="update_progress">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        Progress: <span id="progress-val-text"><?= (int)$case['progress_percentage'] ?></span>%
                                    </label>
                                    <input type="range" class="form-range" name="progress_percentage"
                                           min="0" max="100" value="<?= (int)$case['progress_percentage'] ?>"
                                           oninput="document.getElementById('progress-val-text').innerText = this.value">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Progress Notes <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="progress_notes" rows="3" required minlength="20"
                                              placeholder="Describe progress and activities (min 20 characters)..."><?= htmlspecialchars_decode($case['progress_notes'] ?? '', ENT_QUOTES) ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Save Update
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Progress History -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Progress History</h6>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php if (empty($progressHistory)): ?>
                                    <li class="list-group-item text-center text-muted py-4">No progress history yet.</li>
                                <?php else: ?>
                                    <?php foreach ($progressHistory as $history): ?>
                                        <li class="list-group-item p-3">
                                            <div class="d-flex justify-content-between">
                                                <h6 class="mb-1 text-primary fw-bold"><?= htmlspecialchars($history['status']) ?></h6>
                                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?= date('d M Y, h:i A', strtotime($history['created_at'])) ?></small>
                                            </div>
                                            <p class="mb-0 mt-2 text-dark bg-light p-2 rounded small">
                                                <?= nl2br(htmlspecialchars($history['notes'])) ?>
                                            </p>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- FIX B: Recommend Counselor (only shown if no counselor assigned) -->
                    <?php if (!$case['counselor_id'] && !empty($availableCounselors) && $case['status'] !== 'closed'): ?>
                    <div class="card shadow-sm mb-4 border-warning">
                        <div class="card-header text-bg-warning">
                            <h6 class="mb-0 text-warning opacity-75"><i class="bi bi-person-heart me-2 text-warning"></i>Recommend a Counselor</h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">No counselor has been assigned yet. If this case requires counseling, select a counselor below.</p>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="recommend_counselor">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Select Counselor <span class="text-danger">*</span></label>
                                    <select name="counselor_id" class="form-select" required>
                                        <option value="">Choose a counselor...</option>
                                        <?php foreach ($availableCounselors as $c): ?>
                                            <option value="<?= $c['id'] ?>">
                                                <?= htmlspecialchars($c['full_name']) ?>
                                                (<?= ucfirst(str_replace('_', ' ', $c['role'])) ?>
                                                <?php if ($c['experience_years']): ?> · <?= $c['experience_years'] ?> yrs<?php endif; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-warning btn-sm"
                                        onclick="return confirm('Assign this counselor? The case status will move to Counseling.')">
                                    <i class="bi bi-person-check me-1"></i> Assign Counselor
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php elseif ($case['counselor_id']): ?>
                    <div class="card shadow-sm mb-4 border-success">
                        <div class="card-header text-bg-success">
                            <h6 class="mb-0 text-success opacity-75"><i class="bi bi-person-check-fill me-2"></i>Counselor Assigned</h6>
                        </div>
                        <div class="card-body py-2">
                            <?php
                                $stmtCo = $db->prepare("SELECT full_name, role, specialization FROM team_members WHERE id = ?");
                                $stmtCo->execute([$case['counselor_id']]);
                                $co = $stmtCo->fetch();
                            ?>
                            <p class="mb-1 fw-semibold"><?= htmlspecialchars($co['full_name'] ?? 'N/A') ?></p>
                            <p class="mb-0 text-muted small"><?= ucfirst(str_replace('_', ' ', $co['role'] ?? '')) ?>
                                <?php if (!empty($co['specialization'])): ?> · <?= htmlspecialchars($co['specialization']) ?><?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- FIX C: Recommend Program -->
                    <?php if ($case['status'] !== 'closed' && $case['status'] !== 'cancelled'): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-mortarboard me-2 text-primary"></i>Recommend Rehabilitation / Skill Development</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="recommend_program">
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <label class="form-label fw-semibold">Program Type <span class="text-danger">*</span></label>
                                        <select name="program_type" class="form-select" required>
                                            <option value="">Select type...</option>
                                            <option value="rehabilitation">Rehabilitation</option>
                                            <option value="skill_development">Skill Development</option>
                                        </select>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label fw-semibold">Program Name <span class="text-danger">*</span></label>
                                        <input type="text" name="program_name" class="form-control"
                                               placeholder="e.g., Emotional Resilience Training" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Description</label>
                                        <textarea name="program_description" class="form-control" rows="2"
                                                  placeholder="Goals and details of the program..."></textarea>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label fw-semibold">Start Date</label>
                                        <input type="date" name="program_start_date" class="form-control" min="<?= date('Y-m-d') ?>">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label fw-semibold">End Date</label>
                                        <input type="date" name="program_end_date" class="form-control">
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-plus-circle me-1"></i> Recommend Program
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- FIX A & C: NGO Programs / Services Panel -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-journals me-2 text-info"></i>Programs & Services (<?= count($programs) ?>)</h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($programs)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-journal-x fs-2 d-block mb-2"></i>
                                    No programs recommended yet.
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($programs as $prog): ?>
                                    <div class="list-group-item p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <span class="badge bg-<?= $prog['program_type'] === 'rehabilitation' ? 'info' : 'primary' ?> me-1">
                                                    <?= ucfirst(str_replace('_', ' ', $prog['program_type'])) ?>
                                                </span>
                                                <strong><?= htmlspecialchars($prog['program_name']) ?></strong>
                                            </div>
                                            <span class="badge bg-<?= ['recommended'=>'secondary','enrolled'=>'info','in_progress'=>'warning','completed'=>'success','dropped'=>'danger'][$prog['status']] ?? 'secondary' ?>">
                                                <?= ucfirst(str_replace('_', ' ', $prog['status'])) ?>
                                            </span>
                                        </div>
                                        <?php if ($prog['description']): ?>
                                            <p class="small text-muted mb-2"><?= htmlspecialchars_decode($prog['description'], ENT_QUOTES) ?></p>
                                        <?php endif; ?>
                                        <div class="text-muted small mb-2">
                                            <?php if ($prog['start_date']): ?><i class="bi bi-calendar me-1"></i>From: <?= date('d M Y', strtotime($prog['start_date'])) ?> <?php endif; ?>
                                            <?php if ($prog['end_date']): ?>To: <?= date('d M Y', strtotime($prog['end_date'])) ?><?php endif; ?>
                                            <?php if ($prog['recommended_by_name']): ?>&nbsp;· Recommended by: <?= htmlspecialchars($prog['recommended_by_name']) ?><?php endif; ?>
                                        </div>
                                        <!-- Fix C: Update program status inline -->
                                        <?php if ($prog['status'] !== 'completed' && $prog['status'] !== 'dropped' && $case['status'] !== 'closed'): ?>
                                        <form method="POST" class="d-flex gap-2 align-items-end flex-wrap mt-2">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="update_program_status">
                                            <input type="hidden" name="program_id" value="<?= $prog['id'] ?>">
                                            <div>
                                                <label class="form-label small fw-semibold mb-1">Update Status</label>
                                                <select name="program_status" class="form-select form-select-sm" style="width:160px" required>
                                                    <option value="">Select...</option>
                                                    <?php foreach(['recommended'=>'Recommended','enrolled'=>'Enrolled','in_progress'=>'In Progress','completed'=>'Completed','dropped'=>'Dropped'] as $val => $label): ?>
                                                        <option value="<?= $val ?>" <?= $prog['status']===$val?'selected':'' ?>><?= $label ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div style="flex:1;min-width:160px">
                                                <label class="form-label small fw-semibold mb-1">Notes (optional)</label>
                                                <input type="text" name="program_notes" class="form-control form-control-sm"
                                                       placeholder="Progress update...">
                                            </div>
                                            <button type="submit" class="btn btn-outline-primary btn-sm mb-0">
                                                <i class="bi bi-arrow-repeat me-1"></i>Update
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- RIGHT COLUMN -->
                <div class="col-lg-4">

                    <!-- Next Session Alert -->
                    <?php if ($nextSession): ?>
                    <div class="card shadow-sm mb-4 border-primary">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Next Upcoming Session</h6>
                        </div>
                        <div class="card-body">
                            <p class="fw-bold fs-5 mb-1"><?= date('D, d M Y', strtotime($nextSession['session_date'])) ?></p>
                            <?php if ($nextSession['session_time']): ?>
                                <p class="text-muted mb-1"><i class="bi bi-clock me-1"></i><?= date('h:i A', strtotime($nextSession['session_time'])) ?></p>
                            <?php endif; ?>
                            <p class="text-muted small mb-0">
                                <?= ucfirst(str_replace('_', ' ', $nextSession['session_type'])) ?>
                                &middot; <?= $nextSession['duration_minutes'] ?> min
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Quick Messages -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-chat-dots me-2"></i>Quick Messages</h6>
                        </div>
                        <div class="card-body d-grid gap-2">
                            <a href="<?= url('/team/chat-thread.php?with=user&id=' . $case['user_id']) ?>" class="btn btn-outline-primary btn-sm text-start">
                                <i class="bi bi-chat-fill me-2"></i> Message Patient
                            </a>
                            <a href="<?= url('/team/chat-thread.php?with=ngo&id=' . $case['ngo_id']) ?>" class="btn btn-outline-success btn-sm text-start">
                                <i class="bi bi-chat-text-fill me-2"></i> Message NGO
                            </a>
                            <a href="<?= url('/team/chat-thread.php?with=admin&id=1') ?>" class="btn btn-outline-secondary btn-sm text-start">
                                <i class="bi bi-shield-lock-fill me-2"></i> Message Admin
                            </a>
                        </div>
                    </div>

                    <!-- Satisfaction & Closure -->
                    <?php if ((int)$case['progress_percentage'] === 100): ?>
                    <div class="card shadow-sm mb-4 border-success">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="bi bi-check2-circle me-2"></i>Satisfaction & Closure</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($satisfaction): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-semibold small">Patient Response</span>
                                    <?php $uBadge = $satisfaction['user_response'] === 'satisfied' ? 'success' : ($satisfaction['user_response'] === 'not_satisfied' ? 'danger' : 'warning'); ?>
                                    <span class="badge bg-<?= $uBadge ?>"><?= ucfirst(str_replace('_', ' ', $satisfaction['user_response'])) ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="fw-semibold small">NGO Response</span>
                                    <?php $nBadge = $satisfaction['ngo_response'] === 'satisfied' ? 'success' : ($satisfaction['ngo_response'] === 'not_satisfied' ? 'danger' : 'warning'); ?>
                                    <span class="badge bg-<?= $nBadge ?>"><?= ucfirst(str_replace('_', ' ', $satisfaction['ngo_response'])) ?></span>
                                </div>

                                <?php if ($satisfaction['closure_request_sent'] == 1): ?>
                                    <div class="alert alert-info mb-0 text-center small">
                                        <i class="bi bi-info-circle me-1"></i> Closure request sent to admin.
                                    </div>
                                <?php elseif (checkBothSatisfied($case['id'])): ?>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="request_closure">
                                        <button type="submit" class="btn btn-success w-100 btn-sm">
                                            <i class="bi bi-check-circle me-1"></i>Request Case Closure
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-0 small text-center">
                                        <i class="bi bi-hourglass-split me-1"></i>
                                        Awaiting both parties to confirm satisfaction.
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-muted text-center small mb-0">Satisfaction check is initializing...</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>