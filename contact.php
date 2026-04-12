<?php
/**
 * Heal2Rise Book - Contact Us Page
 */

$pageTitle = 'Contact Us';
require_once __DIR__ . '/includes/header.php';

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // In a real application, you would send an email or store in database
        $success = 'Thank you for your message! We will get back to you within 24-48 hours.';
    }
}
?>

<main id="main-content">
<!-- Hero Section -->
<section class="hero-section" aria-labelledby="contact-title">
    <div class="container">
        <div class="text-center hero-content">
            <h1 id="contact-title">Contact Us</h1>
            <p class="lead mx-auto content-max-sm">We're here to help. Reach out to us anytime and our team will respond within 24-48 hours.</p>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section class="section-padding">
    <div class="container">
        <div class="row g-5">
            <!-- Contact Form -->
            <div class="col-md-7 col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="bi bi-envelope me-2"></i>Send us a Message</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i><?= $success ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-circle me-2"></i><?= $error ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" aria-label="Contact form">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Your Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" placeholder="John Doe" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" placeholder="john@example.com" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Subject <span class="text-danger">*</span></label>
                                    <select name="subject" class="form-select" required>
                                        <option value="">Select a subject...</option>
                                        <option value="general">General Inquiry</option>
                                        <option value="support">Technical Support</option>
                                        <option value="partnership">Partnership Inquiry</option>
                                        <option value="feedback">Feedback</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Message <span class="text-danger">*</span></label>
                                    <textarea name="message" class="form-control" rows="5" placeholder="How can we help you?" required></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-send me-2"></i>Send Message
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            
            <!-- Contact Info -->
            <div class="col-md-5 col-lg-5">
                <div class="card mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-4">Get in Touch</h5>
                        
                        <div class="d-flex mb-4">
                            <div class="icon-box me-3 icon-box-sm icon-lg-text">
                                <i class="bi bi-geo-alt"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Address</h6>
                                <p class="text-muted mb-0">456 Healing Avenue, G-7/1<br>Islamabad, Punjab 44000<br>Pakistan</p>
                            </div>
                        </div>
                        
                        <div class="d-flex mb-4">
                            <div class="icon-box me-3 icon-box-sm icon-lg-text">
                                <i class="bi bi-envelope"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Email</h6>
                                <p class="text-muted mb-0">support@heal2rise.org<br>partnerships@heal2rise.org</p>
                            </div>
                        </div>
                        
                        <div class="d-flex mb-4">
                            <div class="icon-box me-3 icon-box-sm icon-lg-text">
                                <i class="bi bi-telephone"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Phone</h6>
                                <p class="text-muted mb-0">+92 (51) 1234-5678<br>Mon-Fri: 9:00 AM - 6:00 PM PKT</p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h6 class="mb-3">Follow Us</h6>
                        <div class="d-flex gap-2">
                            <a href="#" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-facebook"></i>
                            </a>
                            <a href="#" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-twitter"></i>
                            </a>
                            <a href="#" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-linkedin"></i>
                            </a>
                            <a href="#" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-instagram"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Emergency Support -->
                <div class="card border-danger">
                    <div class="card-body p-4">
                        <h5 class="text-danger fw-bold mb-3">
                            <i class="bi bi-exclamation-triangle me-2"></i>Need Immediate Help?
                        </h5>
                        <p class="text-muted mb-3">
                            If you or someone you know is in crisis or experiencing a mental health emergency, 
                            please reach out to these helplines immediately:
                        </p>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="bi bi-telephone-fill text-danger me-2"></i>
                                <strong>iCall:</strong> 9152987821
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-telephone-fill text-danger me-2"></i>
                                <strong>Vandrevala Foundation:</strong> 1860-2662-345
                            </li>
                            <li>
                                <i class="bi bi-telephone-fill text-danger me-2"></i>
                                <strong>NIMHANS:</strong> 080-46110007
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="section-padding bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Frequently Asked Questions</h2>
            <p class="text-muted">Quick answers to common questions</p>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button">
                                Is my information kept confidential?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-parent="#faqAccordion">
                            <div class="accordion-body">
                                Absolutely. We take privacy very seriously. Your personal information is encrypted and only shared 
                                with verified NGOs when you choose to connect with them. We never sell or share your data with third parties.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button">
                                How are NGOs verified?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-parent="#faqAccordion">
                            <div class="accordion-body">
                                All NGOs go through a rigorous verification process. We verify their registration documents, 
                                certifications, and conduct background checks. Only organizations meeting our quality standards 
                                are approved to join the platform.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button">
                                Is the service free?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes, Heal2Rise Book is completely free for individuals seeking support. NGOs may have their 
                                own fee structures for extended services, but the platform and initial connection are always free.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button">
                                How quickly will I be matched with an NGO?
                            </button>
                        </h2>
                        <div id="faq4" class="accordion-collapse collapse" data-parent="#faqAccordion">
                            <div class="accordion-body">
                                After you register and submit your case, verified NGOs in your area will review your situation. 
                                Most users are matched within 24-48 hours. You'll receive notifications when an NGO expresses 
                                interest in supporting your case.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
