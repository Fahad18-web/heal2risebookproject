    <!-- Footer -->
    <footer class="footer mt-5" aria-label="Site footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6 col-lg-4 mb-4">
                    <h5 class="mb-3">
                        <i class="bi bi-heart-pulse-fill me-2" style="color: var(--color-primary-400);"></i>
                        Heal2Rise Book
                    </h5>
                    <p class="mb-4">
                        A confidential support platform focused on healing, emotional safety, and long-term well-being.
                    </p>
                    <div class="social-links d-flex gap-2" aria-label="Social media links">
                        <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="#" aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
                        <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-4">
                    <h6 class="mb-3">Explore</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="<?= url('/index.php') ?>">Home</a></li>
                        <li class="mb-2"><a href="<?= url('/about.php') ?>">About Us</a></li>
                        <li class="mb-2"><a href="<?= url('/contact.php') ?>">Contact</a></li>
                        <li class="mb-2"><a href="<?= url('/privacy.php') ?>">Privacy Policy</a></li>
                        <li class="mb-2"><a href="<?= url('/donation.php') ?>">Donate</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h6 class="mb-3">Get Started</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="<?= url('/user/register.php') ?>">Register as User</a></li>
                        <li class="mb-2"><a href="<?= url('/ngo/register.php') ?>">Register as NGO</a></li>
                        <li class="mb-2"><a href="<?= url('/user/login.php') ?>">Sign In</a></li>
                        <li class="mb-2"><a href="<?= url('/about.php') ?>">How It Works</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h6 class="mb-3">Always Available</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-envelope me-2"></i>support@heal2rise.org</li>
                        <li class="mb-2"><i class="bi bi-telephone me-2"></i>+92 (300) 1234-567</li>
                        <li class="mb-2"><i class="bi bi-clock me-2"></i>24/7 Helpline</li>
                    </ul>
                    <div class="mt-4">
                        <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>100% Confidential</span>
                    </div>
                </div>
            </div>
            <hr>
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; <?= date('Y') ?> Heal2Rise Book. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0">
                        <i class="bi bi-lock-fill me-1" style="color: var(--color-primary-400);"></i>Your data is encrypted and secure
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="<?= url('/assets/js/components.js') ?>"></script>
    <script src="<?= url('/assets/js/validation.js') ?>"></script>
    <?php if (isset($extraJS)): ?>
        <?php foreach ($extraJS as $js): ?>
            <script src="<?= $js ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
