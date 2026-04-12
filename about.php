<?php
/**
 * Heal2Rise Book - About Us Page
 */

$pageTitle = 'About Us';
require_once __DIR__ . '/includes/header.php';
?>

<main id="main-content">
<!-- Hero Section -->
<section class="hero-section" aria-labelledby="about-title">
    <div class="container">
        <div class="text-center hero-content">
            <h1 id="about-title">About Heal2Rise Book</h1>
            <p class="lead mx-auto">A compassionate platform empowering individuals through safe mental health support and genuine human connection</p>
        </div>
    </div>
</section>

<!-- Mission Section -->
<section class="section-padding">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold mb-4">Our Mission</h2>
            <p class="text-muted mx-auto content-max">
                Heal2Rise Book is dedicated to providing a safe, confidential platform that connects 
                individuals facing mental health challenges with verified NGOs and professional support services.
            </p>
            <p class="text-muted mx-auto content-max">
                We believe that everyone deserves access to mental health support, regardless of their 
                circumstances. Our platform bridges the gap between those seeking help and organizations 
                equipped to provide it.
            </p>
            <p class="text-muted mb-0 mx-auto content-max">
                Through our innovative matching system, we ensure that each individual is connected with 
                the most suitable NGO based on their specific needs, location, and the type of support required.
            </p>
        </div>
        <div class="text-center">
            <img src="https://img.icons8.com/bubbles/300/handshake.png" alt="Partnership" class="img-fluid">
        </div>
    </div>
</section>

<!-- Values Section -->
<section class="section-padding bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Our Core Values</h2>
            <p class="text-muted">The principles that guide everything we do</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="card feature-card h-100">
                    <div class="icon-box">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h4>Privacy First</h4>
                    <p class="text-muted">Your information is protected with the highest security standards. We never share data without consent.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card feature-card h-100">
                    <div class="icon-box">
                        <i class="bi bi-heart"></i>
                    </div>
                    <h4>Compassion</h4>
                    <p class="text-muted">We approach every interaction with empathy, understanding, and genuine care for well-being.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card feature-card h-100">
                    <div class="icon-box">
                        <i class="bi bi-award"></i>
                    </div>
                    <h4>Excellence</h4>
                    <p class="text-muted">We partner only with verified NGOs that meet our strict quality and ethical standards.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card feature-card h-100">
                    <div class="icon-box">
                        <i class="bi bi-people"></i>
                    </div>
                    <h4>Community</h4>
                    <p class="text-muted">Building a supportive network where individuals and organizations work together for healing.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Team Section -->
<section class="section-padding">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">How We Help</h2>
            <p class="text-muted">Our comprehensive approach to mental health support</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center p-4">
                        <div class="icon-box mx-auto mb-4 icon-box-success">
                            <i class="bi bi-person-check icon-success"></i>
                        </div>
                        <h5>For Individuals</h5>
                        <p class="text-muted mb-0">Register confidentially, get matched with suitable NGOs, and receive professional support throughout your healing journey.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center p-4">
                        <div class="icon-box mx-auto mb-4 icon-box-info">
                            <i class="bi bi-building icon-info"></i>
                        </div>
                        <h5>For NGOs</h5>
                        <p class="text-muted mb-0">Register your organization, manage cases efficiently, and connect with individuals who need your specialized services.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center p-4">
                        <div class="icon-box mx-auto mb-4 icon-box-warning">
                            <i class="bi bi-graph-up icon-warning"></i>
                        </div>
                        <h5>Progress Tracking</h5>
                        <p class="text-muted mb-0">Monitor healing progress, track milestones, and celebrate achievements along the recovery journey.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="section-padding bg-gradient-primary text-white">
    <div class="container text-center">
        <h2 class="fw-bold mb-3">Ready to Start Your Journey?</h2>
        <p class="mb-4 opacity-75">Join thousands of individuals who have found hope and healing through Heal2Rise Book.</p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="<?= url('/user/register.php') ?>" class="btn btn-light btn-lg">
                <i class="bi bi-heart me-2"></i>Get Support
            </a>
            <a href="<?= url('/ngo/register.php') ?>" class="btn btn-outline-light btn-lg">
                <i class="bi bi-building me-2"></i>Partner With Us
            </a>
        </div>
    </div>
</section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
