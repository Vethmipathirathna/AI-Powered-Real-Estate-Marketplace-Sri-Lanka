// RealEstateAI - Main JavaScript

document.addEventListener('DOMContentLoaded', function () {
    var navbarCollapse = document.getElementById('mainNavbar');

    // Close the mobile menu after choosing a nav link
    if (navbarCollapse && typeof bootstrap !== 'undefined') {
        navbarCollapse.querySelectorAll('.nav-link, .btn').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.getComputedStyle(navbarCollapse).display === 'block' && navbarCollapse.classList.contains('show')) {
                    var instance = bootstrap.Collapse.getInstance(navbarCollapse);
                    if (instance) {
                        instance.hide();
                    }
                }
            });
        });
    }

    var areaSelect = document.getElementById('area_select');
    var areaOtherWrap = document.querySelector('.ai-area-other-wrap');
    var areaOtherInput = document.getElementById('area_other');

    function syncAreaOtherField() {
        if (!areaSelect || !areaOtherWrap) {
            return;
        }
        var otherValue = areaSelect.getAttribute('data-other-value') || '__other__';
        var useOther = areaSelect.value === otherValue;
        areaOtherWrap.hidden = !useOther;
        if (areaOtherInput) {
            if (useOther) {
                areaOtherInput.setAttribute('required', 'required');
            } else {
                areaOtherInput.removeAttribute('required');
            }
        }
    }

    if (areaSelect) {
        areaSelect.addEventListener('change', syncAreaOtherField);
        syncAreaOtherField();
    }

    initHomePageAnimations();
    initLoginFormEnhancements();
});

function initLoginFormEnhancements() {
    var toggleButtons = document.querySelectorAll('[data-password-toggle]');

    toggleButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var targetId = button.getAttribute('aria-controls');
            if (!targetId) {
                return;
            }

            var input = document.getElementById(targetId);
            if (!input) {
                return;
            }

            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            button.textContent = isHidden ? 'Hide' : 'Show';
            button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });
}

function initHomePageAnimations() {
    var homePage = document.querySelector('main.home-page');
    if (!homePage) {
        return;
    }

    var revealElements = homePage.querySelectorAll('.reveal');
    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (revealElements.length === 0 || prefersReducedMotion) {
        revealElements.forEach(function (element) {
            element.classList.add('is-visible');
        });
        return;
    }

    document.documentElement.classList.add('js-reveal-ready');

    if (!('IntersectionObserver' in window)) {
        revealElements.forEach(function (element) {
            element.classList.add('is-visible');
        });
        return;
    }

    var revealObserver = new IntersectionObserver(function (entries, observer) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) {
                return;
            }
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, {
        threshold: 0.08,
        rootMargin: '0px 0px -5% 0px',
    });

    revealElements.forEach(function (element) {
        revealObserver.observe(element);
    });
}
