<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';

$flashMessage = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Heal2Rise Book' ?> - Your Journey to Healing</title>
    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Inter Font - Modern, Professional -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom Design System -->
    <link href="<?= url('/assets/css/style.css') ?>" rel="stylesheet">
    <?php if (isset($extraCSS)): ?>
        <?php foreach ($extraCSS as $css): ?>
            <link href="<?= $css ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="saas-modern">
    <a href="#main-content" class="visually-hidden-focusable">Skip to main content</a>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg sticky-top" aria-label="Primary navigation">
        <div class="container">
            <a class="navbar-brand" href="<?= url('/index.php') ?>">
                <i class="bi bi-heart-pulse-fill text-primary me-2"></i>
                <span class="fw-bold">Heal2Rise</span> <span class="text-muted">Book</span>
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-label="Toggle navigation" aria-controls="navbarNav" aria-expanded="false">
                <i class="bi bi-list"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto" role="menubar" aria-label="Main links">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('/index.php') ?>"><i class="bi bi-house me-1"></i>Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('/about.php') ?>"><i class="bi bi-info-circle me-1"></i>About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('/contact.php') ?>"><i class="bi bi-envelope me-1"></i>Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('/privacy.php') ?>"><i class="bi bi-shield-check me-1"></i>Privacy</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('/donation.php') ?>"><i class="bi bi-heart me-1"></i>Donate</a>
                    </li>
                </ul>
                <ul class="navbar-nav" role="menubar" aria-label="Account links">
                    <?php if (isLoggedIn()): ?>
                        <?php $userType = getUserType(); ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="bi bi-person-circle me-1"></i>
                                <?= $_SESSION['user_data']['name'] ?? 'Account' ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= url('/' . $userType . '/dashboard.php') ?>">
                                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                                </a></li>
                                <li><a class="dropdown-item" href="<?= url('/' . $userType . '/profile.php') ?>">
                                    <i class="bi bi-person me-2"></i>Profile
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?= url('/logout.php') ?>">
                                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                Login
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= url('/user/login.php') ?>">
                                    <i class="bi bi-person me-2"></i>User Login
                                </a></li>
                                <li><a class="dropdown-item" href="<?= url('/ngo/login.php') ?>">
                                    <i class="bi bi-building me-2"></i>NGO Login
                                </a></li>
                                <li><a class="dropdown-item" href="<?= url('/admin/login.php') ?>">
                                    <i class="bi bi-shield-lock me-2"></i>Admin Login
                                </a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle btn btn-primary text-white px-3 ms-2" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                Register
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= url('/user/register.php') ?>">
                                    <i class="bi bi-person-plus me-2"></i>User Registration
                                </a></li>
                                <li><a class="dropdown-item" href="<?= url('/ngo/register.php') ?>">
                                    <i class="bi bi-building-add me-2"></i>NGO Registration
                                </a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php if ($flashMessage): ?>
    <div class="container mt-3">
        <div class="alert alert-<?= $flashMessage['type'] ?> alert-dismissible fade show" role="alert" aria-live="polite">
            <i class="bi bi-<?= $flashMessage['type'] === 'success' ? 'check-circle' : ($flashMessage['type'] === 'danger' ? 'exclamation-triangle' : 'info-circle') ?> me-2"></i>
            <?= $flashMessage['message'] ?>
            <button type="button" class="btn-close" data-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>
