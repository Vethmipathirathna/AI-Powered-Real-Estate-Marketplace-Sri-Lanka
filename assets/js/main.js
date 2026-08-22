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
});
