<?php
/**
 * Heal2Rise Book - Installation/Setup Script
 * 
 * This script creates the database and default admin account.
 * Run this once during initial setup, then delete or rename this file.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'heal2rise_db';

$step = $_GET['step'] ?? 1;
$message = '';
$error = '';

// Step 2: Create database and tables
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 2) {
    try {
        // Connect without database
        $pdo = new PDO("mysql:host=$host", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Drop existing database if fresh install requested
        if (isset($_POST['fresh_install'])) {
            $pdo->exec("DROP DATABASE IF EXISTS `$dbname`");
        }
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbname`");
        
        // Read and execute schema
        $schemaFile = __DIR__ . '/database/schema.sql';
        if (file_exists($schemaFile)) {
            $schema = file_get_contents($schemaFile);
            
            // Remove comments
            $schema = preg_replace('/--.*$/m', '', $schema);
            $schema = preg_replace('/\/\*.*?\*\//s', '', $schema);
            
            // Remove CREATE DATABASE and USE statements (we already did that)
            $schema = preg_replace('/CREATE DATABASE[^;]+;/i', '', $schema);
            $schema = preg_replace('/USE [^;]+;/i', '', $schema);
            
            // Split into individual statements
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement) && strlen($statement) > 5) {
                    try {
                        $pdo->exec($statement);
                    } catch (PDOException $e) {
                        // Ignore duplicate key/index errors
                        if (strpos($e->getMessage(), 'Duplicate') === false && 
                            strpos($e->getMessage(), 'already exists') === false) {
                            throw $e;
                        }
                    }
                }
            }
            
            $message = 'Database and tables created successfully!';
            $step = 3;
        } else {
            $error = 'Schema file not found. Please ensure database/schema.sql exists.';
        }
    } catch (PDOException $e) {
        $error = 'Database Error: ' . $e->getMessage();
    }
}

// Step 3: Create admin account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $adminUsername = trim($_POST['admin_username'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';
    $adminFullName = trim($_POST['admin_fullname'] ?? '');
    
    if (empty($adminUsername) || empty($adminEmail) || empty($adminPassword) || empty($adminFullName)) {
        $error = 'All fields are required.';
    } elseif (strlen($adminPassword) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check if admin exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ? OR email = ?");
            $stmt->execute([$adminUsername, $adminEmail]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'An admin with this username or email already exists.';
            } else {
                $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO admins (username, email, password, full_name) VALUES (?, ?, ?, ?)");
                $stmt->execute([$adminUsername, $adminEmail, $hashedPassword, $adminFullName]);
                
                $message = 'Admin account created successfully!';
                $step = 4;
            }
        } catch (PDOException $e) {
            $error = 'Database Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Heal2Rise Book - Installation</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="install-wrapper">
        <div class="install-card">
            <div class="text-center mb-6">
                <i class="bi bi-heart-pulse-fill" style="font-size:3rem;color:var(--color-primary-500)"></i>
                <h2 class="mt-3">Heal2Rise Book</h2>
                <p class="text-muted mb-0">Installation Wizard</p>
            </div>
                    <!-- Progress -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="badge <?= $step >= 1 ? 'bg-primary' : 'bg-secondary' ?>">1. Welcome</span>
                            <span class="badge <?= $step >= 2 ? 'bg-primary' : 'bg-secondary' ?>">2. Database</span>
                            <span class="badge <?= $step >= 3 ? 'bg-primary' : 'bg-secondary' ?>">3. Admin</span>
                            <span class="badge <?= $step >= 4 ? 'bg-primary' : 'bg-secondary' ?>">4. Complete</span>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar" style="width: <?= ($step / 4) * 100 ?>%"></div>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?= $message ?></div>
                    <?php endif; ?>

                    <?php if ($step == 1): ?>
                        <!-- Step 1: Welcome -->
                        <h4 class="mb-3">Welcome to Heal2Rise Book Installation</h4>
                        <p>This wizard will guide you through the installation process:</p>
                        <ul>
                            <li>Create the database and tables</li>
                            <li>Set up the admin account</li>
                            <li>Configure initial settings</li>
                        </ul>
                        <div class="alert alert-info">
                            <strong>Requirements:</strong>
                            <ul class="mb-0">
                                <li>PHP 7.4 or higher</li>
                                <li>MySQL 5.7 or higher</li>
                                <li>XAMPP/WAMP with Apache running</li>
                            </ul>
                        </div>
                        <a href="?step=2" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-arrow-right me-2"></i>Start Installation
                        </a>

                    <?php elseif ($step == 2): ?>
                        <!-- Step 2: Database -->
                        <h4 class="mb-3">Database Setup</h4>
                        <p>Click the button below to create the database and required tables.</p>
                        <div class="alert alert-warning">
                            <strong>Note:</strong> Make sure MySQL is running in XAMPP.
                        </div>
                        <form method="POST">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="fresh_install" id="fresh_install" checked>
                                <label class="form-check-label" for="fresh_install">
                                    Fresh install (drop existing database if any)
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-database me-2"></i>Create Database & Tables
                            </button>
                        </form>

                    <?php elseif ($step == 3): ?>
                        <!-- Step 3: Admin Account -->
                        <h4 class="mb-3">Create Admin Account</h4>
                        <p>Set up your administrator account to manage the platform.</p>
                        <form method="POST">
                            <input type="hidden" name="create_admin" value="1">
                            
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="admin_fullname" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="admin_username" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="admin_email" required>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="admin_password" minlength="8" required>
                                <small class="text-muted">Minimum 8 characters</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-person-plus me-2"></i>Create Admin Account
                            </button>
                        </form>

                    <?php elseif ($step == 4): ?>
                        <!-- Step 4: Complete -->
                        <div class="text-center">
                            <i class="bi bi-check-circle-fill text-success display-1 mb-3"></i>
                            <h4>Installation Complete!</h4>
                            <p class="text-muted">Heal2Rise Book has been successfully installed.</p>
                            
                            <div class="alert alert-warning">
                                <strong>Important:</strong> For security, please delete or rename the <code>install.php</code> file.
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="<?= url('/index.php') ?>" class="btn btn-outline-primary btn-lg">
                                    <i class="bi bi-house me-2"></i>Go to Homepage
                                </a>
                                <a href="<?= url('/admin/login.php') ?>" class="btn btn-primary btn-lg">
                                    <i class="bi bi-shield-lock me-2"></i>Login as Admin
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
        </div>
    </div>
</body>
</html>
