/**
 * Heal2Rise Book - Form Validation
 * Client-side validation for all forms
 */

document.addEventListener('DOMContentLoaded', function() {
    // Add validation to all forms with 'needs-validation' class
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Password strength indicator
    const passwordInputs = document.querySelectorAll('input[type="password"][data-strength]');
    passwordInputs.forEach(input => {
        const strengthIndicator = document.getElementById(input.dataset.strength);
        if (strengthIndicator) {
            input.addEventListener('input', function() {
                const strength = checkPasswordStrength(this.value);
                updateStrengthIndicator(strengthIndicator, strength);
            });
        }
    });

    // Password confirmation matching
    const confirmPasswordInputs = document.querySelectorAll('[data-match]');
    confirmPasswordInputs.forEach(input => {
        const matchInput = document.getElementById(input.dataset.match);
        if (matchInput) {
            input.addEventListener('input', function() {
                if (this.value !== matchInput.value) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
            
            matchInput.addEventListener('input', function() {
                if (input.value !== this.value) {
                    input.setCustomValidity('Passwords do not match');
                } else {
                    input.setCustomValidity('');
                }
            });
        }
    });

    // Phone number formatting
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 10) {
                value = value.slice(0, 10);
            }
            this.value = value;
        });
    });

    // Email validation
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value && !isValidEmail(this.value)) {
                this.setCustomValidity('Please enter a valid email address');
            } else {
                this.setCustomValidity('');
            }
        });
    });

    // Show/hide password toggle
    const passwordToggles = document.querySelectorAll('.password-toggle');
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = document.getElementById(this.dataset.target);
            if (input) {
                const type = input.type === 'password' ? 'text' : 'password';
                input.type = type;
                this.querySelector('i').className = type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
            }
        });
    });

    // File upload preview
    const fileInputs = document.querySelectorAll('input[type="file"][data-preview]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const preview = document.getElementById(this.dataset.preview);
            if (preview && this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });

    // Character counter for textareas
    const textareas = document.querySelectorAll('textarea[data-counter]');
    textareas.forEach(textarea => {
        const counter = document.getElementById(textarea.dataset.counter);
        const maxLength = textarea.getAttribute('maxlength');
        
        if (counter && maxLength) {
            updateCounter();
            textarea.addEventListener('input', updateCounter);
            
            function updateCounter() {
                const remaining = maxLength - textarea.value.length;
                counter.textContent = `${remaining} characters remaining`;
                counter.className = remaining < 50 ? 'form-text text-danger' : 'form-text text-muted';
            }
        }
    });

    // Auto-dismiss alerts
    const alerts = document.querySelectorAll('.alert-auto-dismiss');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade');
            setTimeout(() => alert.remove(), 150);
        }, 5000);
    });

    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm(this.dataset.confirm || 'Are you sure you want to proceed?')) {
                e.preventDefault();
            }
        });
    });
});

/**
 * Check password strength
 */
function checkPasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    
    if (strength <= 2) return 'weak';
    if (strength <= 4) return 'medium';
    return 'strong';
}

/**
 * Update password strength indicator
 */
function updateStrengthIndicator(element, strength) {
    const colors = {
        'weak': 'danger',
        'medium': 'warning',
        'strong': 'success'
    };
    
    const widths = {
        'weak': '33%',
        'medium': '66%',
        'strong': '100%'
    };
    
    element.className = `progress-bar bg-${colors[strength]}`;
    element.style.width = widths[strength];
    element.textContent = strength.charAt(0).toUpperCase() + strength.slice(1);
}

/**
 * Validate email format
 */
function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

/**
 * Validate phone number
 */
function isValidPhone(phone) {
    const regex = /^[0-9+\-\s()]{10,20}$/;
    return regex.test(phone);
}

/**
 * Format date for display
 */
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

/**
 * Show loading spinner
 */
function showLoading(button) {
    button.disabled = true;
    button.dataset.originalText = button.innerHTML;
    button.innerHTML = '<span class="loading-spinner"></span> Loading...';
}

/**
 * Hide loading spinner
 */
function hideLoading(button) {
    button.disabled = false;
    button.innerHTML = button.dataset.originalText || 'Submit';
}

/**
 * Toast notification
 * Uses the showToast function from components.js
 * Kept here as a fallback if components.js hasn't loaded yet
 */
if (typeof window.showToast === 'undefined') {
    window.showToast = function(message, type) {
        type = type || 'info';
        var container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        var toast = document.createElement('div');
        toast.className = 'toast show bg-' + type;
        toast.setAttribute('role', 'alert');
        toast.innerHTML =
            '<div class="d-flex align-items-center" style="width:100%">' +
            '  <div class="toast-body">' + message + '</div>' +
            '  <button type="button" class="btn-close btn-close-white me-2" data-dismiss="toast"></button>' +
            '</div>';

        toast.querySelector('[data-dismiss="toast"]').addEventListener('click', function() {
            toast.style.opacity = '0';
            setTimeout(function() { toast.remove(); }, 150);
        });

        container.appendChild(toast);
        setTimeout(function() {
            toast.style.opacity = '0';
            setTimeout(function() { toast.remove(); }, 150);
        }, 5000);
    };
}

function createToastContainer() {
    var container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}
