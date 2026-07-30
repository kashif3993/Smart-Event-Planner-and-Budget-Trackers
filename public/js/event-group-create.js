document.addEventListener('DOMContentLoaded', function () {
    var candidates = window.eventGroupCandidateEvents || [];
    var byId = {};
    candidates.forEach(function (e) { byId[e.id] = e; });

    var list = document.getElementById('eventSelectList');
    var preview = document.getElementById('groupPreview');
    var previewCount = document.getElementById('previewCount');
    var previewBudget = document.getElementById('previewBudget');
    var previewGuests = document.getElementById('previewGuests');
    var currencyWarning = document.getElementById('currencyWarning');
    var createBtn = document.getElementById('createGroupBtn');
    var groupTypeSelect = document.getElementById('group_type');
    var customTypeWrap = document.getElementById('customGroupTypeWrap');

    function toggleCustomType() {
        if (!groupTypeSelect || !customTypeWrap) return;
        customTypeWrap.style.display = groupTypeSelect.value === 'Custom' ? 'block' : 'none';
    }

    if (groupTypeSelect) {
        toggleCustomType();
        groupTypeSelect.addEventListener('change', toggleCustomType);
    }

    function refresh() {
        if (!list) return;

        var checked = Array.prototype.slice.call(list.querySelectorAll('input[type="checkbox"]:checked'));
        var selected = checked.map(function (input) { return byId[Number(input.value)]; }).filter(Boolean);

        Array.prototype.forEach.call(list.querySelectorAll('.event-select-row'), function (row) {
            var input = row.querySelector('input[type="checkbox"]');
            row.classList.toggle('is-checked', input && input.checked);
        });

        if (selected.length === 0) {
            preview.style.display = 'none';
            currencyWarning.style.display = 'none';
            createBtn.disabled = true;
            return;
        }

        preview.style.display = 'block';

        var currencies = {};
        var totalBudget = 0;
        var totalGuests = 0;

        selected.forEach(function (e) {
            currencies[e.currency] = true;
            totalBudget += Number(e.total_budget || 0);
            totalGuests += Number(e.guest_count || 0);
        });

        var currencyKeys = Object.keys(currencies);
        var symbol = currencyKeys.length === 1 && currencyKeys[0] === 'USD' ? '$' : 'PKR ';

        previewCount.textContent = selected.length;
        previewBudget.textContent = symbol + totalBudget.toLocaleString(undefined, { maximumFractionDigits: 0 });
        previewGuests.textContent = totalGuests;

        var mixedCurrency = currencyKeys.length > 1;
        currencyWarning.style.display = mixedCurrency ? 'block' : 'none';

        var validCount = selected.length >= 2 && selected.length <= 6;
        createBtn.disabled = !validCount || mixedCurrency;
    }

    if (list) {
        list.addEventListener('change', refresh);
        refresh();
    }
});
