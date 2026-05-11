<?php

/**
 * Heal2Rise Book - Home Page
 */

$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<main id="main-content">
<!-- Hero Section -->
<section class="hero-section" aria-labelledby="home-hero-title" >
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 hero-content">
                <div class="privacy-badge mb-4">
                    <i class="bi bi-shield-check"></i>
                    100% Confidential & Secure
                </div>
                <h1 id="home-hero-title" class="display-4 fw-bold mb-4">Your Safe Space to <span class="text-light">Heal</span> & Rise</h1>
                <p class="lead mb-4">
                    Heal2Rise Book is a gentle, confidential platform designed for individuals 
                    facing emotional challenges. We connect you with compassionate NGOs and 
                    professionals who walk beside you on your journey to renewal.
                </p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="<?= url('/user/register.php') ?>" class="btn btn-warning btn-lg d-inline-flex align-items-center">
                        <img src="<?= url('/assets/imgs/flaticon-medical-heart.png') ?>" alt="Support" width="24" height="24" class="me-2"> 
                        Get Support Now
                    </a>
                    <a href="<?= url('/ngo/register.php') ?>" class="btn btn-outline-light btn-lg d-inline-flex align-items-center">
                        <img src="<?= url('/assets/imgs/flaticon-medical-hospital.png') ?>" alt="NGO" width="24" height="24" class="me-2">
                        Register as NGO
                    </a>
                </div>
            </div>
            <div class="col-lg-6 text-center mt-5 mt-lg-0">
                <img src="<?= url('/assets/imgs/hero-image.png') ?>" alt="Mental health support illustration" class="img-fluid hero-image">
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="section-padding">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">How Heal2Rise Works</h2>
            <p class="text-muted">A gentle step-by-step journey to healing and empowerment</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="card feature-card h-100 fade-in">
                    <div class="icon-box">
                        <img src="<?= url('/assets/imgs/flaticon-medical-patient.png') ?>" alt="Register" width="40" height="40">
                    </div>
                    <h4>1. Register Securely</h4>
                    <p class="text-muted">Create your confidential account. Your privacy is our top priority.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card feature-card h-100 fade-in" style="animation-delay: 0.1s">
                    <div class="icon-box">
                        <img src="<?= url('/assets/imgs/flaticon-medical-doctor.png') ?>" alt="Match" width="40" height="40">
                    </div>
                    <h4>2. Get Matched</h4>
                    <p class="text-muted">Our system connects you with the most suitable NGO based on your needs.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card feature-card h-100 fade-in" style="animation-delay: 0.2s">
                    <div class="icon-box">
                        <img src="<?= url('/assets/imgs/flaticon-medical-chat.png') ?>" alt="Support" width="40" height="40">
                    </div>
                    <h4>3. Receive Support</h4>
                    <p class="text-muted">Professional counselors and psychiatrists provide regular guidance and therapy.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card feature-card h-100 fade-in" style="animation-delay: 0.3s">
                    <div class="icon-box">
                        <img src="<?= url('/assets/imgs/flaticon-medical-recovery.png') ?>" alt="Recover" width="40" height="40">
                    </div>
                    <h4>4. Rise Again</h4>
                    <p class="text-muted">Develop skills, gain confidence, and step into an empowered new life.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="section-padding bg-white" aria-label="Key benefits">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 col-lg-6 mb-4 mb-lg-0">
                <h2 class="fw-bold mb-4">Why Choose Heal2Rise Book?</h2>
                <div class="d-flex mb-4">
                    <div class="me-3">
                        <div class="icon-box icon-box-sm">
                            <i class="bi bi-shield-lock icon-sm"></i>
                        </div>
                    </div>
                    <div>
                        <h5>Complete Privacy</h5>
                        <p class="text-muted">Your identity and information are fully protected. No data sharing without consent.</p>
                    </div>
                </div>
                <div class="d-flex mb-4">
                    <div class="me-3">
                        <div class="icon-box icon-box-sm">
                            <i class="bi bi-people icon-sm"></i>
                        </div>
                    </div>
                    <div>
                        <h5>Verified NGOs</h5>
                        <p class="text-muted">All partner NGOs are thoroughly verified by our admin team before approval.</p>
                    </div>
                </div>
                <div class="d-flex mb-4">
                    <div class="me-3">
                        <div class="icon-box icon-box-sm">
                            <i class="bi bi-graph-up-arrow icon-sm"></i>
                        </div>
                    </div>
                    <div>
                        <h5>Progress Tracking</h5>
                        <p class="text-muted">Monitor your healing journey with regular assessments and milestones.</p>
                    </div>
                </div>
                <div class="d-flex">
                    <div class="me-3">
                        <div class="icon-box icon-box-sm">
                            <i class="bi bi-headset icon-sm"></i>
                        </div>
                    </div>
                    <div>
                        <h5>24/7 Support</h5>
                        <p class="text-muted">Access help whenever you need it through our dedicated support channels.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-6 text-center">
                <img src="https://img.icons8.com/bubbles/400/conference-call.png" alt="Support Team" class="img-fluid">
            </div>
        </div>
    </div>
</section>

<!-- Statistics Section -->
<section class="section-padding bg-gradient-primary text-white">
    <div class="container">
        <div class="row text-center g-4">
            <div class="col-6 col-md-3">
                <h2 class="display-4 fw-bold">200+</h2>
                <p class="mb-0">Individuals Helped</p>
            </div>
            <div class="col-6 col-md-3">
                <h2 class="display-4 fw-bold">30+</h2>
                <p class="mb-0">Partner NGOs</p>
            </div>
            <div class="col-6 col-md-3">
                <h2 class="display-4 fw-bold">50+</h2>
                <p class="mb-0">Counselors</p>
            </div>
            <div class="col-6 col-md-3">
                <h2 class="display-4 fw-bold">95%</h2>
                <p class="mb-0">Success Rate</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="section-padding">
    <div class="container">
        <div class="card border-0 p-5 text-center" style="background: linear-gradient(135deg, var(--color-primary-50) 0%, rgba(8, 240, 105, 0.11) 100%);">
            <h2 class="fw-bold mb-3">Ready to Begin Your Healing Journey?</h2>
            <p class="text-muted mb-4">Take the first step today. Our caring team is here to support you every step of the way.</p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="<?= url('/user/register.php') ?>" class="btn btn-primary btn-lg d-inline-flex align-items-center">
                    <img src="<?= url('/assets/imgs/flaticon-medical-pulse.png') ?>" alt="Start" width="24" height="24" class="me-2">
                    Start Now
                </a>
                <a href="<?= url('/contact.php') ?>" class="btn btn-outline-secondary btn-lg d-inline-flex align-items-center">
                    <img src="<?= url('/assets/imgs/flaticon-medical-call.png') ?>" alt="Contact" width="24" height="24" class="me-2">
                    Contact Us
                </a>
            </div>
        </div>
    </div>
</section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
