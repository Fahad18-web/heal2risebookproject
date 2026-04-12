<?php
/**
 * Heal2Rise Book - User Login
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Redirect if already logged in
if (isLoggedIn('user')) {
    redirect('/user/dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $usernameOrEmail = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);

        // Validation
        if (empty($usernameOrEmail)) {
            $errors[] = 'Please enter your username or email.';
        }
        
        if (empty($password)) {
            $errors[] = 'Please enter your password.';
        }

        // Authenticate user
        if (empty($errors)) {
            $db = getDB();
            
            $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status != 'inactive'");
            $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($password, $user['password'])) {
                // Check verification status
                if ($user['verification_status'] === 'pending') {
                    $errors[] = 'Your account is pending verification. Please wait for admin approval.';
                } elseif ($user['verification_status'] === 'rejected') {
                    $errors[] = 'Your account registration was rejected. Please contact support.';
                } else {
                    // Login successful
                    setLoginSession($user['id'], 'user', [
                        'name' => $user['full_name'],
                        'email' => $user['email'],
                        'username' => $user['username']
                    ]);
                    
                    // Handle remember me
                    if ($rememberMe) {
                        $token = bin2hex(random_bytes(32));
                        // In production, store this token in database and set cookie
                    }
                    
                    setFlashMessage('Welcome back, ' . $user['full_name'] . '!', 'success');
                    redirect('/user/dashboard.php');
                    exit;
                }
            } else {
                $errors[] = 'Invalid username/email or password.';
            }
        }
    }
}

$pageTitle = 'User Login';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="auth-wrapper">
    <div class="container">
        <div class="auth-card auth-card-sm">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-shield-lock display-4 mb-3"></i>
                    <h3>Welcome Back</h3>
                    <p>Your safe space awaits you</p>
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

                    <form method="POST" class="needs-validation" novalidate aria-label="User login form">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username or Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                                       required autofocus>
                            </div>
                            <div class="invalid-feedback">Please enter your username or email.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary password-toggle" type="button" data-target="password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Please enter your password.</div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                                <label class="form-check-label" for="remember_me">Remember me</label>
                            </div>
                            <a href="<?= url('/user/forgot-password.php') ?>" class="text-decoration-none">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 mb-4">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In Securely
                        </button>
                        
                        <div class="text-center">
                            <span class="privacy-badge" style="background: var(--color-primary-50); color: var(--color-primary-700); border-color: var(--color-primary-100);">
                                <i class="bi bi-shield-check" style="color: var(--color-primary-500);"></i>
                                Your data is encrypted & protected
                            </span>
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="mb-0">Don't have an account? <a href="<?= url('/user/register.php') ?>">Register now</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
