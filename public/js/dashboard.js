document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebarToggle && sidebar && overlay) {
        // Toggle sidebar open/close
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-open');
            overlay.classList.toggle('show');
        });

        // Close sidebar when clicking on overlay
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('sidebar-open');
            overlay.classList.remove('show');
        });
    }

    // Header dropdowns (notifications, user profile)
    const dropdowns = document.querySelectorAll('.dropdown');

    function closeDropdown(dropdown) {
        dropdown.classList.remove('open');
        const trigger = dropdown.querySelector('[aria-expanded]');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
    }

    function closeAllDropdowns(except) {
        dropdowns.forEach(function(dropdown) {
            if (dropdown !== except) closeDropdown(dropdown);
        });
    }

    dropdowns.forEach(function(dropdown) {
        const trigger = dropdown.querySelector('[aria-expanded]');
        if (!trigger) return;

        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = dropdown.classList.contains('open');
            closeAllDropdowns(dropdown);
            dropdown.classList.toggle('open', !isOpen);
            trigger.setAttribute('aria-expanded', String(!isOpen));
        });

        trigger.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                trigger.click();
            }
        });
    });

    document.addEventListener('click', function() {
        closeAllDropdowns();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeAllDropdowns();
    });
});
