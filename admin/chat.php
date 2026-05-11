<?php
/**
 * Heal2Rise Book - Admin Chat & Case Closure
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login
requireLogin('admin');

$adminId = getCurrentUserId();
$db = getDB();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('Invalid request. Please try again.', 'danger');
    } else {
        $action = $_POST['action'] ?? '';
        
        // Handle Case Closure
        if ($action === 'close_case') {
            $caseId         = intval($_POST['case_id'] ?? 0);
            $closureRemarks = trim($_POST['closure_remarks'] ?? 'Case closed after both parties confirmed satisfaction.');
            $markRecovered  = !empty($_POST['mark_recovered']);

            if ($caseId) {
                // Mark satisfaction request as approved before closing
                $db->prepare("UPDATE satisfaction_requests SET admin_decision = 'approved' WHERE case_id = ?")
                   ->execute([$caseId]);

                $result = adminCloseCase($caseId, $closureRemarks, $adminId, $markRecovered);

                if ($result['success']) {
                    setFlashMessage('Case officially closed.', 'success');
                } else {
                    setFlashMessage('Could not close case: ' . ($result['error'] ?? 'Unknown error'), 'danger');
                }
            }
            redirect('/admin/chat.php');
        }
        
        // Handle Sending Message
        if ($action === 'send_message') {
            $receiverId = intval($_POST['receiver_id'] ?? 0);
            // Do NOT sanitize here — sendChatMessage stores raw text, display uses htmlspecialchars
            $msgText = trim($_POST['message'] ?? '');
            
            if (empty($msgText) || strlen($msgText) > 1000) {
                setFlashMessage('Message must be between 1 and 1000 characters.', 'warning');
            } else if ($receiverId) {
                $result = sendChatMessage('admin', $adminId, 'team_member', $receiverId, $msgText, null);
                if (!$result['success']) {
                    setFlashMessage($result['error'], 'danger');
                }
            }
            redirect("/admin/chat.php?with=team_member&id={$receiverId}");
        }
    }
}

// Fetch Closure Requests
$stmtReq = $db->prepare("
    SELECT sr.*, c.case_number, u.full_name as user_name
    FROM satisfaction_requests sr
    JOIN cases c ON sr.case_id = c.id
    JOIN users u ON c.user_id = u.id
    WHERE sr.closure_request_sent = 1 AND sr.admin_decision = 'pending'
");
$stmtReq->execute();
$closureRequests = $stmtReq->fetchAll();

// Fetch All Team Members for Chat Navigation
$stmtMembers = $db->prepare("SELECT id, full_name, category, status FROM team_members ORDER BY full_name");
$stmtMembers->execute();
$teamMembers = $stmtMembers->fetchAll();

// Prepare Chat Thread if a specific user is selected
$activeChatId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$activeWith = $_GET['with'] ?? '';

$messages = [];
$activeMemberName = '';
$activeMemberCategory = '';

if ($activeWith === 'team_member' && $activeChatId > 0) {
    // Get member details
    $stmtFind = $db->prepare("SELECT full_name, category FROM team_members WHERE id = ?");
    $stmtFind->execute([$activeChatId]);
    $actMem = $stmtFind->fetch();
    
    if ($actMem) {
        $activeMemberName = $actMem['full_name'];
        $activeMemberCategory = $actMem['category'];
        
        // Mark as read
        $stmtRead = $db->prepare("UPDATE messages SET is_read = 1 WHERE receiver_type = 'admin' AND receiver_id = ? AND sender_type = 'team_member' AND sender_id = ?");
        $stmtRead->execute([$adminId, $activeChatId]);
        
        // Load Thread
        if (function_exists('getChatThread')) {
            $messages = getChatThread('admin', $adminId, 'team_member', $activeChatId, null);
        }
    }
}

$pageTitle = 'Admin Chat Hub & Approvals';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Placeholder -->
        <div class="col-md-3 col-lg-2 d-md-block dashboard-sidebar collapse bg-dark pt-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link text-white" href="<?= url('/admin/dashboard.php') ?>">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="<?= url('/admin/create-team-member.php') ?>">
                        <i class="bi bi-person-plus me-2"></i> Create Team
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active text-white fw-bold" href="<?= url('/admin/chat.php') ?>">
                        <i class="bi bi-chat-dots me-2"></i> Communications 
                    </a>
                </li>
            </ul>
        </div>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            
            <?php $flash = getFlashMessage(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                    <?= $flash['message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- SECTION 1: Pending Satisfaction/Closure Requests -->
            <?php if (!empty($closureRequests)): ?>
                <div class="card border-warning mb-4 shadow-sm">
                    <div class="card-header bg-warning text-dark fw-bold">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Pending Case Closure Notifications
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Case Number</th>
                                        <th>User Name</th>
                                        <th>Status Triggered</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($closureRequests as $req): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($req['case_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($req['user_name']) ?></td>
                                        <td class="text-success"><i class="bi bi-check-all"></i> Both parties satisfied</td>
                                        <td class="text-end">
                                            <form method="POST" action="" onsubmit="return confirm('Close Case <?= htmlspecialchars($req['case_number']) ?>? This cannot be undone.');">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="action" value="close_case">
                                                <input type="hidden" name="case_id" value="<?= $req['case_id'] ?>">
                                                <div class="mb-2">
                                                    <textarea name="closure_remarks" class="form-control form-control-sm" rows="2" maxlength="500"
                                                        placeholder="Closure remarks (optional)..."></textarea>
                                                </div>
                                                <div class="form-check form-check-inline mb-2">
                                                    <input class="form-check-input" type="checkbox" name="mark_recovered" id="rec_<?= $req['case_id'] ?>" value="1" checked>
                                                    <label class="form-check-label small" for="rec_<?= $req['case_id'] ?>">Mark user as recovered</label>
                                                </div>
                                                <div>
                                                    <button type="submit" class="btn btn-sm btn-success fw-bold">
                                                        <i class="bi bi-lock-fill me-1"></i> Close Case
                                                    </button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- SECTION 2: Master Chat Portal -->
            <div class="row">
                <!-- Chat Subject List -->
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-dark text-white">
                            <i class="bi bi-people-fill me-2"></i> Team Roster
                        </div>
                        <ul class="list-group list-group-flush h-100" style="max-height: 600px; overflow-y: auto;">
                            <?php foreach ($teamMembers as $tm): 
                                // Per-sender unread count (getUnreadMessageCount totals all senders — use direct query here)
                                $stmtUC = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_type = 'admin' AND receiver_id = ? AND sender_type = 'team_member' AND sender_id = ? AND is_read = 0");
                                $stmtUC->execute([$adminId, $tm['id']]);
                                $unread = (int)$stmtUC->fetchColumn();
                                $isActive = ($activeWith === 'team_member' && $activeChatId == $tm['id']);
                            ?>
                            <a href="<?= url("/admin/chat.php?with=team_member&id={$tm['id']}") ?>" 
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 <?= $isActive ? 'active bg-primary text-white border-primary' : '' ?>">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle d-flex justify-content-center align-items-center me-3 <?= $isActive ? 'bg-light text-primary' : 'bg-primary text-white' ?>" style="width: 40px; height: 40px;">
                                        <i class="bi bi-person-fill fs-5"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($tm['full_name']) ?></h6>
                                        <small class="<?= $isActive ? 'text-light' : 'text-muted' ?>">
                                            <?= ucfirst(str_replace('_', ' ', $tm['category'])) ?>
                                            <?= $tm['status'] !== 'active' ? ' <span class="badge bg-secondary ms-1">Inactive</span>' : '' ?>
                                        </small>
                                    </div>
                                </div>
                                <?php if ($unread > 0): ?>
                                    <span class="badge bg-danger rounded-pill"><?= $unread ?></span>
                                <?php endif; ?>
                            </a>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Chat Feed Panel -->
                <div class="col-md-8 mb-4">
                    <div class="h-100 d-flex flex-column" style="min-height: 70vh;">
                        <?php if ($activeChatId && $activeWith === 'team_member'): ?>
                            <!-- Active Chat Header -->
                            <div class="chat-header mb-3">
                                <div class="avatar-circle">
                                    <i class="bi bi-person-fill fs-5"></i>
                                </div>
                                <div class="user-info w-100 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-0 fw-bold"><?= htmlspecialchars($activeMemberName) ?></h5>
                                        <small class="text-white-50">System Administrator</small>
                                    </div>
                                    <span class="badge bg-white text-primary rounded-pill px-3 py-2"><?= ucfirst(str_replace('_', ' ', $activeMemberCategory)) ?></span>
                                </div>
                            </div>
                            
                            <!-- Chat Box -->
                            <div class="chat-box-area flex-grow-1" id="chat-box">
                                <?php if (empty($messages)): ?>
                                    <div class="text-center text-muted mt-auto mb-auto h-100 d-flex align-items-center justify-content-center">
                                        <p class="mb-0 bg-white d-inline-block px-3 py-2 rounded shadow-sm">No messages yet. Send a message below to start the conversation.</p>
                                    </div>
                                <?php else: 
                                    $prevDate = null;
                                    foreach ($messages as $msg): 
                                        $isMine = ($msg['sender_type'] === 'admin' && $msg['sender_id'] == $adminId);
                                        $msgDate = date('Y-m-d', strtotime($msg['created_at']));
                                        $today = date('Y-m-d');
                                        $yesterday = date('Y-m-d', strtotime('-1 day'));
                                        if ($msgDate === $today)          $dateLabel = 'Today';
                                        elseif ($msgDate === $yesterday)  $dateLabel = 'Yesterday';
                                        else                              $dateLabel = date('d M Y', strtotime($msg['created_at']));
                                ?>
                                    <?php if ($prevDate !== $msgDate): $prevDate = $msgDate; ?>
                                        <div class="chat-date-divider"><span><?= $dateLabel ?></span></div>
                                    <?php endif; ?>

                                    <div class="chat-bubble-container <?= $isMine ? 'mine' : 'theirs' ?>">
                                        <div class="chat-avatar <?= $isMine ? 'mine' : 'theirs' ?>"><?= $isMine ? 'A' : strtoupper(substr($activeMemberName,0,1)) ?></div>
                                        <div class="d-flex flex-column align-items-start">
                                            <div class="chat-bubble">
                                                <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                            </div>
                                            <div class="chat-meta">
                                                <?= date('h:i A', strtotime($msg['created_at'])) ?>
                                                <?php if ($isMine): ?>
                                                    <i class="bi <?= $msg['is_read'] ? 'bi-check2-all tick-read' : 'bi-check2 tick-unread' ?>"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                            
                            <!-- Reply Input -->
                            <div class="chat-input-area mt-3">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="send_message">
                                    <input type="hidden" name="receiver_id" value="<?= $activeChatId ?>">
                                    
                                    <div class="input-group">
                                        <textarea name="message" class="form-control" rows="1" placeholder="Type your message..." required maxlength="1000"></textarea>
                                        <button class="btn btn-send d-flex align-items-center justify-content-center" type="submit" style="width: 100px;">
                                            <span class="me-2 fw-bold">Send</span> <i class="bi bi-send-fill"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- Empty State -->
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-body d-flex flex-column justify-content-center align-items-center bg-light text-muted h-100" style="min-height: 400px;">
                                    <i class="bi bi-chat-square-dots display-1 mb-3 opacity-25"></i>
                                    <h4>Select a conversation</h4>
                                    <p>Click on a team member from the directory to view or start a chat thread.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        var chatBox = document.getElementById("chat-box");
        if(chatBox) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
