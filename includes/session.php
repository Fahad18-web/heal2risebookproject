<?php
/**
 * Heal2Rise Book - Session Management
 * Handles session initialization and security
 */

if (session_status() === PHP_SESSION_NONE) {
    // Secure session configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
    
    session_start();
}

// Regenerate session ID periodically to prevent fixation
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

/**
 * Check if user is logged in
 */
function isLoggedIn($type = null) {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    if ($type !== null && (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== $type)) {
        return false;
    }
    return true;
}

/**
 * Require login - redirect if not logged in
 */
function requireLogin($type = null) {
    if (!isLoggedIn($type)) {
        $base = defined('BASE_URL') ? BASE_URL : '';
        $redirect = $type ? "{$base}/{$type}/login.php" : "{$base}/index.php";
        header("Location: $redirect");
        exit;
    }
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user type
 */
function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

/**
 * Set login session
 */
function setLoginSession($userId, $userType, $userData = []) {
    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_type'] = $userType;
    $_SESSION['user_data'] = $userData;
    $_SESSION['login_time'] = time();
}

/**
 * Logout user
 */
function logout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Set flash message
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Get and clear flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
