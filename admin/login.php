<?php
/**
 * Heal2Rise Book - Admin Login
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Redirect if already logged in
if (isLoggedIn('admin')) {
    redirect('/admin/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $errors[] = 'Please enter username and password.';
        }

        if (empty($errors)) {
            $db = getDB();
            
            $stmt = $db->prepare("SELECT * FROM admins WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $admin = $stmt->fetch();
            
            if ($admin && verifyPassword($password, $admin['password'])) {
                setLoginSession($admin['id'], 'admin', [
                    'name' => $admin['full_name'],
                    'email' => $admin['email'],
                    'username' => $admin['username']
                ]);
                
                setFlashMessage('Welcome back, ' . $admin['full_name'] . '!', 'success');
                redirect('/admin/dashboard.php');
            } else {
                $errors[] = 'Invalid username or password.';
            }
        }
    }
}

$pageTitle = 'Admin Login';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="auth-wrapper">
    <div class="container">
        <div class="auth-card auth-card-xs">
            <div class="card">
                <div class="card-header admin-card-header">
                    <i class="bi bi-shield-lock-fill display-4 mb-3"></i>
                    <h3>Admin Login</h3>
                    <p>System Administration Access</p>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $error ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate aria-label="Admin login form">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username or Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                                       required autofocus>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary password-toggle" type="button" data-target="password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-dark btn-lg w-100 mb-3">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Secure Login
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="<?= url('/index.php') ?>" class="text-muted"><i class="bi bi-arrow-left me-2"></i>Back to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
