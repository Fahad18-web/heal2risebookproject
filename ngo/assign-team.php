<?php
/**
 * Heal2Rise Book - Assign Team Member to Case
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin('ngo');

$ngoId = getCurrentUserId();
$db = getDB();
$caseId = $_GET['case_id'] ?? null;

if (!$caseId) {
    redirect('/ngo/cases.php');
}

// Get NGO data
$stmt = $db->prepare("SELECT * FROM ngos WHERE id = ?");
$stmt->execute([$ngoId]);
$ngo = $stmt->fetch();

// Verify the case belongs to this NGO
$stmt = $db->prepare("SELECT c.*, u.full_name as user_name 
                      FROM cases c 
                      JOIN users u ON c.user_id = u.id 
                      WHERE c.id = ? AND c.ngo_id = ?");
$stmt->execute([$caseId, $ngoId]);
$case = $stmt->fetch();

if (!$case) {
    $_SESSION['error'] = 'Case not found or access denied.';
    redirect('/ngo/cases.php');
}

// Get available team members (active members who aren't at max capacity)
$stmt = $db->prepare("
    SELECT tm.*, 
           (SELECT COUNT(*) FROM cases WHERE team_member_id = tm.id AND status NOT IN ('closed', 'resolved')) as current_cases
    FROM team_members tm 
    WHERE tm.ngo_id = ? AND tm.status = 'active'
    HAVING current_cases < tm.max_cases OR tm.max_cases IS NULL
    ORDER BY current_cases ASC, tm.full_name ASC
");
$stmt->execute([$ngoId]);
$availableMembers = $stmt->fetchAll();

// Get all team members for reference
$stmt = $db->prepare("
    SELECT tm.*, 
           (SELECT COUNT(*) FROM cases WHERE team_member_id = tm.id AND status NOT IN ('closed', 'resolved')) as current_cases
    FROM team_members tm 
    WHERE tm.ngo_id = ?
    ORDER BY tm.status DESC, tm.full_name ASC
");
$stmt->execute([$ngoId]);
$allMembers = $stmt->fetchAll();

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $teamMemberId = $_POST['team_member_id'] ?? null;
        $notes = sanitize($_POST['assignment_notes'] ?? '');
        
        if (empty($teamMemberId)) {
            $errors[] = 'Please select a team member.';
        } else {
            // Verify team member belongs to this NGO and is active
            $stmt = $db->prepare("SELECT * FROM team_members WHERE id = ? AND ngo_id = ? AND status = 'active'");
            $stmt->execute([$teamMemberId, $ngoId]);
            $teamMember = $stmt->fetch();
            
            if (!$teamMember) {
                $errors[] = 'Invalid team member selected.';
            } else {
                try {
                    $db->beginTransaction();
                    
                    // Update the case with assigned team member and status
                    $stmt = $db->prepare("UPDATE cases SET team_member_id = ?, status = 'assigned', assignment_date = NOW(), updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$teamMemberId, $caseId]);
                    
                    // Add progress note
                    $progressNote = "Case assigned to " . $teamMember['full_name'] . " (" . ucfirst($teamMember['role']) . ")";
                    if ($notes) {
                        $progressNote .= ". Notes: " . $notes;
                    }
                    
                    $stmt = $db->prepare("INSERT INTO case_progress (case_id, status, notes, updated_by, created_at) 
                                          VALUES (?, 'assigned', ?, ?, NOW())");
                    $stmt->execute([$caseId, $progressNote, $teamMemberId]);
                    
                    // Notify the user
                    $notificationMsg = "A team member has been assigned to your case: " . $teamMember['full_name'];
                    $stmt = $db->prepare("INSERT INTO notifications (user_type, user_id, title, message, created_at) 
                                          VALUES ('user', ?, 'Team Member Assigned', ?, NOW())");
                    $stmt->execute([$case['user_id'], $notificationMsg]);
                    
                    $db->commit();
                    
                    $_SESSION['success'] = 'Team member assigned successfully!';
                    redirect('/ngo/case-details.php?id=' . $caseId);
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $errors[] = 'An error occurred. Please try again.';
                }
            }
        }
    }
}

$pageTitle = 'Assign Team Member';
require_once __DIR__ . '/../includes/header.php';

// Helper function for role badge color
function getRoleBadgeColor($role) {
    $colors = [
        'counselor' => 'primary',
        'therapist' => 'info',
        'social_worker' => 'success',
        'psychiatrist' => 'warning',
        'coordinator' => 'secondary',
        'volunteer' => 'light'
    ];
    return $colors[$role] ?? 'secondary';
}
?>

<main id="main-content">
<div class="container py-5">
    <h2 class="page-title-soft">NGO Connection & Case Assignment</h2>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url('/ngo/dashboard.php') ?>">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= url('/ngo/cases.php') ?>">Cases</a></li>
            <li class="breadcrumb-item"><a href="<?= url('/ngo/case-details.php?id=' . $caseId) ?>">Case #<?= $caseId ?></a></li>
            <li class="breadcrumb-item active">Assign Team Member</li>
        </ol>
    </nav>
    
    <div class="row">
        <!-- Case Summary -->
        <div class="col-md-5 col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Case Summary</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">Case ID</label>
                        <p class="mb-0 fw-bold">#<?= $case['id'] ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Client Identifier</label>
                        <p class="mb-0">U-<?= str_pad((string)$case['user_id'], 5, '0', STR_PAD_LEFT) ?></p>
                        <small class="text-muted">Identity protected for privacy.</small>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Category</label>
                        <p class="mb-0">
                            <span class="badge bg-primary"><?= ucfirst(str_replace('_', ' ', $case['issue_category'])) ?></span>
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Priority</label>
                        <p class="mb-0">
                            <?php
                            $priorityColors = ['low' => 'success', 'medium' => 'warning', 'high' => 'danger', 'critical' => 'danger'];
                            $priorityColor = $priorityColors[$case['priority']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $priorityColor ?>"><?= ucfirst($case['priority']) ?></span>
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Status</label>
                        <p class="mb-0">
                            <span class="badge bg-<?= getStatusBadge($case['status']) ?>">
                                <?= ucfirst(str_replace('_', ' ', $case['status'])) ?>
                            </span>
                        </p>
                    </div>
                    <div class="security-note mb-3">
                        <i class="bi bi-shield-check"></i>
                        <div>Assign the most suitable available team member while preserving client confidentiality.</div>
                    </div>
                    <div class="mb-0">
                        <label class="text-muted small">Submitted</label>
                        <p class="mb-0"><?= formatDate($case['created_at']) ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Current Assignment -->
            <?php if ($case['team_member_id']): ?>
                <?php
                $stmt = $db->prepare("SELECT * FROM team_members WHERE id = ?");
                $stmt->execute([$case['team_member_id']]);
                $currentMember = $stmt->fetch();
                ?>
                <div class="card mt-4 border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Currently Assigned</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong><?= htmlspecialchars($currentMember['full_name']) ?></strong></p>
                        <p class="text-muted small mb-0"><?= ucfirst(str_replace('_', ' ', $currentMember['role'])) ?></p>
                        <hr>
                        <p class="text-muted small mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Selecting a new team member will replace the current assignment.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Assignment Form -->
        <div class="col-md-7 col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>Assign Team Member</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $error ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($availableMembers)): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>No available team members!</strong><br>
                            All team members are either inactive or at maximum case capacity.
                            <a href="<?= url('/ngo/team.php?action=add') ?>" class="alert-link">Add a new team member</a>.
                        </div>
                    <?php else: ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">Select Team Member <span class="text-danger">*</span></label>
                                <p class="text-muted small mb-3">Choose the most suitable team member based on their specialization and current workload.</p>
                                
                                <div class="row g-3">
                                    <?php foreach ($availableMembers as $member): ?>
                                        <div class="col-md-6">
                                            <div class="card team-member-card h-100" onclick="selectMember(<?= $member['id'] ?>)">
                                                <div class="card-body">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="team_member_id" 
                                                               id="member_<?= $member['id'] ?>" value="<?= $member['id'] ?>" required>
                                                        <label class="form-check-label w-100" for="member_<?= $member['id'] ?>">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <h6 class="mb-1"><?= htmlspecialchars($member['full_name']) ?></h6>
                                                                    <span class="badge bg-<?= getRoleBadgeColor($member['role']) ?> mb-2">
                                                                        <?= ucfirst(str_replace('_', ' ', $member['role'])) ?>
                                                                    </span>
                                                                </div>
                                                                <span class="badge bg-light text-dark">
                                                                    <?= $member['current_cases'] ?>/<?= $member['max_cases'] ?: '∞' ?> cases
                                                                </span>
                                                            </div>
                                                            
                                                            <?php if ($member['specialization']): ?>
                                                                <p class="text-muted small mb-1">
                                                                    <i class="bi bi-star me-1"></i>
                                                                    <?= htmlspecialchars($member['specialization']) ?>
                                                                </p>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($member['experience_years']): ?>
                                                                <p class="text-muted small mb-0">
                                                                    <i class="bi bi-briefcase me-1"></i>
                                                                    <?= $member['experience_years'] ?> years experience
                                                                </p>
                                                            <?php endif; ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="assignment_notes" class="form-label fw-bold">Assignment Notes</label>
                                <textarea name="assignment_notes" id="assignment_notes" class="form-control" rows="3" 
                                          placeholder="Add any notes or special instructions for the team member..."></textarea>
                                <div class="form-text">Optional: Include any relevant information for the assigned team member.</div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-2"></i>Assign Team Member
                                </button>
                                <a href="<?= url('/ngo/case-details.php?id=' . $caseId) ?>" class="btn btn-outline-secondary">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- All Team Members Reference -->
            <?php if (count($allMembers) > count($availableMembers)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-people me-2"></i>All Team Members</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Some team members are unavailable due to status or workload:</p>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Cases</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allMembers as $member): ?>
                                        <?php 
                                        $isAvailable = $member['status'] === 'active' && 
                                                       ($member['max_cases'] === null || $member['current_cases'] < $member['max_cases']);
                                        ?>
                                        <tr class="<?= !$isAvailable ? 'text-muted' : '' ?>">
                                            <td><?= htmlspecialchars($member['full_name']) ?></td>
                                            <td><?= ucfirst(str_replace('_', ' ', $member['role'])) ?></td>
                                            <td><?= $member['current_cases'] ?>/<?= $member['max_cases'] ?: '∞' ?></td>
                                            <td>
                                                <?php if ($member['status'] !== 'active'): ?>
                                                    <span class="badge bg-secondary"><?= ucfirst($member['status']) ?></span>
                                                <?php elseif ($member['max_cases'] && $member['current_cases'] >= $member['max_cases']): ?>
                                                    <span class="badge bg-warning text-dark">At Capacity</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Available</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</main>

<script>
function selectMember(memberId) {
    document.getElementById('member_' + memberId).checked = true;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
