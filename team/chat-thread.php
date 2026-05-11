<?php
/**
 * Heal2Rise Book - Team Member Chat Thread
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login
requireLogin('team_member');

$db = getDB();
$teamMemberId = getCurrentUserId();

// Input validation
$with = $_GET['with'] ?? '';
$otherId = intval($_GET['id'] ?? 0);
$caseId = isset($_GET['case_id']) ? intval($_GET['case_id']) : null;

if (!in_array($with, ['user', 'ngo', 'admin']) || $otherId <= 0) {
    setFlashMessage('Invalid chat parameters.', 'danger');
    redirect('/team/chat.php');
}

// Fetch other person's name
$otherName = 'Unknown';
if ($with === 'user') {
    $stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$otherId]);
    $otherName = $stmt->fetchColumn() ?: 'Unknown User';
} elseif ($with === 'ngo') {
    $stmt = $db->prepare("SELECT organization_name FROM ngos WHERE id = ?");
    $stmt->execute([$otherId]);
    $otherName = $stmt->fetchColumn() ?: 'Unknown NGO';
} elseif ($with === 'admin') {
    $stmt = $db->prepare("SELECT full_name FROM admins WHERE id = ?");
    $stmt->execute([$otherId]);
    $otherName = $stmt->fetchColumn() ?: 'Admin';
}

// Handle sending a new message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('Invalid request. Please try again.', 'danger');
    } else {
        // Do NOT sanitize here — sendChatMessage stores raw text, display uses htmlspecialchars
        $msgText = trim($_POST['message'] ?? '');
        $postCaseId = !empty($_POST['case_id']) ? intval($_POST['case_id']) : null;
        
        if (empty($msgText) || strlen($msgText) > 1000) {
            setFlashMessage('Message must be between 1 and 1000 characters.', 'warning');
        } else {
            $result = sendChatMessage('team_member', $teamMemberId, $with, $otherId, $msgText, $postCaseId);
            if (!$result['success']) {
                setFlashMessage($result['error'], 'danger');
            }
        }
        
        // Redirect to prevent form resubmission
        $redirectUrl = "/team/chat-thread.php?with={$with}&id={$otherId}";
        if ($postCaseId) $redirectUrl .= "&case_id={$postCaseId}";
        redirect($redirectUrl);
    }
}

// Mark messages as read
$stmtRead = $db->prepare("UPDATE messages SET is_read = 1 WHERE receiver_type = 'team_member' AND receiver_id = ? AND sender_type = ? AND sender_id = ?");
$stmtRead->execute([$teamMemberId, $with, $otherId]);

// Load messages using our existing helper function
$messages = getChatThread('team_member', $teamMemberId, $with, $otherId, $caseId);

$pageTitle = 'Chat with ' . htmlspecialchars($otherName);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4" style="max-width: 800px;">
    <!-- Chat Header -->
    <div class="card shadow-sm mb-3 border-0">
        <div class="card-body d-flex align-items-center bg-primary text-white rounded">
            <a href="<?= url('/team/chat.php') ?>" class="text-white text-decoration-none me-3 fs-5">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div class="d-flex align-items-center">
                <div class="bg-white text-primary rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 45px; height: 45px;">
                    <i class="bi bi-person-fill fs-4"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-bold"><?= htmlspecialchars($otherName) ?></h5>
                    <small class="text-white-50"><?= ucfirst($with) ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Chat Messages Area -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body p-3 overflow-auto" id="chat-box" style="height: 60vh; display: flex; flex-direction: column; background: #f8fafc;">
            
            <?php $flash = getFlashMessage(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?> py-2 px-3 mx-auto mb-3 text-center" style="max-width: 80%;"><?= $flash['message'] ?></div>
            <?php endif; ?>

            <?php if (empty($messages)): ?>
                <div class="text-center text-muted mt-auto mb-auto">
                    <i class="bi bi-chat-dots" style="font-size:2rem;color:#cbd5e1"></i>
                    <p class="mt-2 mb-0" style="font-size:0.85rem;">No messages yet. Say hello!</p>
                </div>
            <?php else:
                $prevDate = null;
            ?>
                <?php foreach ($messages as $msg):
                    $isMine = ($msg['sender_type'] === 'team_member' && $msg['sender_id'] == $teamMemberId);
                    $msgDate = date('Y-m-d', strtotime($msg['created_at']));
                    $today = date('Y-m-d');
                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    if ($msgDate === $today)          $dateLabel = 'Today';
                    elseif ($msgDate === $yesterday)  $dateLabel = 'Yesterday';
                    else                              $dateLabel = date('d M Y', strtotime($msg['created_at']));
                ?>
                    <?php if ($prevDate !== $msgDate): $prevDate = $msgDate; ?>
                        <div class="chat-date-divider"><?= $dateLabel ?></div>
                    <?php endif; ?>

                    <div class="d-flex align-items-end gap-2 mb-2 <?= $isMine ? 'flex-row-reverse' : '' ?>">
                        <!-- Avatar -->
                        <?php
                            $avatarType = $isMine ? 'mine' : $with;
                            $avatarClass = in_array($avatarType, ['admin','ngo']) ? $avatarType : ($isMine ? 'mine' : 'theirs');
                            $initial = strtoupper(substr($isMine ? ($_SESSION['user_data']['name'] ?? 'M') : $otherName, 0, 1));
                        ?>
                        <div class="chat-avatar <?= $avatarClass ?>" title="<?= htmlspecialchars($isMine ? 'You' : $otherName) ?>"><?= $initial ?></div>

                        <div class="d-flex flex-column <?= $isMine ? 'align-items-end' : 'align-items-start' ?>">
                            <div class="<?= $isMine ? 'chat-bubble-mine' : 'chat-bubble-theirs' ?>">
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
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>

    <!-- Message Send Form -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-3">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <?php if ($caseId): ?>
                    <input type="hidden" name="case_id" value="<?= $caseId ?>">
                <?php endif; ?>
                
                <div class="input-group">
                    <textarea name="message" class="form-control" rows="2" placeholder="Type your message..." required maxlength="1000" style="resize: none; border-radius: 20px 0 0 20px;"></textarea>
                    <button class="btn btn-primary px-4" type="submit" style="border-radius: 0 20px 20px 0;">
                        <i class="bi bi-send-fill fs-5"></i>
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