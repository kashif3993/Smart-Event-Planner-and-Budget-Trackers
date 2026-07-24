document.addEventListener('DOMContentLoaded', function () {
    var openBtns = document.querySelectorAll('[data-open-modal="rebalancerModal"]');
    var modal = document.getElementById('rebalancerModal');
    if (!openBtns.length || !modal) return;

    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    var currency = window.rebalanceCurrencySymbol || '$';
    var totalBudget = Number(window.rebalanceTotalBudget || 0);
    var baseCategories = window.rebalanceCategories || [];

    var strategyToggles = document.getElementById('rebalanceStrategyToggles');
    var statusBox = document.getElementById('rebalanceStatus');
    var deficitValueEl = document.getElementById('rebalanceDeficitValue');
    var liquidityValueEl = document.getElementById('rebalanceLiquidityValue');
    var categoryListEl = document.getElementById('rebalanceCategoryList');
    var commitBtn = document.getElementById('rebalanceCommitBtn');
    var chartCanvas = document.getElementById('rebalanceChart');

    // Fresh per sandbox session (reset on every modal open) — the PRD's "State
    // Isolation" requirement: all tinkering happens in this local snapshot,
    // nothing touches the server until Commit.
    var sandboxCategories = [];
    var sandboxLockedIds = [];
    var currentStrategy = 'proportional';
    var aiPriorities = null; // { [categoryId]: priority } — fetched once per session, then reused locally
    var latestProposal = [];
    var chart = null;

    function money(value) {
        return currency + Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
    }

    function round2(value) {
        return Math.round((value + Number.EPSILON) * 100) / 100;
    }

    function renderBanner() {
        var deficit = 0;
        var liquidity = 0;

        sandboxCategories.forEach(function (c) {
            if (c.is_over_budget) {
                deficit += (c.spent - c.allocated_amount);
            } else {
                liquidity += (c.allocated_amount - c.spent);
            }
        });

        deficitValueEl.textContent = money(deficit);
        liquidityValueEl.textContent = money(liquidity);
    }

    /* ── Pure local math, mirroring BudgetRebalancerService exactly ──
       (proportional / targeted / AI-weighted reduction distribution) so the
       interactive chart never needs a round trip except the one-time AI
       priorities fetch. */

    function proportionalReductions(mutable, deficit) {
        var totalAllocated = mutable.reduce(function (sum, c) { return sum + c.allocated_amount; }, 0);
        var reductions = {};
        if (totalAllocated <= 0 || deficit <= 0) return reductions;

        mutable.forEach(function (c) {
            var share = c.allocated_amount / totalAllocated;
            var headroom = c.allocated_amount - c.spent;
            reductions[c.id] = Math.min(deficit * share, headroom);
        });

        return reductions;
    }

    function targetedReductions(mutable, deficit) {
        var reductions = {};
        if (deficit <= 0) return reductions;

        var remaining = deficit;
        var sorted = mutable.slice().sort(function (a, b) {
            return (b.allocated_amount - b.spent) - (a.allocated_amount - a.spent);
        });

        for (var i = 0; i < sorted.length; i++) {
            if (remaining <= 0) break;

            var c = sorted[i];
            var headroom = c.allocated_amount - c.spent;
            var take = Math.min(headroom, remaining);

            if (take > 0) {
                reductions[c.id] = take;
                remaining -= take;
            }
        }

        return reductions;
    }

    function aiWeightedReductions(mutable, deficit, priorities) {
        if (!mutable.length || deficit <= 0) return {};

        var weights = {};
        mutable.forEach(function (c) {
            var priority = (priorities && priorities[c.id]) || 3;
            priority = Math.max(1, Math.min(5, priority));
            weights[c.id] = 6 - priority;
        });

        var totalWeight = Object.values(weights).reduce(function (a, b) { return a + b; }, 0);
        if (totalWeight <= 0) return proportionalReductions(mutable, deficit);

        var reductions = {};
        mutable.forEach(function (c) {
            var share = weights[c.id] / totalWeight;
            var headroom = c.allocated_amount - c.spent;
            reductions[c.id] = Math.min(deficit * share, headroom);
        });

        return reductions;
    }

    /**
     * Builds the full proposal array from local state — the frontend
     * equivalent of BudgetRebalancerService::buildPreview(). Returns null
     * only when the AI strategy is selected but priorities haven't been
     * fetched yet (caller fetches once, then calls this again from cache).
     */
    function computeProposal() {
        var overBudget = {};
        sandboxCategories.forEach(function (c) {
            if (c.spent > c.allocated_amount) overBudget[c.id] = true;
        });

        var mutable = sandboxCategories.filter(function (c) {
            return !overBudget[c.id] && !c.is_locked && !c.has_paid_expense && sandboxLockedIds.indexOf(c.id) === -1;
        });

        var deficit = sandboxCategories.reduce(function (sum, c) {
            return overBudget[c.id] ? sum + (c.spent - c.allocated_amount) : sum;
        }, 0);

        var reductions;
        if (currentStrategy === 'targeted') {
            reductions = targetedReductions(mutable, deficit);
        } else if (currentStrategy === 'ai') {
            if (!aiPriorities) return null;
            reductions = aiWeightedReductions(mutable, deficit, aiPriorities);
        } else {
            reductions = proportionalReductions(mutable, deficit);
        }

        return sandboxCategories.map(function (c) {
            var newAllocated = c.allocated_amount;

            if (overBudget[c.id]) {
                newAllocated = c.spent;
            } else if (reductions[c.id] !== undefined) {
                newAllocated = Math.max(c.spent, c.allocated_amount - reductions[c.id]);
            }

            return {
                id: c.id,
                category_name: c.category_name,
                allocated_amount: round2(c.allocated_amount),
                suggested_allocated_amount: round2(newAllocated),
                suggested_budget_percentage: totalBudget > 0 ? round2((newAllocated / totalBudget) * 100) : 0,
                spent: round2(c.spent),
                is_over_budget: !!overBudget[c.id],
                is_locked: c.is_locked,
                is_immutable: !!overBudget[c.id] || c.is_locked || c.has_paid_expense
            };
        });
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

    function renderProposal(proposal) {
        statusBox.style.display = 'none';
        latestProposal = proposal;
        renderChart(proposal);
        renderCategoryList(proposal);
        commitBtn.disabled = false;
    }

    /**
     * Recomputes and re-renders from local state. Only reaches the network
     * when the AI strategy is active and priorities haven't been fetched yet
     * for this sandbox session — every other change (strategy switch between
     * cached options, lock toggles) is instant, no API call.
     */
    function refresh() {
        var proposal = computeProposal();

        if (proposal) {
            renderProposal(proposal);
            return;
        }

        // Only "ai" with no cached priorities yet falls through to here.
        statusBox.style.display = 'block';
        statusBox.className = 'rebalance-status is-loading';
        statusBox.textContent = 'Asking the AI which categories are safest to trim...';
        commitBtn.disabled = true;

        fetch(window.rebalanceAiPrioritiesUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({})
        })
            .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    statusBox.className = 'rebalance-status is-error';
                    statusBox.textContent = (result.data && result.data.message) || 'Could not fetch AI priorities.';
                    commitBtn.disabled = true;
                    return;
                }

                aiPriorities = {};
                (result.data.priorities || []).forEach(function (p) {
                    aiPriorities[p.id] = p.priority;
                });

                // Now that priorities are cached, this and every subsequent
                // AI recalculation (lock toggles, switching back to AI) is local.
                renderProposal(computeProposal());
            })
            .catch(function () {
                statusBox.className = 'rebalance-status is-error';
                statusBox.textContent = 'Could not fetch AI priorities. Please try again.';
                commitBtn.disabled = true;
            });
    }

    function openSandbox() {
        // Deep-copy the current event's categories into local state — the
        // sandbox never reads or writes window.rebalanceCategories directly.
        sandboxCategories = baseCategories.map(function (c) {
            return {
                id: c.id,
                category_name: c.category_name,
                allocated_amount: c.allocated_amount,
                spent: c.spent,
                is_over_budget: c.is_over_budget,
                is_locked: c.is_locked,
                has_paid_expense: c.has_paid_expense
            };
        });

        sandboxLockedIds = sandboxCategories.filter(function (c) { return c.is_locked; }).map(function (c) { return c.id; });
        currentStrategy = 'proportional';
        aiPriorities = null;

        var proportionalRadio = strategyToggles.querySelector('input[value="proportional"]');
        if (proportionalRadio) proportionalRadio.checked = true;

        renderBanner();
        refresh();
    }

    openBtns.forEach(function (btn) {
        btn.addEventListener('click', openSandbox);
    });

    strategyToggles.addEventListener('change', function (e) {
        if (e.target.name !== 'rebalance_strategy') return;
        currentStrategy = e.target.value;
        refresh();
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

        refresh();
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
