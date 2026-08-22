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
});
