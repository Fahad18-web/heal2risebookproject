<?php
/**
 * Heal2Rise Book - Privacy Policy Page
 */

$pageTitle = 'Privacy Policy';
require_once __DIR__ . '/includes/header.php';
?>

<main id="main-content">
<!-- Hero Section -->
<section class="hero-section" aria-labelledby="privacy-title">
    <div class="container">
        <div class="text-center hero-content">
            <h1 id="privacy-title">Privacy Policy</h1>
            <p class="lead">Your privacy is our priority. Learn how we protect your information.</p>
        </div>
    </div>
</section>

<!-- Privacy Content -->
<section class="section-padding">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-11 col-lg-10">
                <div class="card">
                    <div class="card-body p-4 p-md-5">
                        <p class="text-muted mb-4">
                            <strong>Last Updated:</strong> <?= date('F Y') ?>
                        </p>
                        
                        <div class="alert alert-info mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            At Heal2Rise Book, we understand the sensitive nature of mental health information. 
                            This Privacy Policy explains how we collect, use, protect, and share your personal information.
                        </div>
                        
                        <!-- Section 1 -->
                        <div class="mb-5">
                            <h3 class="fw-bold mb-3">1. Information We Collect</h3>
                            <p class="text-muted">We collect information that you provide directly to us, including:</p>
                            <ul class="text-muted privacy-list">
                                <li><strong>Account Information:</strong> Name, email address, phone number, and password when you register.</li>
                                <li><strong>Profile Information:</strong> Age, location, and other demographic details you choose to provide.</li>
                                <li><strong>Case Information:</strong> Details about your situation, support needs, and preferences for NGO matching.</li>
                                <li><strong>Communication Data:</strong> Messages exchanged with NGOs and our support team.</li>
                                <li><strong>Usage Data:</strong> Information about how you interact with our platform.</li>
                            </ul>
                        </div>
                        
                        <!-- Section 2 -->
                        <div class="mb-5">
                            <h3 class="fw-bold mb-3">2. How We Use Your Information</h3>
                            <p class="text-muted">We use the information we collect to:</p>
                            <ul class="text-muted privacy-list">
                                <li>Create and manage your account</li>
                                <li>Match you with appropriate NGOs based on your needs and location</li>
                                <li>Facilitate communication between you and NGOs</li>
                                <li>Track the progress of your support journey</li>
                                <li>Send you notifications about case updates and platform announcements</li>
                                <li>Improve our services and develop new features</li>
                                <li>Ensure the safety and security of our platform</li>
                            </ul>
                        </div>
                        
                        <!-- Section 3 -->
                        <div class="mb-5">
                            <h3 class="fw-bold mb-3">3. Information Sharing</h3>
                            <p class="text-muted">We are committed to protecting your privacy. We share your information only in the following circumstances:</p>
                            
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="fw-bold"><i class="bi bi-building me-2"></i>With NGOs</h6>
                                    <p class="text-muted mb-0">When you submit a case, relevant information is shared with verified NGOs to facilitate support. 
                                    You control what information is visible to NGOs.</p>
                                </div>
                            </div>
                            
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="fw-bold"><i class="bi bi-shield-check me-2"></i>For Safety</h6>
                                    <p class="text-muted mb-0">We may share information if we believe it's necessary to prevent harm to you or others, 
                                    or as required by law.</p>
                                </div>
                            </div>
                            
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="fw-bold"><i class="bi bi-graph-up me-2"></i>Aggregated Data</h6>
                                    <p class="text-muted mb-0">We may share anonymized, aggregated statistics for research purposes. 
                                    This data cannot be used to identify you.</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section 4 -->
                        <div class="mb-5">
                            <h3 class="fw-bold mb-3">4. Data Security</h3>
                            <p class="text-muted">We implement robust security measures to protect your information:</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <i class="bi bi-lock-fill text-success me-3 mt-1"></i>
                                        <div>
                                            <strong>Encryption</strong>
                                            <p class="text-muted small mb-0">All data is encrypted in transit and at rest using industry-standard protocols.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <i class="bi bi-key-fill text-success me-3 mt-1"></i>
                                        <div>
                                            <strong>Password Protection</strong>
                                            <p class="text-muted small mb-0">Passwords are hashed using bcrypt algorithm and never stored in plain text.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <i class="bi bi-person-check-fill text-success me-3 mt-1"></i>
                                        <div>
                                            <strong>Access Controls</strong>
                                            <p class="text-muted small mb-0">Strict access controls ensure only authorized personnel can access sensitive data.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <i class="bi bi-activity text-success me-3 mt-1"></i>
                                        <div>
                                            <strong>Monitoring</strong>
                                            <p class="text-muted small mb-0">Continuous security monitoring to detect and prevent unauthorized access.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section 5 -->
                        <div class="mb-5">
                            <h3 class="fw-bold mb-3">5. Your Rights</h3>
                            <p class="text-muted">You have the following rights regarding your personal information:</p>
                            <ul class="text-muted privacy-list">
                                <li><strong>Access:</strong> Request a copy of the information we hold about you.</li>
                                <li><strong>Correction:</strong> Update or correct inaccurate information.</li>
                                <li><strong>Deletion:</strong> Request deletion of your account and personal data.</li>
                                <li><strong>Portability:</strong> Receive your data in a machine-readable format.</li>
                                <li><strong>Objection:</strong> Object to certain processing of your information.</li>
                                <li><strong>Withdraw Consent:</strong> Withdraw consent for data processing at any time.</li>
                            </ul>
                            <p class="text-muted">
                                To exercise these rights, please contact us at 
                                <a href="mailto:privacy@heal2rise.org">privacy@heal2rise.org</a>.
                            </p>
                        </div>
                        
                        <!-- Section 6 -->
                        <div class="mb-5">
                            <h3 class="fw-bold mb-3">6. Data Retention</h3>
                            <p class="text-muted">
                                We retain your information for as long as your account is active or as needed to provide services. 
                                If you request account deletion, we will remove your personal information within 30 days, 
                                except where retention is required by law or for legitimate business purposes.
                            </p>
                        </div>
                        
                        <!-- Section 7 -->
                        <div class="mb-5">
                            <h3 class="fw-bold mb-3">7. Cookies and Tracking</h3>
                            <p class="text-muted">
                                We use essential cookies to maintain your session and remember your preferences. 
                                We do not use third-party tracking cookies or sell your data to advertisers.
                            </p>
                        </div>
                        
                        <!-- Section 8 -->
                        <div class="mb-5">
                            <h3 class="fw-bold mb-3">8. Children's Privacy</h3>
                            <p class="text-muted">
                                Our platform is designed for users aged 13 and above. For users under 18, 
                                parental or guardian consent may be required. We take extra precautions to protect 
                                the privacy of minor users.
                            </p>
                        </div>
                        
                        <!-- Section 9 -->
                        <div class="mb-5">
                            <h3 class="fw-bold mb-3">9. Changes to This Policy</h3>
                            <p class="text-muted">
                                We may update this Privacy Policy from time to time. We will notify you of significant changes 
                                via email or through a notice on our platform. Your continued use of our services after 
                                changes become effective constitutes acceptance of the updated policy.
                            </p>
                        </div>
                        
                        <!-- Section 10 -->
                        <div class="mb-4">
                            <h3 class="fw-bold mb-3">10. Contact Us</h3>
                            <p class="text-muted">
                                If you have questions about this Privacy Policy or our data practices, please contact us:
                            </p>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <p class="mb-1"><strong>Heal2Rise Book Privacy Team</strong></p>
                                    <p class="mb-1 text-muted">Email: <a href="mailto:privacy@heal2rise.org">privacy@heal2rise.org</a></p>
                                    <p class="mb-1 text-muted">Address: 456 Healing Avenue, G-7/1, Islamabad, Punjab 44000, Pakistan</p>
                                    <p class="mb-0 text-muted">Phone: +92 (51) 1234-5678</p>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="text-center">
                            <p class="text-muted mb-3">By using Heal2Rise Book, you agree to this Privacy Policy.</p>
                            <a href="<?= url('/') ?>" class="btn btn-primary">
                                <i class="bi bi-house me-2"></i>Return to Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
