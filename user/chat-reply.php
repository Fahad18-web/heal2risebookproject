<?php
/**
 * Heal2Rise Book - User Chat Reply
 * Interface for users to reply to their assigned team member for a case.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login
requireLogin('user');

$userId = getCurrentUserId();
$caseId = intval($_GET['case_id'] ?? 0);

if (!$caseId) {
    setFlashMessage('Invalid case.', 'danger');
    redirect('/user/cases.php');
    exit;
}

$db = getDB();

// Verify this case belongs to the current user and get team member
$stmt = $db->prepare("
    SELECT c.team_member_id, tm.full_name as team_member_name 
    FROM cases c 
    LEFT JOIN team_members tm ON c.team_member_id = tm.id 
    WHERE c.id = ? AND c.user_id = ?
");
$stmt->execute([$caseId, $userId]);
$caseInfo = $stmt->fetch();

if (!$caseInfo) {
    setFlashMessage('Case not found or access denied.', 'danger');
    redirect('/user/cases.php');
    exit;
}

$teamMemberId = $caseInfo['team_member_id'];
$teamMemberName = $caseInfo['team_member_name'] ?: 'Team Member';

if (!$teamMemberId) {
    setFlashMessage('No team member assigned yet.', 'warning');
    redirect("/user/case-details.php?id={$caseId}");
    exit;
}

// Handle sending a new message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('Invalid request. Please try again.', 'danger');
    } else {
        // Do NOT sanitize here — sendChatMessage stores raw text, display uses htmlspecialchars
        $msgText = trim($_POST['message'] ?? '');
        
        if (empty($msgText) || strlen($msgText) > 1000) {
            setFlashMessage('Message must be between 1 and 1000 characters.', 'warning');
        } else {
            $result = sendChatMessage('user', $userId, 'team_member', $teamMemberId, $msgText, $caseId);
            if (!$result['success']) {
                setFlashMessage($result['error'], 'danger');
            }
        }
        
        redirect("/user/chat-reply.php?case_id={$caseId}");
        exit;
    }
}

// Mark messages as read when opening this page
$stmtRead = $db->prepare("UPDATE messages SET is_read = 1 WHERE receiver_type = 'user' AND receiver_id = ? AND sender_type = 'team_member' AND sender_id = ?");
$stmtRead->execute([$userId, $teamMemberId]);

// Load the full conversation thread
$messages = getChatThread('user', $userId, 'team_member', $teamMemberId, $caseId);

$pageTitle = 'Message with your Team Member';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4" style="max-width: 800px;">
    <!-- Chat Header -->
    <div class="card shadow-sm mb-3 border-0">
        <div class="card-body d-flex align-items-center bg-primary text-white rounded">
            <a href="<?= url('/user/case-details.php?id=' . $caseId) ?>" class="text-white text-decoration-none me-3 fs-5">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div class="d-flex align-items-center">
                <div class="bg-white text-primary rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 45px; height: 45px;">
                    <i class="bi bi-headset fs-4"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-bold"><?= htmlspecialchars($teamMemberName) ?></h5>
                    <small class="text-white-50">Assigned Team Member</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Chat Messages Area -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body p-4 overflow-auto bg-light" id="chat-box" style="height: 60vh; display: flex; flex-direction: column;">
            
            <?php $flash = getFlashMessage(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?> py-2 px-3 mx-auto mb-3 text-center" style="max-width: 80%;"><?= $flash['message'] ?></div>
            <?php endif; ?>

            <?php if (empty($messages)): ?>
                <div class="text-center text-muted mt-auto mb-auto">
                    <p class="mb-0 bg-white d-inline-block px-3 py-2 rounded shadow-sm">No messages yet. Send a reply below if you have questions!</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): 
                    $isMine = ($msg['sender_type'] === 'user' && $msg['sender_id'] == $userId);
                ?>
                    <div class="d-flex flex-column mb-3 <?= $isMine ? 'align-items-end' : 'align-items-start' ?>">
                                            <div class="chat-bubble <?= $isMine ? 'chat-bubble-mine' : 'chat-bubble-other' ?>">
                                                <p class="mb-0"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                                <span class="msg-time"><?= date('h:i A', strtotime($msg['created_at'])) ?></span>
                                            </div>
                                        </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>

    <!-- Message Reply Form -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-3">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                
                <div class="input-group">
                    <textarea name="message" class="form-control" rows="2" placeholder="Type your reply here..." required maxlength="1000" style="resize: none; border-radius: 20px 0 0 20px;"></textarea>
                    <button class="btn btn-primary px-4 fw-bold" type="submit" style="border-radius: 0 20px 20px 0;">
                        Send Reply <i class="bi bi-send-fill ms-2"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Auto-scroll to bottom of chat -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var chatBox = document.getElementById("chat-box");
        if (chatBox && chatBox.querySelector('p.mb-0.text-dark')) {
            // Only scroll when actual messages exist — empty state uses mt-auto/mb-auto centering
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
