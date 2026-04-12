/**
 * Heal2Rise Book — Lightweight UI Components
 * Replaces Bootstrap JS with custom vanilla JavaScript
 * Handles: Navbar collapse, Dropdowns, Tabs, Accordion, Alert dismiss, Toasts
 */

document.addEventListener('DOMContentLoaded', function () {
    initNavbarCollapse();
    initNavbarScroll();
    initDropdowns();
    initTabs();
    initAccordion();
    initAlertDismiss();
    initCollapse();
    initSmoothFadeIn();
});

/* ============================================
   NAVBAR COLLAPSE (Mobile hamburger toggle)
   ============================================ */
function initNavbarCollapse() {
    document.querySelectorAll('[data-toggle="collapse"]').forEach(function (toggler) {
        toggler.addEventListener('click', function (e) {
            e.preventDefault();
            var targetSel = this.getAttribute('data-target');
            if (!targetSel) return;
            var target = document.querySelector(targetSel);
            if (!target) return;
            target.classList.toggle('show');
        });
    });
}

/* ============================================
   GENERIC COLLAPSE
   ============================================ */
function initCollapse() {
    // Handle any generic collapse toggles not covered by accordion or navbar
    // Uses data-toggle="collapse" and data-target="#id"
}

/* ============================================
   DROPDOWNS
   ============================================ */
function initDropdowns() {
    // Toggle dropdown on click
    document.querySelectorAll('[data-toggle="dropdown"]').forEach(function (toggle) {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var menu = this.nextElementSibling;
            if (!menu || !menu.classList.contains('dropdown-menu')) {
                // Try parent's query
                menu = this.parentElement.querySelector('.dropdown-menu');
            }
            if (!menu) return;

            // Close other open dropdowns first
            document.querySelectorAll('.dropdown-menu.show').forEach(function (openMenu) {
                if (openMenu !== menu) openMenu.classList.remove('show');
            });

            menu.classList.toggle('show');
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(function (menu) {
                menu.classList.remove('show');
            });
        }
    });

    // Close dropdowns on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.dropdown-menu.show').forEach(function (menu) {
                menu.classList.remove('show');
            });
            // Also close navbar collapse on mobile
            document.querySelectorAll('.navbar-collapse.show').forEach(function (collapse) {
                collapse.classList.remove('show');
            });
        }
    });
}

/* ============================================
   TABS
   ============================================ */
function initTabs() {
    document.querySelectorAll('[data-toggle="tab"]').forEach(function (tabLink) {
        tabLink.addEventListener('click', function (e) {
            e.preventDefault();

            var targetSel = this.getAttribute('href') || this.getAttribute('data-target');
            if (!targetSel) return;

            // Deactivate sibling tab links
            var tabNav = this.closest('.nav, .nav-tabs');
            if (tabNav) {
                tabNav.querySelectorAll('.nav-link').forEach(function (link) {
                    link.classList.remove('active');
                });
            }

            // Activate clicked tab
            this.classList.add('active');

            // Hide all sibling tab panes
            var tabContent = document.querySelector(targetSel);
            if (!tabContent) return;

            var tabContainer = tabContent.parentElement;
            if (tabContainer) {
                tabContainer.querySelectorAll('.tab-pane').forEach(function (pane) {
                    pane.classList.remove('active', 'show');
                });
            }

            // Show target pane
            tabContent.classList.add('active', 'show');
        });
    });
}

/* ============================================
   ACCORDION
   ============================================ */
function initAccordion() {
    document.querySelectorAll('.accordion-button').forEach(function (button) {
        button.addEventListener('click', function () {
            var item = this.closest('.accordion-item');
            if (!item) return;

            var collapse = item.querySelector('.accordion-collapse');
            if (!collapse) return;

            var accordion = this.closest('.accordion');
            var isOpen = collapse.classList.contains('show');

            // If part of an accordion group, close others
            if (accordion) {
                var parentId = collapse.getAttribute('data-parent') || (accordion ? '#' + accordion.id : null);
                if (parentId) {
                    var parent = document.querySelector(parentId) || accordion;
                    parent.querySelectorAll('.accordion-collapse.show').forEach(function (openCollapse) {
                        if (openCollapse !== collapse) {
                            openCollapse.classList.remove('show');
                            var otherButton = openCollapse.closest('.accordion-item').querySelector('.accordion-button');
                            if (otherButton) otherButton.classList.add('collapsed');
                        }
                    });
                }
            }

            // Toggle current
            if (isOpen) {
                collapse.classList.remove('show');
                this.classList.add('collapsed');
            } else {
                collapse.classList.add('show');
                this.classList.remove('collapsed');
            }
        });
    });
}

/* ============================================
   ALERT DISMISS
   ============================================ */
function initAlertDismiss() {
    document.addEventListener('click', function (e) {
        var dismissBtn = e.target.closest('[data-dismiss="alert"]');
        if (!dismissBtn) return;

        var alert = dismissBtn.closest('.alert');
        if (!alert) return;

        alert.style.opacity = '0';
        alert.style.transition = 'opacity 150ms ease';
        setTimeout(function () {
            alert.remove();
        }, 150);
    });
}

/* ============================================
   TOAST SYSTEM (showToast global function)
   ============================================ */
window.showToast = function (message, type) {
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

    // Dismiss button
    toast.querySelector('[data-dismiss="toast"]').addEventListener('click', function () {
        removeToast(toast);
    });

    container.appendChild(toast);

    // Auto-remove after 5 seconds
    setTimeout(function () {
        removeToast(toast);
    }, 5000);
};

function removeToast(toast) {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity 150ms ease';
    setTimeout(function () {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 150);
}

/* ============================================
   NAVBAR SHADOW ON SCROLL
   ============================================ */
function initNavbarScroll() {
    var navbar = document.querySelector('.navbar');
    if (!navbar) return;

    function updateNavbar() {
        if (window.scrollY > 10) {
            navbar.style.boxShadow = '0 4px 20px rgba(44, 62, 80, 0.08)';
        } else {
            navbar.style.boxShadow = 'none';
        }
    }

    window.addEventListener('scroll', updateNavbar, { passive: true });
    updateNavbar();
}

/* ============================================
   SMOOTH FADE-IN ON SCROLL (Calm entrance)
   ============================================ */
function initSmoothFadeIn() {
    // Skip if user prefers reduced motion
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var elements = document.querySelectorAll('.feature-card, .stat-card, .card.h-100');
    if (!elements.length) return;

    elements.forEach(function (el) {
        el.style.opacity = '0';
        el.style.transform = 'translateY(16px)';
        el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    });

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

    elements.forEach(function (el) { observer.observe(el); });
}
