<?php
/**
 * Heal2Rise Book - Team Member Login
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Redirect if already logged in
if (isLoggedIn('team_member')) {
    redirect('/team/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validation
        if (empty($email)) {
            $error = 'Please enter your email.';
        } elseif (empty($password)) {
            $error = 'Please enter your password.';
        } else {
            // Authenticate team member
            $db = getDB();
            
            $stmt = $db->prepare("SELECT * FROM team_members WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $team = $stmt->fetch();
            
            if ($team && verifyPassword($password, $team['password'])) {
                // Login successful
                setLoginSession($team['id'], 'team_member', [
                    'name' => $team['full_name'],
                    'category' => $team['category']
                ]);
                
                // Update last login (optional but good practice)
                $stmtUpdate = $db->prepare("UPDATE team_members SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                $stmtUpdate->execute([$team['id']]);

                setFlashMessage('Welcome back, ' . $team['full_name'] . '!', 'success');
                redirect('/team/dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password. Please make sure your account is active.';
            }
        }
    }
}

$pageTitle = 'Team Member Login';
require_once __DIR__ . '/../includes/header.php';
?>

<main id="main-content" class="auth-wrapper">
    <div class="container">
        <div class="auth-card auth-card-sm">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-people-fill display-4 mb-3"></i>
                    <h3>Team Login</h3>
                    <p>NGO Support Staff Portal</p>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <?= $error ?>
                            </div>
                        <?php endif; ?>

                        <?php 
                        $flash = getFlashMessage();
                        if ($flash): 
                        ?>
                            <div class="alert alert-<?= $flash['type'] ?>">
                                <?= $flash['message'] ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate aria-label="Team member login form">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="mb-4">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Login
                                </button>
                            </div>
                            
                            <div class="text-center mt-3">
                                <p class="mb-1"><a href="<?= url('/index.php') ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Home</a></p>
                            </div>
                        </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
