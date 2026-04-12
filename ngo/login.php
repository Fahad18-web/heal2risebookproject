<?php
/**
 * Heal2Rise Book - NGO Login
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Redirect if already logged in
if (isLoggedIn('ngo')) {
    redirect('/ngo/dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $usernameOrEmail = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($usernameOrEmail)) {
            $errors[] = 'Please enter your username or email.';
        }
        
        if (empty($password)) {
            $errors[] = 'Please enter your password.';
        }

        if (empty($errors)) {
            $db = getDB();
            
            $stmt = $db->prepare("SELECT * FROM ngos WHERE (username = ? OR email = ?) AND status = 'active'");
            $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
            $ngo = $stmt->fetch();
            
            if ($ngo && verifyPassword($password, $ngo['password'])) {
                if ($ngo['verification_status'] === 'pending') {
                    $errors[] = 'Your organization is pending verification. Please wait for admin approval.';
                } elseif ($ngo['verification_status'] === 'rejected') {
                    $errors[] = 'Your organization registration was rejected. Please contact support.';
                } else {
                    // Login successful
                    setLoginSession($ngo['id'], 'ngo', [
                        'name' => $ngo['organization_name'],
                        'email' => $ngo['email'],
                        'username' => $ngo['username']
                    ]);
                    
                    setFlashMessage('Welcome back, ' . $ngo['organization_name'] . '!', 'success');
                    redirect('/ngo/dashboard.php');
                    exit;
                }
            } else {
                $errors[] = 'Invalid username/email or password.';
            }
        }
    }
}

$pageTitle = 'NGO Login';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="auth-wrapper">
    <div class="container">
        <div class="auth-card auth-card-sm">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-shield-lock display-4 mb-3"></i>
                    <h3>NGO Portal</h3>
                    <p>Securely access your organization dashboard</p>
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

                    <form method="POST" class="needs-validation" novalidate aria-label="NGO login form">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username or Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-building"></i></span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                                       required autofocus>
                            </div>
                            <div class="invalid-feedback">Please enter your username or email.</div>
                        </div>
                        
                        <div class="mb-4">
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

                        <button type="submit" class="btn btn-primary btn-lg w-100 mb-4">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In Securely
                        </button>
                    </form>
                    
                    <div class="text-center mb-4">
                        <span class="privacy-badge" style="background: var(--color-primary-50); color: var(--color-primary-700); border-color: var(--color-primary-100);">
                            <i class="bi bi-shield-check" style="color: var(--color-primary-500);"></i>
                            Encrypted & Protected
                        </span>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="mb-0">Not registered? <a href="<?= url('/ngo/register.php') ?>">Register your NGO</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
