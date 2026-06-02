<?php
/**
 * Heal2Rise Book - User Case Details
 * Shows case info, progress, sessions, programs, and assigned personnel
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('user');

$userId = getCurrentUserId();
$caseId = intval($_GET['id'] ?? 0);

if (!$caseId) {
    redirect('/user/cases.php');
    exit;
}

$db = getDB();

// Handle Satisfaction Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_satisfaction') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('Invalid request. Please try again.', 'danger');
    } else {
        $satisfaction = $_POST['satisfaction'] ?? '';
        
        if (in_array($satisfaction, ['satisfied', 'not_satisfied'])) {
            $stmtUpdate = $db->prepare("UPDATE satisfaction_requests SET user_response = ?, user_responded_at = NOW() WHERE case_id = ?");
            $stmtUpdate->execute([$satisfaction, $caseId]);
            
            if ($satisfaction === 'satisfied') {
                if (checkBothSatisfied($caseId)) {
                    notifyAdminForClosure($caseId);
                }
                setFlashMessage('Thank you for confirming your satisfaction!', 'success');
            } else {
                setFlashMessage('Your team member has been notified that you need further support.', 'warning');
            }
            redirect("/user/case-details.php?id={$caseId}");
            exit;
        }
    }
}

// Get case details (only if belongs to this user)
$stmt = $db->prepare("
    SELECT c.*, 
           n.organization_name, n.phone as ngo_phone, n.email as ngo_email, n.address as ngo_address,
           n.city as ngo_city, n.state as ngo_state, n.website as ngo_website,
           tm.full_name as team_member_name, tm.role as team_member_role, tm.email as team_member_email,
           co.full_name as counselor_name, co.role as counselor_role, co.email as counselor_email
    FROM cases c
    LEFT JOIN ngos n ON c.ngo_id = n.id
    LEFT JOIN team_members tm ON c.team_member_id = tm.id
    LEFT JOIN team_members co ON c.counselor_id = co.id
    WHERE c.id = ? AND c.user_id = ?
");
$stmt->execute([$caseId, $userId]);
$case = $stmt->fetch();

if (!$case) {
    setFlashMessage('Case not found.', 'danger');
    redirect('/user/cases.php');
    exit;
}

// Get case progress history
$stmt = $db->prepare("
    SELECT cp.*, tm.full_name as updated_by_name, tm.role as updated_by_role
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
                        <i class="bi bi-person-circle display-4 text-primary"></i>
                        <h6 class="mt-2 mb-0"><?= htmlspecialchars($_SESSION['user_data']['name'] ?? 'User') ?></h6>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?= url('/user/dashboard.php') ?>">
                            <i class="bi bi-speedometer2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="<?= url('/user/profile.php') ?>">
                            <i class="bi bi-person"></i>My Profile
                        </a>
                        <a class="nav-link active" href="<?= url('/user/cases.php') ?>">
                            <i class="bi bi-folder"></i>My Cases
                        </a>
                        <a class="nav-link" href="<?= url('/user/notifications.php') ?>">
                            <i class="bi bi-bell"></i>Notifications
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
                        <a href="<?= url('/user/cases.php') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                            <i class="bi bi-arrow-left me-2"></i>Back to My Cases
                        </a>
                        <h4 class="mb-0">Case: <?= htmlspecialchars($case['case_number']) ?></h4>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-<?= getStatusBadge($case['status']) ?> fs-6">
                            <?= ucfirst(str_replace('_', ' ', $case['status'])) ?>
                        </span>
                        <?php if (!empty($case['ngo_id'])): ?>
                            <div class="mt-2">
                                <a href="<?= url('/donation.php?ngo_id=' . intval($case['ngo_id']) . '&case_id=' . intval($case['id'])) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-heart me-1"></i>Donate to this Case
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row">
                    <!-- Main Content -->
                    <div class="col-md-8 col-lg-8">
                        <!-- Case Overview -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-info-circle me-2"></i>Case Overview
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <strong>Issue Category:</strong><br>
                                        <span class="badge bg-info"><?= ucfirst(str_replace('_', ' ', $case['issue_category'])) ?></span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Severity Level:</strong><br>
                                        <span class="badge bg-<?= $case['severity_level'] === 'high' || $case['severity_level'] === 'critical' ? 'danger' : ($case['severity_level'] === 'medium' ? 'warning' : 'success') ?>">
                                            <?= ucfirst($case['severity_level']) ?>
                                        </span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Priority:</strong><br>
                                        <span class="badge bg-<?= $case['priority'] === 'urgent' ? 'danger' : ($case['priority'] === 'high' ? 'warning' : 'secondary') ?>">
                                            <?= ucfirst($case['priority']) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <strong>Your Description:</strong>
                                <div class="bg-light p-3 rounded mt-2">
                                    <?= nl2br(htmlspecialchars_decode($case['description'], ENT_QUOTES)) ?>
                                </div>
                                
                                <?php if ($case['closure_remarks']): ?>
                                    <hr>
                                    <strong>Closure Remarks:</strong>
                                    <div class="bg-success bg-opacity-10 p-3 rounded mt-2">
                                        <?= nl2br(htmlspecialchars($case['closure_remarks'])) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <small class="text-muted"><strong>Created:</strong> <?= formatDate($case['created_at'], 'M d, Y H:i') ?></small>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted"><strong>Last Updated:</strong> <?= formatDate($case['updated_at'], 'M d, Y H:i') ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Case Progress Bar -->
                        <div class="card mb-4 shadow-sm border-0">
                            <div class="card-header bg-white border-bottom-0 pb-0">
                                <i class="bi bi-bar-chart-fill me-2 text-primary"></i><strong>Case Progress Tracker</strong>
                            </div>
                            <div class="card-body">
                                <?php 
                                    $prog = (int)($case['progress_percentage'] ?? 0); 
                                    $progColor = $prog == 100 ? 'success' : ($prog >= 50 ? 'warning' : 'danger');
                                ?>
                                <div class="progress shadow-sm" style="height: 25px; border-radius: 15px;">
                                    <div class="progress-bar bg-<?= $progColor ?> progress-bar-striped progress-bar-animated" role="progressbar" 
                                         style="width: <?= $prog ?>%;" aria-valuenow="<?= $prog ?>" aria-valuemin="0" aria-valuemax="100">
                                        <span class="fw-bold text-white d-block w-100 text-center" style="font-size: 1rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);"><?= $prog ?>%</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Satisfaction Confirmation Action -->
                        <?php
                        $stmtCheckReq = $db->prepare("SELECT * FROM satisfaction_requests WHERE case_id = ? AND user_response = 'pending'");
                        $stmtCheckReq->execute([$caseId]);
                        $pendingRequest = $stmtCheckReq->fetch();

                        if ($pendingRequest):
                        ?>
                        <div class="card mb-4 border-success align-items-center text-center shadow-sm">
                            <div class="card-body p-4 w-100">
                                <i class="bi bi-check-circle display-4 text-success mb-3"></i>
                                <h5 class="mb-2">Your case has reached 100% progress.</h5>
                                <p class="text-muted mb-4">Are you satisfied with the support you received?</p>
                                
                                <form method="POST" action="" class="d-flex justify-content-center gap-3">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="submit_satisfaction">
                                    
                                    <button type="submit" name="satisfaction" value="satisfied" class="btn btn-success btn-lg px-4 rounded-pill shadow-sm">
                                        ✅ Yes, I am satisfied
                                    </button>
                                    <button type="submit" name="satisfaction" value="not_satisfied" class="btn btn-outline-danger btn-lg px-4 rounded-pill">
                                        ❌ Not yet
                                    </button>
                                </form>
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
                                    <?php if ($session['status'] === 'completed' && $session['recommendations']): ?>
                                        <p class="small mb-0"><strong>Recommendations:</strong> <?= htmlspecialchars($session['recommendations']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($session['mood_rating']): ?>
                                        <p class="small mb-0"><strong>Mood Rating:</strong> <?= $session['mood_rating'] ?>/10</p>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Programs -->
                        <?php if (!empty($programs)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-mortarboard me-2"></i>Programs
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
                                        <p class="text-muted small mb-1"><?= htmlspecialchars_decode($program['description'], ENT_QUOTES) ?></p>
                                    <?php endif; ?>
                                    <?php if ($program['recommended_by_name']): ?>
                                        <small class="text-muted"><i class="bi bi-person me-1"></i>Recommended by: <?= htmlspecialchars($program['recommended_by_name']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Progress Timeline -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-clock-history me-2"></i>Progress Updates
                            </div>
                            <div class="card-body">
                                <?php if (empty($progressHistory)): ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-hourglass-split display-4 text-muted mb-3"></i>
                                        <p class="text-muted mb-0">Your case is being processed. Updates will appear here.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="timeline">
                                        <?php foreach ($progressHistory as $progress): ?>
                                            <div class="timeline-item mb-4 pb-3 border-bottom">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <span class="badge bg-<?= getStatusBadge($progress['status']) ?> mb-2">
                                                            <?= ucfirst(str_replace('_', ' ', $progress['status'])) ?>
                                                        </span>
                                                        <p class="mb-2"><?= nl2br(htmlspecialchars($progress['notes'])) ?></p>
                                                        <?php if ($progress['updated_by_name']): ?>
                                                            <small class="text-muted">
                                                                <i class="bi bi-person me-1"></i>
                                                                <?= htmlspecialchars($progress['updated_by_name']) ?> 
                                                                (<?= ucfirst($progress['updated_by_role']) ?>)
                                                            </small>
                                                        <?php endif; ?>
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
                        <!-- Status Card -->
                        <div class="card mb-4 border-<?= getStatusBadge($case['status']) ?>">
                            <div class="card-header bg-<?= getStatusBadge($case['status']) ?> text-white">
                                <i class="bi bi-flag me-2"></i>Current Status
                            </div>
                            <div class="card-body text-center">
                                <h4 class="text-<?= getStatusBadge($case['status']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $case['status'])) ?>
                                </h4>
                                <p class="text-muted mb-0">
                                    <?php
                                    switch ($case['status']) {
                                        case 'pending':
                                            echo 'Your case is being reviewed.';
                                            break;
                                        case 'assigned':
                                            echo 'A team member has been assigned to your case.';
                                            break;
                                        case 'in_progress':
                                            echo 'Your case is actively being worked on.';
                                            break;
                                        case 'counseling':
                                            echo 'You are currently in counseling sessions.';
                                            break;
                                        case 'rehabilitation':
                                            echo 'You are enrolled in a rehabilitation program.';
                                            break;
                                        case 'skill_development':
                                            echo 'You are in a skill development program.';
                                            break;
                                        case 'follow_up':
                                            echo 'Follow-up support is being provided.';
                                            break;
                                        case 'closed':
                                            echo 'Your case has been successfully resolved. We wish you well!';
                                            break;
                                        default:
                                            echo 'Processing your request.';
                                    }
                                    ?>
                                </p>
                                <?php if ($case['actual_end_date']): ?>
                                    <p class="text-muted small mt-2 mb-0">Closed on <?= formatDate($case['actual_end_date']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- NGO Info -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-building me-2"></i>Assigned Organization
                            </div>
                            <div class="card-body">
                                <?php if ($case['organization_name']): ?>
                                    <h5 class="mb-3"><?= htmlspecialchars($case['organization_name']) ?></h5>
                                    
                                    <p class="mb-2">
                                        <i class="bi bi-envelope text-primary me-2"></i>
                                        <a href="mailto:<?= htmlspecialchars($case['ngo_email']) ?>"><?= htmlspecialchars($case['ngo_email']) ?></a>
                                    </p>
                                    <p class="mb-2">
                                        <i class="bi bi-telephone text-primary me-2"></i>
                                        <a href="tel:<?= htmlspecialchars($case['ngo_phone']) ?>"><?= htmlspecialchars($case['ngo_phone']) ?></a>
                                    </p>
                                    <p class="mb-2">
                                        <i class="bi bi-geo-alt text-primary me-2"></i>
                                        <?= htmlspecialchars($case['ngo_city'] . ', ' . $case['ngo_state']) ?>
                                    </p>
                                    <?php if ($case['ngo_website']): ?>
                                        <p class="mb-0">
                                            <i class="bi bi-globe text-primary me-2"></i>
                                            <a href="<?= htmlspecialchars($case['ngo_website']) ?>" target="_blank" rel="noopener">Visit Website</a>
                                        </p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="bi bi-hourglass-split display-4 text-muted mb-3"></i>
                                        <p class="text-muted mb-0">An organization will be assigned to your case soon.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Team Member Info -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-person-badge me-2"></i>Your Team Member
                            </div>
                            <div class="card-body">
                                <?php if ($case['team_member_name']): ?>
                                    <div class="text-center mb-3">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($case['team_member_name']) ?>&background=4A90A4&color=fff&size=80" 
                                             class="rounded-circle mb-2" width="80" height="80" alt="Team Member">
                                        <h5 class="mb-1"><?= htmlspecialchars($case['team_member_name']) ?></h5>
                                        <span class="badge bg-primary"><?= ucfirst(str_replace('_', ' ', $case['team_member_role'])) ?></span>
                                    </div>
                                    <hr>
                                    <p class="mb-0">
                                        <i class="bi bi-envelope text-primary me-2"></i>
                                        <a href="mailto:<?= htmlspecialchars($case['team_member_email']) ?>"><?= htmlspecialchars($case['team_member_email']) ?></a>
                                    </p>
                                    
                                    <!-- Team Member Chat Button -->
                                    <div class="mt-3">
                                        <a href="<?= url('/user/chat-reply.php?case_id=' . $caseId) ?>" class="btn btn-primary w-100 rounded-pill shadow-sm">
                                            💬 Message Team Member
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="bi bi-person-plus display-4 text-muted mb-3"></i>
                                        <p class="text-muted mb-0">A team member will be assigned to you soon.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Counselor Info -->
                        <?php if ($case['counselor_id']): ?>
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-heart-pulse me-2"></i>Your Counselor
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($case['counselor_name']) ?>&background=6BCB77&color=fff&size=80" 
                                         class="rounded-circle mb-2" width="80" height="80" alt="Counselor">
                                    <h5 class="mb-1"><?= htmlspecialchars($case['counselor_name']) ?></h5>
                                    <span class="badge bg-success"><?= ucfirst(str_replace('_', ' ', $case['counselor_role'])) ?></span>
                                </div>
                                <hr>
                                <p class="mb-2">
                                    <i class="bi bi-envelope text-primary me-2"></i>
                                    <a href="mailto:<?= htmlspecialchars($case['counselor_email']) ?>"><?= htmlspecialchars($case['counselor_email']) ?></a>
                                </p>
                                <p class="mb-0">
                                    <strong>Sessions:</strong> <?= count($sessions) ?> total,
                                    <?= count(array_filter($sessions, fn($s) => $s['status'] === 'completed')) ?> completed
                                </p>
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