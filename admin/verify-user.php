<?php
/**
 * Admin - Verify User
 * Approve or reject user verification
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireLogin('admin');

// Handle POST-based verification (secure)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method.';
    redirect('/admin/users.php');
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = 'Invalid security token.';
    redirect('/admin/users.php');
}

$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$action = $_POST['action'] ?? '';

if (!$userId || !in_array($action, ['approve', 'reject'])) {
    $_SESSION['error'] = 'Invalid request.';
    redirect('/admin/users.php');
}

try {
    $pdo = getDB();
    
    // Get user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error'] = 'User not found.';
        redirect('/admin/users.php');
    }
    
    // Update verification status
    $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
    $isVerified = ($action === 'approve') ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE users SET verification_status = ?, is_verified = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newStatus, $isVerified, $userId]);
    
    // Create notification for the user
    $notificationTitle = ($action === 'approve') 
        ? 'Account Verified' 
        : 'Account Verification Rejected';
    $notificationMessage = ($action === 'approve')
        ? 'Your account has been verified. You can now request support from NGOs.'
        : 'Your account verification was rejected. Please contact support for more information.';
    
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, user_type, title, message, created_at) VALUES (?, 'user', ?, ?, NOW())");
    $stmt->execute([$userId, $notificationTitle, $notificationMessage]);
    
    $_SESSION['success'] = ($action === 'approve') 
        ? 'User has been verified successfully.' 
        : 'User verification has been rejected.';
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
}

redirect('/admin/dashboard.php');
