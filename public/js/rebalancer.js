document.addEventListener('DOMContentLoaded', function () {
    var openBtn = document.querySelector('[data-open-modal="rebalancerModal"]');
    var modal = document.getElementById('rebalancerModal');
    if (!openBtn || !modal) return;

    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    var currency = window.rebalanceCurrencySymbol || '$';
    var originalCategories = window.rebalanceCategories || [];

    var strategyToggles = document.getElementById('rebalanceStrategyToggles');
    var statusBox = document.getElementById('rebalanceStatus');
    var deficitValueEl = document.getElementById('rebalanceDeficitValue');
    var liquidityValueEl = document.getElementById('rebalanceLiquidityValue');
    var categoryListEl = document.getElementById('rebalanceCategoryList');
    var commitBtn = document.getElementById('rebalanceCommitBtn');
    var chartCanvas = document.getElementById('rebalanceChart');

    var sandboxLockedIds = [];
    var currentStrategy = 'proportional';
    var latestProposal = [];
    var chart = null;

    function money(value) {
        return currency + Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
    }

    function renderBanner() {
        var deficit = 0;
        var liquidity = 0;

        originalCategories.forEach(function (c) {
            if (c.is_over_budget) {
                deficit += (c.spent - c.allocated_amount);
            } else {
                liquidity += (c.allocated_amount - c.spent);
            }
        });

        deficitValueEl.textContent = money(deficit);
        liquidityValueEl.textContent = money(liquidity);
    }

    function renderChart(categories) {
        var labels = categories.map(function (c) { return c.category_name; });
        var current = categories.map(function (c) { return c.allocated_amount; });
        var proposed = categories.map(function (c) { return c.suggested_allocated_amount; });

        if (!chart) {
            chart = new Chart(chartCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Current Allocation', data: current, backgroundColor: '#c7d2fe' },
                        { label: 'Proposed Allocation', data: proposed, backgroundColor: '#4f46e5' }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 500, easing: 'easeOutQuart' },
                    plugins: { legend: { position: 'bottom' } },
                    scales: { y: { beginAtZero: true } }
                }
            });
            return;
        }

        chart.data.labels = labels;
        chart.data.datasets[0].data = current;
        chart.data.datasets[1].data = proposed;
        chart.update();
    }

    function renderCategoryList(categories) {
        categoryListEl.innerHTML = '';

        categories.forEach(function (c) {
            var row = document.createElement('div');
            row.className = 'rebalance-category-row';

            var lockDisabled = c.is_over_budget || c.is_locked;
            var isChecked = sandboxLockedIds.indexOf(c.id) !== -1;

            row.innerHTML =
                '<label class="rebalance-lock-toggle' + (lockDisabled ? ' is-disabled' : '') + '">' +
                    '<input type="checkbox" data-category-id="' + c.id + '"' +
                        (isChecked ? ' checked' : '') +
                        (lockDisabled ? ' disabled' : '') + '>' +
                    '<i class="fas ' + (isChecked || c.is_locked ? 'fa-lock' : 'fa-lock-open') + '"></i>' +
                '</label>' +
                '<div class="rebalance-category-info">' +
                    '<span class="rebalance-category-name">' + c.category_name + '</span>' +
                    '<span class="rebalance-category-amounts">' +
                        money(c.allocated_amount) + ' &rarr; ' + money(c.suggested_allocated_amount) +
                    '</span>' +
                '</div>' +
                (c.is_over_budget
                    ? '<span class="chip chip-danger">Bumped to Spend</span>'
                    : (isChecked ? '<span class="chip chip-warning">Locked</span>' : ''));

            categoryListEl.appendChild(row);
        });
    }

    function loadPreview() {
        statusBox.style.display = 'block';
        statusBox.className = 'rebalance-status is-loading';
        statusBox.textContent = currentStrategy === 'ai'
            ? 'Asking the AI which categories are safest to trim...'
            : 'Recalculating proposed allocations...';
        commitBtn.disabled = true;

        fetch(window.rebalancePreviewUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                strategy: currentStrategy,
                locked_category_ids: sandboxLockedIds
            })
        })
            .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    statusBox.className = 'rebalance-status is-error';
                    statusBox.textContent = result.data.message || 'Could not build a rebalance proposal.';
                    commitBtn.disabled = true;
                    return;
                }

                statusBox.style.display = 'none';
                latestProposal = result.data.categories;
                renderChart(latestProposal);
                renderCategoryList(latestProposal);
                commitBtn.disabled = false;
            })
            .catch(function () {
                statusBox.className = 'rebalance-status is-error';
                statusBox.textContent = 'Could not build a rebalance proposal. Please try again.';
                commitBtn.disabled = true;
            });
    }

    openBtn.addEventListener('click', function () {
        sandboxLockedIds = originalCategories.filter(function (c) { return c.is_locked; }).map(function (c) { return c.id; });
        currentStrategy = 'proportional';
        var proportionalRadio = strategyToggles.querySelector('input[value="proportional"]');
        if (proportionalRadio) proportionalRadio.checked = true;

        renderBanner();
        loadPreview();
    });

    strategyToggles.addEventListener('change', function (e) {
        if (e.target.name !== 'rebalance_strategy') return;
        currentStrategy = e.target.value;
        loadPreview();
    });

    categoryListEl.addEventListener('change', function (e) {
        if (!e.target.matches('input[type="checkbox"][data-category-id]')) return;

        var categoryId = Number(e.target.getAttribute('data-category-id'));
        var index = sandboxLockedIds.indexOf(categoryId);

        if (e.target.checked && index === -1) {
            sandboxLockedIds.push(categoryId);
        } else if (!e.target.checked && index !== -1) {
            sandboxLockedIds.splice(index, 1);
        }

        loadPreview();
    });

    commitBtn.addEventListener('click', function () {
        if (!latestProposal.length) return;

        commitBtn.disabled = true;
        statusBox.style.display = 'block';
        statusBox.className = 'rebalance-status is-loading';
        statusBox.textContent = 'Committing new budget allocation...';

        fetch(window.rebalanceCommitUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                categories: latestProposal.map(function (c) {
                    return { id: c.id, allocated_amount: c.suggested_allocated_amount };
                })
            })
        })
            .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
            .then(function (result) {
                if (result.ok && result.data.success) {
                    statusBox.className = 'rebalance-status is-success';
                    statusBox.textContent = result.data.message + ' Reloading...';
                    setTimeout(function () { window.location.reload(); }, 1000);
                } else {
                    statusBox.className = 'rebalance-status is-error';
                    statusBox.textContent = result.data.message || 'Could not commit the new budget allocation.';
                    commitBtn.disabled = false;
                }
            })
            .catch(function () {
                statusBox.className = 'rebalance-status is-error';
                statusBox.textContent = 'Could not commit the new budget allocation. Please try again.';
                commitBtn.disabled = false;
            });
    });
});
