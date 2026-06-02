<?php
/**
 * Heal2Rise Book - Team Member Chat Hub
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login
requireLogin('team_member');

$db = getDB();
$teamMemberId = getCurrentUserId();

// Function to fetch grouped conversations
function getConversations($db, $teamMemberId, $otherType) {
    // We want the latest message per conversation, so we use a subquery to find the max(id)
    $query = "
        SELECT m.*, 
            IF(m.sender_type = 'team_member', m.receiver_id, m.sender_id) as other_id,
            COALESCE(
               (SELECT COUNT(*) FROM messages unread 
                WHERE unread.receiver_type = 'team_member' 
                  AND unread.receiver_id = ? 
                  AND unread.sender_type = ? 
                  AND unread.sender_id = IF(m.sender_type = 'team_member', m.receiver_id, m.sender_id) 
                  AND unread.is_read = 0), 0
            ) as unread_count
        FROM messages m
        INNER JOIN (
            SELECT MAX(id) as max_id
            FROM messages
            WHERE (sender_type = 'team_member' AND sender_id = ? AND receiver_type = ?)
               OR (receiver_type = 'team_member' AND receiver_id = ? AND sender_type = ?)
            GROUP BY IF(sender_type = 'team_member', receiver_id, sender_id)
        ) latest ON m.id = latest.max_id
        ORDER BY m.created_at DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$teamMemberId, $otherType, $teamMemberId, $otherType, $teamMemberId, $otherType]);
    $conversations = $stmt->fetchAll();
    
    // Now attach names based on the otherType
    foreach($conversations as &$conv) {
        $otherId = $conv['other_id'];
        $name = 'Unknown';
        
        if ($otherType === 'user') {
            $s = $db->prepare("SELECT full_name FROM users WHERE id = ?");
            $s->execute([$otherId]);
            $name = $s->fetchColumn() ?: 'Unknown User';
        } elseif ($otherType === 'ngo') {
            $s = $db->prepare("SELECT organization_name FROM ngos WHERE id = ?");
            $s->execute([$otherId]);
            $name = $s->fetchColumn() ?: 'Unknown NGO';
        } elseif ($otherType === 'admin') {
            $s = $db->prepare("SELECT full_name FROM admins WHERE id = ?");
            $s->execute([$otherId]);
            $name = $s->fetchColumn() ?: 'Admin';
        }
        
        $conv['name'] = $name;
    }
    
    return $conversations;
}

$userConversations = getConversations($db, $teamMemberId, 'user');
$ngoConversations = getConversations($db, $teamMemberId, 'ngo');
$adminConversations = getConversations($db, $teamMemberId, 'admin');

// If no admin conversation exists yet, fetch all real admins so team member can initiate
if (empty($adminConversations)) {
    $stmtAdmins = $db->query("SELECT id, full_name FROM admins WHERE 1 ORDER BY id ASC");
    foreach ($stmtAdmins->fetchAll() as $admin) {
        $adminConversations[] = [
            'other_id'     => $admin['id'],
            'name'         => $admin['full_name'],
            'message'      => 'Start a conversation...',
            'created_at'   => null,
            'unread_count' => 0,
        ];
    }
}

$unreadMessages = getUnreadMessageCount('team_member', $teamMemberId);
$pageTitle = 'Messages';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 mb-4">
            <div class="dashboard-sidebar">
                <nav class="nav flex-column">
                    <a class="nav-link" href="<?= url('/team/dashboard.php') ?>">
                        <i class="bi bi-speedometer2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="<?= url('/team/cases.php') ?>">
                        <i class="bi bi-folder"></i>My Cases
                    </a>
                    <a class="nav-link active" href="<?= url('/team/chat.php') ?>">
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

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-chat-dots text-primary me-2"></i> Messages</h2>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header bg-white pt-3 pb-0 border-bottom-0">
                    <ul class="nav nav-tabs" id="chatTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active text-dark fw-bold" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">
                                <i class="bi bi-people me-1"></i> Users
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-dark fw-bold" id="ngos-tab" data-bs-toggle="tab" data-bs-target="#ngos" type="button" role="tab">
                                <i class="bi bi-building me-1"></i> NGOs
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-dark fw-bold" id="admin-tab" data-bs-toggle="tab" data-bs-target="#admin" type="button" role="tab">
                                <i class="bi bi-shield-lock me-1"></i> Admin
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body p-0">
                    <div class="tab-content" id="chatTabsContent">
                        
                        <!-- Users Tab -->
                        <div class="tab-pane fade show active" id="users" role="tabpanel">
                            <div class="list-group list-group-flush">
                                <?php if (empty($userConversations)): ?>
                                    <div class="p-4 text-center text-muted">
                                        <i class="bi bi-chat-square-text" style="font-size: 2rem;"></i>
                                        <p class="mt-2 mb-0">No user conversations yet.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($userConversations as $conv): ?>
                                        <a href="<?= url("/team/chat-thread.php?with=user&id={$conv['other_id']}") ?>" class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <h6 class="mb-0 fw-bold text-dark">
                                                    <?= htmlspecialchars($conv['name']) ?>
                                                    <?php if ($conv['unread_count'] > 0): ?>
                                                        <span class="badge bg-danger rounded-pill ms-1"><?= $conv['unread_count'] ?></span>
                                                    <?php endif; ?>
                                                </h6>
                                                <?php if ($conv['created_at']): ?>
                                                <small class="text-muted"><?= timeAgo($conv['created_at']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-0 text-muted text-truncate" style="max-width: 80%;">
                                                <?= htmlspecialchars(substr($conv['message'], 0, 50)) ?><?= strlen($conv['message']) > 50 ? '...' : '' ?>
                                            </p>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- NGOs Tab -->
                        <div class="tab-pane fade" id="ngos" role="tabpanel">
                            <div class="list-group list-group-flush">
                                <?php if (empty($ngoConversations)): ?>
                                    <div class="p-4 text-center text-muted">
                                        <i class="bi bi-chat-square-text" style="font-size: 2rem;"></i>
                                        <p class="mt-2 mb-0">No NGO conversations yet.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($ngoConversations as $conv): ?>
                                        <a href="<?= url("/team/chat-thread.php?with=ngo&id={$conv['other_id']}") ?>" class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <h6 class="mb-0 fw-bold text-dark">
                                                    <?= htmlspecialchars($conv['name']) ?>
                                                    <?php if ($conv['unread_count'] > 0): ?>
                                                        <span class="badge bg-danger rounded-pill ms-1"><?= $conv['unread_count'] ?></span>
                                                    <?php endif; ?>
                                                </h6>
                                                <?php if ($conv['created_at']): ?>
                                                <small class="text-muted"><?= timeAgo($conv['created_at']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-0 text-muted text-truncate" style="max-width: 80%;">
                                                <?= htmlspecialchars(substr($conv['message'], 0, 50)) ?><?= strlen($conv['message']) > 50 ? '...' : '' ?>
                                            </p>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Admin Tab -->
                        <div class="tab-pane fade" id="admin" role="tabpanel">
                            <div class="list-group list-group-flush">
                                <?php foreach ($adminConversations as $conv): ?>
                                    <a href="<?= url("/team/chat-thread.php?with=admin&id={$conv['other_id']}") ?>" class="list-group-item list-group-item-action p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <h6 class="mb-0 fw-bold text-dark">
                                                <?= htmlspecialchars($conv['name']) ?>
                                                <?php if ($conv['unread_count'] > 0): ?>
                                                    <span class="badge bg-danger rounded-pill ms-1"><?= $conv['unread_count'] ?></span>
                                                <?php endif; ?>
                                            </h6>
                                            <?php if ($conv['created_at']): ?>
                                            <small class="text-muted"><?= timeAgo($conv['created_at']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <p class="mb-0 text-muted text-truncate" style="max-width: 80%;">
                                            <?= htmlspecialchars(substr($conv['message'], 0, 50)) ?><?= strlen($conv['message']) > 50 ? '...' : '' ?>
                                        </p>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>