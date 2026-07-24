function settingsTogglePw(fieldId, btn) {
    const field = document.getElementById(fieldId);
    const isText = field.type === 'text';
    field.type = isText ? 'password' : 'text';
    btn.classList.toggle('is-visible', !isText);
}

function settingsDeleteBackup(slug) {
    if (confirm('Delete this backup? This cannot be undone.')) {
        document.getElementById('backup-delete-form-' + slug).submit();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    /* ── Avatar preview on file select ── */
    const fileInput = document.getElementById('profile_image');
    const preview = document.getElementById('avatarPreview');

    if (fileInput && preview) {
        fileInput.addEventListener('change', function () {
            const file = fileInput.files && fileInput.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (e) {
                if (preview.tagName === 'IMG') {
                    preview.src = e.target.result;
                } else {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.id = 'avatarPreview';
                    img.className = 'settings-avatar-preview';
                    preview.replaceWith(img);
                }
            };
            reader.readAsDataURL(file);
        });
    }

    /* ── Delete-account confirmation: require a typed password before enabling submit ── */
    const deleteModal = document.getElementById('deleteAccountModal');
    const deletePasswordField = document.getElementById('delete_current_password');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    if (deleteModal && deletePasswordField && confirmDeleteBtn) {
        deletePasswordField.addEventListener('input', function () {
            confirmDeleteBtn.disabled = deletePasswordField.value.trim().length === 0;
        });

        // The shared modal system (events.js) resets the form on close, which
        // clears the password value but doesn't re-fire 'input' — reset the
        // button's disabled state explicitly whenever this modal opens/closes.
        document.querySelectorAll('[data-open-modal="deleteAccountModal"], [data-close-modal="deleteAccountModal"]')
            .forEach(function (trigger) {
                trigger.addEventListener('click', function () {
                    confirmDeleteBtn.disabled = true;
                });
            });
    }
});
