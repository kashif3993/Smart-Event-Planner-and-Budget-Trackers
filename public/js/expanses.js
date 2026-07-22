/* ── Modal helpers ── */
function openExpenseModal() {
    resetExpenseForm();
    document.getElementById('expenseModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeExpenseModal() {
    document.getElementById('expenseModal').classList.remove('show');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeAllRowMenus();
    }
});

/* ── Reset form back to "create" mode ── */
function resetExpenseForm() {
    var form = document.getElementById('expenseForm');
    form.reset();
    form.action = window.expensesStoreUrl;
    document.getElementById('formMethod').value = '';

    document.getElementById('modalTitle').innerHTML =
        '<i class="fas fa-plus-circle" style="color:#4f46e5;margin-right:8px;"></i>Add New Expense';
    document.getElementById('modalSubmitBtn').innerHTML = '<i class="fas fa-save"></i> <span>Save Expense</span>';

    var catSelect = document.getElementById('modal_category_id');
    catSelect.innerHTML = '<option value="">— Select event first —</option>';
    catSelect.disabled = true;
    document.getElementById('cat-hint').style.display = 'none';
}

/* ── Delete expense ── */
function deleteExpense(id) {
    closeAllRowMenus();
    if (confirm('Delete this expense? This cannot be undone.')) {
        document.getElementById('delete-form-' + id).submit();
    }
}

/* ── Edit expense: populate modal from row's data attribute ── */
function editExpense(btn) {
    closeAllRowMenus();
    var data = JSON.parse(btn.getAttribute('data-expense'));

    resetExpenseForm();

    document.getElementById('expenseForm').action = window.expensesUpdateUrlBase + '/' + data.id;
    document.getElementById('formMethod').value = 'PUT';

    document.getElementById('modalTitle').innerHTML =
        '<i class="fas fa-pen" style="color:#4f46e5;margin-right:8px;"></i>Edit Expense';
    document.getElementById('modalSubmitBtn').innerHTML = '<i class="fas fa-save"></i> <span>Update Expense</span>';

    document.getElementById('modal_event_id').value = data.event_id;
    document.getElementById('modal_vendor_item_name').value = data.vendor_item_name || '';
    document.getElementById('modal_estimated_cost').value = data.estimated_cost || '';
    document.getElementById('modal_actual_cost').value = data.actual_cost || '';
    document.getElementById('modal_payment_status').value = data.payment_status || 'Pending';
    document.getElementById('modal_date_logged').value = data.date_logged || '';
    document.getElementById('modal_notes').value = data.notes || '';

    if (data.event_id) {
        loadCategories(data.event_id, data.category_id);
    }

    document.getElementById('expenseModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

/* ── Load categories for selected event via AJAX ── */
function loadCategories(eventId, selectedCategoryId) {
    var select  = document.getElementById('modal_category_id');
    var hint    = document.getElementById('cat-hint');

    select.innerHTML = '<option value="">Loading…</option>';
    select.disabled  = true;
    hint.style.display = 'none';

    if (!eventId) {
        select.innerHTML = '<option value="">— Select event first —</option>';
        return;
    }

    fetch(window.vendorCategoriesUrl + '/' + eventId + '/vendor-categories', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        select.innerHTML = '<option value="">— Select a category —</option>';

        if (data.length === 0) {
            hint.style.display = 'block';
            return;
        }

        data.forEach(function (cat) {
            var opt = document.createElement('option');
            opt.value       = cat.id;
            opt.textContent = cat.vendor_name ? cat.category_name + ' - ' + cat.vendor_name : cat.category_name;
            select.appendChild(opt);
        });

        select.disabled = false;

        if (selectedCategoryId) {
            select.value = selectedCategoryId;
        }
    })
    .catch(function () {
        select.innerHTML = '<option value="">Failed to load — try again</option>';
    });
}

/* ── Row kebab menu ── */
function toggleRowMenu(event, id) {
    event.stopPropagation();
    var menu = document.getElementById('row-dropdown-' + id);
    var isOpen = menu.classList.contains('show');
    closeAllRowMenus();
    if (!isOpen) {
        menu.classList.add('show');
    }
}

function closeAllRowMenus() {
    document.querySelectorAll('.row-dropdown.show').forEach(function (menu) {
        menu.classList.remove('show');
    });
}

document.addEventListener('click', function () {
    closeAllRowMenus();
});

/* ── Doughnut Chart ── */
document.addEventListener('DOMContentLoaded', function () {
    var canvas = document.getElementById('categoryChart');
    if (!canvas) return;

    var categories = window.expenseCategories || {};
    var keys = Object.keys(categories);
    if (!keys.length) return;

    var colors = ['#4f46e5', '#8b5cf6', '#0d9488', '#ef4444', '#f59e0b', '#06b6d4'];

    new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: keys,
            datasets: [{
                data: Object.values(categories),
                backgroundColor: colors.slice(0, keys.length),
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            return ' PKR ' + Number(ctx.parsed).toLocaleString();
                        }
                    }
                }
            }
        }
    });
});
