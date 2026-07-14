document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebarToggle && sidebar && overlay) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-open');
            overlay.classList.toggle('show');
        });

        overlay.addEventListener('click', function() {
            sidebar.classList.remove('sidebar-open');
            overlay.classList.remove('show');
        });
    }

    // Header dropdowns (notifications, user profile, phase filter)
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
    });

    document.addEventListener('click', function() {
        closeAllDropdowns();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllDropdowns();
            closeAllModals();
        }
    });

    // Generic modal open/close (event + task modals)
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.add('open');
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.remove('open');
    }

    function closeAllModals() {
        document.querySelectorAll('.modal-overlay.open').forEach(function(modal) {
            modal.classList.remove('open');
        });
    }

    document.querySelectorAll('[data-open-modal]').forEach(function(trigger) {
        trigger.addEventListener('click', function() {
            openModal(trigger.getAttribute('data-open-modal'));
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(function(trigger) {
        trigger.addEventListener('click', function() {
            closeModal(trigger.getAttribute('data-close-modal'));
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal(modal.id);
        });
    });

    // Auto-open the create-event modal when navigated to with ?new=1 (sidebar "+ New Event" button)
    if (new URLSearchParams(window.location.search).get('new') === '1' && document.getElementById('eventModal')) {
        openModal('eventModal');
    }

    // Share button - copy the current page URL to the clipboard
    const shareBtn = document.getElementById('shareEventBtn');
    if (shareBtn) {
        shareBtn.addEventListener('click', function() {
            navigator.clipboard.writeText(window.location.href).then(function() {
                const original = shareBtn.innerHTML;
                shareBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                setTimeout(function() { shareBtn.innerHTML = original; }, 2000);
            });
        });
    }

    // Venue image preview in the event form modal
    const venueImageInput = document.getElementById('venue_image');
    if (venueImageInput) {
        venueImageInput.addEventListener('change', function() {
            const file = venueImageInput.files[0];
            if (!file) return;

            let preview = venueImageInput.parentElement.querySelector('.venue-image-preview');
            if (!preview) {
                preview = document.createElement('img');
                preview.className = 'venue-image-preview';
                venueImageInput.parentElement.appendChild(preview);
            }
            preview.src = URL.createObjectURL(file);
        });
    }
});
