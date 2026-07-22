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
        }
    });

    // Generic modal open/close (event + task modals).
    // Modals only close via an explicit Cancel/close("x") click — never by
    // clicking the backdrop or pressing Escape — so in-progress form input
    // is never lost by accident.
    function resetModalForm(modal) {
        modal.querySelectorAll('form').forEach(function(form) {
            form.reset();
        });

        // form.reset() only restores real form fields, not DOM nodes that JS
        // injected afterwards (the venue image preview). Put those back too.
        modal.querySelectorAll('.venue-image-preview').forEach(function(preview) {
            if (preview.dataset.dynamic === 'true') {
                // Created on the fly because there was no existing image to preview.
                preview.remove();
            } else if (preview.dataset.originalSrc) {
                preview.src = preview.dataset.originalSrc;
                preview.hidden = false;
            } else {
                // An empty src="" resolves to the current page URL, not "no image" —
                // the attribute has to come off entirely.
                preview.removeAttribute('src');
                preview.hidden = true;
            }
        });
    }

    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.add('open');
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('open');
        resetModalForm(modal);
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

    // Remember the venue image preview's original src (the event's already
    // saved image, if any) so cancelling a file selection can restore it
    // instead of leaving the newly-picked file's blob preview in place.
    // getAttribute (not .src) so an absent/empty attribute stays "" instead of
    // resolving to the current page's URL.
    document.querySelectorAll('.venue-image-preview').forEach(function(preview) {
        preview.dataset.originalSrc = preview.getAttribute('src') || '';
    });

    // When a form submits, the browser can restore the *pre-submit* page
    // straight from bfcache on Back/Forward instead of asking the server for
    // a fresh copy — modal still open, still full of whatever was just typed
    // (or just created). Force every modal closed and reset whenever that
    // happens so Back never shows stale form data.
    window.addEventListener('pageshow', function(e) {
        if (!e.persisted) return;
        document.querySelectorAll('.modal-overlay').forEach(function(modal) {
            closeModal(modal.id);
        });
    });

    // Auto-open the create-event modal when navigated to with ?new=1 (sidebar "+ New Event" button)
    if (new URLSearchParams(window.location.search).get('new') === '1' && document.getElementById('eventModal')) {
        openModal('eventModal');
    }

    // Venue image preview in every event form on the page (create + edit modals)
    document.querySelectorAll('input[type="file"][name="venue_image"]').forEach(function(venueImageInput) {
        venueImageInput.addEventListener('change', function() {
            const file = venueImageInput.files[0];
            if (!file) return;

            let preview = venueImageInput.parentElement.querySelector('.venue-image-preview');
            if (!preview) {
                preview = document.createElement('img');
                preview.className = 'venue-image-preview';
                preview.dataset.dynamic = 'true';
                venueImageInput.parentElement.appendChild(preview);
            }
            preview.hidden = false;
            preview.src = URL.createObjectURL(file);
        });
    });

    // ----- Populate the Edit Event modal (events index cards) from the
    // clicked card's data attributes, the same way tasks.js fills taskModalEdit -----
    const eventEditForm = document.getElementById('eventEditForm');
    const eventDeleteForm = document.getElementById('eventDeleteForm');
    // Captured once, before any card is edited, so repeated edits always rebuild
    // the URL from the original "__EVENT__" template instead of an already-replaced one.
    const eventEditFormActionTemplate = eventEditForm ? eventEditForm.action : null;
    const eventDeleteFormActionTemplate = eventDeleteForm ? eventDeleteForm.action : null;
    const editVenuePreview = document.getElementById('edit_venue_image_preview');
    const editVenueInput = document.getElementById('edit_venue_image');

    document.querySelectorAll('.event-edit-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            if (!eventEditForm) return;
            const d = button.dataset;

            if (eventEditFormActionTemplate) {
                eventEditForm.action = eventEditFormActionTemplate.replace('__EVENT__', d.eventId);
            }
            if (eventDeleteForm && eventDeleteFormActionTemplate) {
                eventDeleteForm.action = eventDeleteFormActionTemplate.replace('__EVENT__', d.eventId);
            }

            eventEditForm.querySelector('#edit_event_name').value = d.eventName || '';
            eventEditForm.querySelector('#edit_event_type').value = d.eventType || 'Wedding';
            eventEditForm.querySelector('#edit_custom_event_type').value = d.eventCustomType || '';
            eventEditForm.querySelector('#edit_event_date').value = d.eventDate || '';
            eventEditForm.querySelector('#edit_event_time').value = d.eventTime || '';
            eventEditForm.querySelector('#edit_guest_count').value = d.eventGuestCount || 0;
            eventEditForm.querySelector('#edit_max_guests').value = d.eventMaxGuests || '';
            eventEditForm.querySelector('#edit_venue_name').value = d.eventVenueName || '';
            eventEditForm.querySelector('#edit_location').value = d.eventLocation || '';
            eventEditForm.querySelector('#edit_total_budget').value = d.eventTotalBudget || 0;
            eventEditForm.querySelector('#edit_budget_spent').value = d.eventBudgetSpent || 0;
            eventEditForm.querySelector('#edit_currency').value = d.eventCurrency || 'PKR';
            eventEditForm.querySelector('#edit_status').value = d.eventStatus || 'Planning';
            eventEditForm.querySelector('#edit_description').value = d.eventDescription || '';

            if (editVenueInput) editVenueInput.value = '';
            if (editVenuePreview) {
                if (d.eventVenueImage) {
                    editVenuePreview.src = d.eventVenueImage;
                    editVenuePreview.hidden = false;
                } else {
                    editVenuePreview.removeAttribute('src');
                    editVenuePreview.hidden = true;
                }
                editVenuePreview.dataset.originalSrc = d.eventVenueImage || '';
            }
        });
    });
});
