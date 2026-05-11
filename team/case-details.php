<?php
/**
 * Heal2Rise Book - Team Member Case Detail
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login
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
        
        if ($action === 'update_progress') {
            $progressPercentage = (int)($_POST['progress_percentage'] ?? 0);
            $progressNotes = sanitize($_POST['progress_notes'] ?? '');
            
            if (strlen($progressNotes) < 20) {
                setFlashMessage('Progress notes must be at least 20 characters.', 'danger');
            } elseif ($progressPercentage < 0 || $progressPercentage > 100) {
                setFlashMessage('Invalid progress percentage.', 'danger');
            } else {
                // UPDATE cases SET progress_percentage = ?, progress_notes = ? WHERE id = ?
                $stmt = $db->prepare("UPDATE cases SET progress_percentage = ?, progress_notes = ? WHERE id = ? AND team_member_id = ?");
                if ($stmt->execute([$progressPercentage, $progressNotes, $caseId, $teamMemberId])) {
                    
                    // INSERT into case_progress history
                    $statusText = "Progress updated to {$progressPercentage}%";
                    $stmtProg = $db->prepare("INSERT INTO case_progress (case_id, status, notes, updated_by) VALUES (?, ?, ?, ?)");
                    $stmtProg->execute([$caseId, $statusText, $progressNotes, $teamMemberId]);
                    
                    // Fetch case details for sending notification
                    $stmtCaseDet = $db->prepare("SELECT user_id, ngo_id, case_number FROM cases WHERE id = ?");
                    $stmtCaseDet->execute([$caseId]);
                    $cDet = $stmtCaseDet->fetch();
                    
                    if ($cDet) {
                        sendNotification('user', $cDet['user_id'], 'Case Progress Updated', "Your case #{$cDet['case_number']} progress is now {$progressPercentage}%.", 'info');
                        sendNotification('ngo', $cDet['ngo_id'], 'Case Progress Updated', "Case #{$cDet['case_number']} progress updated to {$progressPercentage}%.", 'info');
                    }
                    
                    // IF progress_percentage = 100
                    if ($progressPercentage === 100) {
                        requestSatisfactionCheck($caseId, $teamMemberId);
                    }
                    
                    setFlashMessage('Case progress updated successfully.', 'success');
                } else {
                    setFlashMessage('Failed to update case progress.', 'danger');
                }
            }
            redirect("/team/case-detail.php?id={$caseId}");
            
        } elseif ($action === 'request_closure') {
            if (notifyAdminForClosure($caseId, $teamMemberId)) {
                setFlashMessage('Closure request sent to admin successfully.', 'success');
            } else {
                setFlashMessage('Failed to send closure request.', 'danger');
            }
            redirect("/team/case-detail.php?id={$caseId}");
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

// Fetch progress history
$stmtHistory = $db->prepare("SELECT * FROM case_progress WHERE case_id = ? ORDER BY created_at DESC");
$stmtHistory->execute([$caseId]);
$progressHistory = $stmtHistory->fetchAll();

// Fetch satisfaction requests if progress is 100
$satisfaction = null;
if ((int)$case['progress_percentage'] === 100) {
    $stmtSat = $db->prepare("SELECT * FROM satisfaction_requests WHERE case_id = ? ORDER BY id DESC LIMIT 1");
    $stmtSat->execute([$caseId]);
    $satisfaction = $stmtSat->fetch();
}

$pageTitle = 'Case Details - ' . htmlspecialchars($case['case_number']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Placeholder -->
        <div class="col-md-3 col-lg-2 d-md-block dashboard-sidebar collapse">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('/team/dashboard.php') ?>">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?= url('/team/cases.php') ?>">
                            <i class="bi bi-folder2-open"></i> My Cases
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('/team/chat.php') ?>">
                            <i class="bi bi-chat-dots"></i> Messages
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            
            <?php $flash = getFlashMessage(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>"><?= $flash['message'] ?></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Case Detail</h2>
                <a href="<?= url('/team/dashboard.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <!-- SECTION 1: Case Overview -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Case Overview</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-6 mb-3">
                                    <small class="text-muted text-uppercase fw-bold">Case Number</small>
                                    <p class="fs-5 mb-0"><strong><?= htmlspecialchars($case['case_number']) ?></strong></p>
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <small class="text-muted text-uppercase fw-bold">Status</small>
                                    <p class="mb-0">
                                        <span class="badge bg-secondary fs-6"><?= ucfirst(str_replace('_', ' ', htmlspecialchars($case['status']))) ?></span>
                                    </p>
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <small class="text-muted text-uppercase fw-bold">Issue Category</small>
                                    <p class="mb-0"><?= ucfirst(str_replace('_', ' ', htmlspecialchars($case['issue_category']))) ?></p>
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <small class="text-muted text-uppercase fw-bold">Severity Level</small>
                                    <p class="mb-0"><?= ucfirst(htmlspecialchars($case['severity_level'])) ?></p>
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <small class="text-muted text-uppercase fw-bold">User</small>
                                    <p class="mb-0"><?= htmlspecialchars($case['user_name']) ?></p>
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <small class="text-muted text-uppercase fw-bold">Assigned NGO</small>
                                    <p class="mb-0"><?= htmlspecialchars($case['ngo_name']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: Progress Update Form -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Update Progress</h5>
                        </div>
                        <div class="card-body">
                            <form action="" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="update_progress">
                                
                                <div class="mb-3">
                                    <label for="progress_percentage" class="form-label fw-bold">Progress Percentage: <span id="progress-val-text"><?= (int)$case['progress_percentage'] ?></span>%</label>
                                    <input type="range" class="form-range" name="progress_percentage" id="progress_percentage" min="0" max="100" value="<?= (int)$case['progress_percentage'] ?>" oninput="document.getElementById('progress-val-text').innerText = this.value">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="progress_notes" class="form-label fw-bold">Progress Notes</label>
                                    <textarea class="form-control" name="progress_notes" id="progress_notes" rows="4" required minlength="20" placeholder="Describe the updates and activities performed with at least 20 characters..."><?= htmlspecialchars($case['progress_notes'] ?? '') ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Update</button>
                            </form>
                        </div>
                    </div>

                    <!-- SECTION 4: Progress History Timeline -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Progress History Timeline</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php if (empty($progressHistory)): ?>
                                    <li class="list-group-item text-center text-muted py-4">No progress history logs found.</li>
                                <?php else: ?>
                                    <?php foreach ($progressHistory as $history): ?>
                                        <li class="list-group-item p-3">
                                            <div class="d-flex justify-content-between">
                                                <h6 class="mb-1 text-primary fw-bold"><?= htmlspecialchars($history['status']) ?></h6>
                                                <small class="text-muted"><i class="bi bi-clock"></i> <?= date('d M Y, h:i A', strtotime($history['created_at'])) ?></small>
                                            </div>
                                            <p class="mb-0 mt-2 text-dark bg-light p-2 rounded"><?= nl2br(htmlspecialchars($history['notes'])) ?></p>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- SECTION 5: Quick Message Button -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">Quick Messages</h5>
                        </div>
                        <div class="card-body d-grid gap-2">
                            <a href="<?= url('/team/chat-thread.php?with=user&id=' . $case['user_id']) ?>" class="btn btn-outline-primary text-start">
                                <i class="bi bi-chat-fill me-2"></i> Message User
                            </a>
                            <a href="<?= url('/team/chat-thread.php?with=ngo&id=' . $case['ngo_id']) ?>" class="btn btn-outline-success text-start">
                                <i class="bi bi-chat-text-fill me-2"></i> Message NGO
                            </a>
                            <a href="<?= url('/team/chat-thread.php?with=admin&id=1') ?>" class="btn btn-outline-danger text-start">
                                <i class="bi bi-shield-lock-fill me-2"></i> Message Admin
                            </a>
                        </div>
                    </div>

                    <!-- SECTION 3: Satisfaction Status -->
                    <?php if ((int)$case['progress_percentage'] === 100): ?>
                    <div class="card shadow-sm mb-4 border-success">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Satisfaction Status</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($satisfaction): ?>
                                <div class="mb-3 d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">User Response:</span>
                                    <?php 
                                        $uBadge = 'warning';
                                        if($satisfaction['user_response'] == 'satisfied') $uBadge = 'success';
                                        if($satisfaction['user_response'] == 'not_satisfied') $uBadge = 'danger';
                                    ?>
                                    <span class="badge bg-<?= $uBadge ?> p-2"><?= ucfirst(str_replace('_', ' ', $satisfaction['user_response'])) ?></span>
                                </div>
                                
                                <div class="mb-4 d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">NGO Response:</span>
                                    <?php 
                                        $nBadge = 'warning';
                                        if($satisfaction['ngo_response'] == 'satisfied') $nBadge = 'success';
                                        if($satisfaction['ngo_response'] == 'not_satisfied') $nBadge = 'danger';
                                    ?>
                                    <span class="badge bg-<?= $nBadge ?> p-2"><?= ucfirst(str_replace('_', ' ', $satisfaction['ngo_response'])) ?></span>
                                </div>

                                <?php if ($satisfaction['closure_request_sent'] == 1): ?>
                                    <div class="alert alert-info mb-0 text-center">
                                        <i class="bi bi-info-circle me-1"></i> Closure request sent to admin
                                    </div>
                                <?php elseif (checkBothSatisfied($case['id'])): ?>
                                    <form method="POST" action="">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="request_closure">
                                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-check-circle me-1"></i> Request Admin to Close Case</button>
                                    </form>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-0 p-2 text-center text-sm">
                                        <small><i class="bi bi-hourglass-split"></i> Awaiting both parties to confirm satisfaction before case can be closed.</small>
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <p class="text-muted text-center mb-0">Satisfaction request data is initializing...</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
