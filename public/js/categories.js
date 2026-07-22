document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    // ----- Populate the Edit Category modal from the clicked card's data attributes -----
    const editForm = document.getElementById('categoryEditForm');
    const actionTemplate = editForm ? editForm.dataset.actionTemplate : null;
    let currentEditId = null;

    document.querySelectorAll('.category-edit-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!editForm || !actionTemplate) return;

            currentEditId = button.dataset.id;
            editForm.action = actionTemplate.replace('__CATEGORY__', currentEditId);

            editForm.querySelector('#edit_category_name').value = button.dataset.name || '';
            editForm.querySelector('#edit_vendor_name').value = button.dataset.vendor || '';
            editForm.querySelector('#edit_suggested_percentage').value = button.dataset.suggested || '';
            editForm.querySelector('#edit_allocated_amount').value = button.dataset.allocated || '';
            editForm.querySelector('#edit_ai_slash_priority').value = button.dataset.priority || '';
            editForm.querySelector('#edit_notes').value = button.dataset.notes || '';
            editForm.querySelector('#edit_is_locked').checked = button.dataset.locked === '1';
        });
    });

    // ----- Delete a category (card action or edit-modal footer) -----
    window.deleteVendorCategory = function (id) {
        if (confirm('Delete this vendor category? Its logged expenses will be removed too. This cannot be undone.')) {
            const form = document.getElementById('category-delete-form-' + id);
            if (form) form.submit();
        }
    };

    window.deleteVendorCategoryFromEdit = function () {
        if (currentEditId) window.deleteVendorCategory(currentEditId);
    };

    // ----- Lock / unlock toggle (instant, no reload) -----
    document.querySelectorAll('.lock-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const url = button.getAttribute('data-toggle-lock-url');
            if (!url) return;

            button.disabled = true;

            fetch(url, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    const icon = button.querySelector('i');
                    button.classList.toggle('is-locked', data.is_locked);
                    if (icon) {
                        icon.classList.toggle('fa-lock', data.is_locked);
                        icon.classList.toggle('fa-lock-open', !data.is_locked);
                    }
                    button.title = data.is_locked ? 'Locked — click to unlock' : 'Click to lock this category';

                    const editBtn = button.parentElement.querySelector('.category-edit-btn');
                    if (editBtn) editBtn.dataset.locked = data.is_locked ? '1' : '0';
                })
                .finally(function () { button.disabled = false; });
        });
    });
});
