document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('contentionSandboxModal');
    var openBtns = document.querySelectorAll('[data-open-modal="contentionSandboxModal"]');
    if (!modal || !openBtns.length) return;

    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    var currency = window.contentionCurrencySymbol || '';

    var strategyToggles = document.getElementById('sandboxStrategyToggles');
    var statusBox = document.getElementById('sandboxStatus');
    var capValueEl = document.getElementById('sandboxCapValue');
    var spendValueEl = document.getElementById('sandboxSpendValue');
    var deficitValueEl = document.getElementById('sandboxDeficitValue');
    var unabsorbedValueEl = document.getElementById('sandboxUnabsorbedValue');
    var streamEl = document.getElementById('sandboxNegotiationStream');
    var skipWrap = document.getElementById('sandboxSkipWrap');
    var skipBtn = document.getElementById('sandboxSkipReveal');
    var visualizerEl = document.getElementById('sandboxVisualizer');
    var eventListEl = document.getElementById('sandboxEventList');
    var commitBtn = document.getElementById('sandboxCommitBtn');

    // Fresh per session — CR-21/CR-22: an isolated snapshot, nothing touches
    // live budgets until Commit.
    var pooledCap = 0;
    var globalDeficit = 0;
    var sandboxEvents = [];
    var currentStrategy = 'hierarchy';
    var latestProposal = null; // { concessions: [{event_id, concession, rationale, rank, score}], residual, rationaleText }
    var revealTimer = null;
    var manualOverrides = {}; // event_id -> amount (only while user is editing)

    function money(value) {
        return currency + Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
    }

    function eventById(id) {
        return sandboxEvents.find(function (e) { return e.id === id; });
    }

    function immuneIds() {
        return sandboxEvents.filter(function (e) { return e.is_immune; }).map(function (e) { return e.id; });
    }

    /* ── Client-side port of StrictHierarchyScoringService — mirrors the
       server exactly (score = weight / max(1, daysRemaining), ties broken by
       later date, headroom-capped cascade) so this strategy resolves
       instantly with zero round trips, same pattern as rebalancer.js. ── */
    function computeHierarchyProposal() {
        var eligible = sandboxEvents.filter(function (e) { return !e.is_immune; });

        var ranked = eligible.map(function (e) {
            return { event: e, score: e.weight / Math.max(1, e.days_remaining) };
        }).sort(function (a, b) {
            if (a.score !== b.score) return a.score - b.score;
            return a.event.date < b.event.date ? 1 : (a.event.date > b.event.date ? -1 : 0);
        });

        var remaining = globalDeficit;
        var concessions = [];

        ranked.forEach(function (row, index) {
            var headroom = Math.max(0, row.event.total_budget - row.event.budget_spent);
            var take = Math.max(0, Math.min(headroom, remaining));
            remaining -= take;

            concessions.push({
                event_id: row.event.id,
                concession: round2(take),
                rank: index + 1,
                score: row.score,
                rationale: take > 0
                    ? '"' + row.event.name + '" ranks #' + (index + 1) + ' in protection (score ' + row.score.toFixed(2) + ') and concedes ' + money(take) + ' of headroom.'
                    : '"' + row.event.name + '" ranks #' + (index + 1) + ' in protection but has no further headroom to give.'
            });
        });

        return {
            concessions: concessions,
            residual: round2(Math.max(0, remaining)),
            rationaleText: null
        };
    }

    function round2(v) { return Math.round((v + Number.EPSILON) * 100) / 100; }

    function renderHealthTracker() {
        var committed = sandboxEvents.reduce(function (sum, e) { return sum + Math.max(e.total_budget, e.budget_spent); }, 0);
        capValueEl.textContent = money(pooledCap);
        spendValueEl.textContent = money(committed);
        deficitValueEl.textContent = money(globalDeficit);

        var residual = latestProposal ? latestProposal.residual : globalDeficit;
        unabsorbedValueEl.textContent = money(residual);
        unabsorbedValueEl.className = 'pooled-health-value ' + (residual > 0 ? 'pooled-health-value--danger' : 'pooled-health-value--success');
    }

    function renderVisualizer() {
        if (!latestProposal) { visualizerEl.innerHTML = ''; return; }

        visualizerEl.innerHTML = latestProposal.concessions.map(function (c) {
            var event = eventById(c.event_id);
            var before = event.total_budget;
            var after = Math.max(event.budget_spent, before - c.concession);
            var maxScale = Math.max(before, after, 1);

            return '<div class="cev-row">' +
                '<div class="cev-label">' + event.name + '</div>' +
                '<div class="cev-bar-wrap">' +
                    '<div class="cev-bar-before" style="width:' + (before / maxScale * 100) + '%;"></div>' +
                    '<div class="cev-bar-after" style="width:' + (after / maxScale * 100) + '%;"></div>' +
                '</div>' +
                '<div class="cev-amount">' + money(before) + ' &rarr; ' + money(after) + '</div>' +
            '</div>';
        }).join('');
    }

    function renderEventList() {
        eventListEl.innerHTML = sandboxEvents.map(function (event) {
            var c = latestProposal ? latestProposal.concessions.find(function (x) { return x.event_id === event.id; }) : null;
            var concessionAmount = c ? c.concession : 0;
            var manual = manualOverrides.hasOwnProperty(event.id);

            var rankBadge = (c && c.rank) ? '<span class="sandbox-rank-badge">Rank #' + c.rank + '</span>' : '';

            return '<div class="sandbox-event-row ' + (event.is_immune ? 'is-immune' : '') + '" data-event-id="' + event.id + '">' +
                '<div class="sandbox-event-row-top">' +
                    '<div>' +
                        '<span class="sandbox-event-name">' + event.name + '</span>' + rankBadge +
                        '<div class="sandbox-event-meta">' + event.type + ' &middot; ' + event.days_remaining + ' days out &middot; ' +
                            'budget ' + money(event.total_budget) + ', spent ' + money(event.budget_spent) + '</div>' +
                    '</div>' +
                    '<label class="immunity-lock-toggle">' +
                        '<input type="checkbox" data-immunity-toggle="' + event.id + '" ' + (event.is_immune ? 'checked' : '') + '>' +
                        '<i class="fas ' + (event.is_immune ? 'fa-shield-halved' : 'fa-shield') + '"></i> Immune' +
                    '</label>' +
                '</div>' +
                (event.is_immune ? '' :
                    '<div class="sandbox-concession-amounts">' +
                        '<span>Concession:</span>' +
                        '<input type="number" step="0.01" min="0" max="' + Math.max(0, event.total_budget - event.budget_spent) + '" ' +
                            'data-concession-input="' + event.id + '" value="' + concessionAmount.toFixed(2) + '">' +
                        (manual ? '<span class="chip chip-warning">Manually amended</span>' : '') +
                    '</div>' +
                    (c && c.rationale ? '<p style="font-size:12.5px;color:var(--text-muted);margin-top:6px;">' + c.rationale + '</p>' : '')
                ) +
            '</div>';
        }).join('');
    }

    function updateCommitAvailability() {
        var hasCommitUrl = !!window.contentionCommitUrl;
        var hasProposal = !!latestProposal && latestProposal.concessions.length > 0;
        commitBtn.disabled = !hasCommitUrl || !hasProposal;
    }

    function renderAll() {
        renderHealthTracker();
        renderVisualizer();
        renderEventList();
        updateCommitAvailability();
    }

    function applyManualOverrides() {
        if (!latestProposal) return;

        var recalculated = 0;
        latestProposal.concessions.forEach(function (c) {
            if (manualOverrides.hasOwnProperty(c.event_id)) {
                c.concession = manualOverrides[c.event_id];
            }
            recalculated += c.concession;
        });

        latestProposal.residual = round2(Math.max(0, globalDeficit - recalculated));
    }

    function revealStream(rationaleLines, onDone) {
        streamEl.style.display = 'block';
        streamEl.innerHTML = '';
        skipWrap.style.display = rationaleLines.length ? 'block' : 'none';

        var i = 0;
        function step() {
            if (i >= rationaleLines.length) {
                clearInterval(revealTimer);
                revealTimer = null;
                skipWrap.style.display = 'none';
                if (onDone) onDone();
                return;
            }

            var line = document.createElement('div');
            line.className = 'negotiation-line';
            line.innerHTML = rationaleLines[i];
            streamEl.appendChild(line);
            streamEl.scrollTop = streamEl.scrollHeight;
            i++;
        }

        step();
        revealTimer = setInterval(step, 450);

        skipBtn.onclick = function () {
            while (i < rationaleLines.length) step();
        };
    }

    function runHierarchy() {
        manualOverrides = {};
        latestProposal = computeHierarchyProposal();

        var lines = latestProposal.concessions.map(function (c) {
            return '<strong>' + eventById(c.event_id).name + '</strong> — ' + c.rationale;
        });

        streamEl.style.display = 'none';
        skipWrap.style.display = 'none';
        renderAll();

        if (lines.length) {
            revealStream(lines, renderAll);
        }
    }

    function runAiNegotiation() {
        if (!window.contentionNegotiateUrl) {
            statusBox.style.display = 'block';
            statusBox.className = 'rebalance-status is-warning';
            statusBox.textContent = 'Multi-Agent Negotiation is not available yet.';
            return;
        }

        manualOverrides = {};
        statusBox.style.display = 'block';
        statusBox.className = 'rebalance-status is-loading';
        statusBox.textContent = 'Negotiating with each event\'s advocate...';
        streamEl.style.display = 'none';
        commitBtn.disabled = true;

        fetch(window.contentionNegotiateUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ immune_event_ids: immuneIds() })
        })
            .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    statusBox.className = 'rebalance-status is-error';
                    statusBox.textContent = (result.data && result.data.message) || 'Multi-Agent Negotiation failed.';

                    if (result.data && result.data.fell_back) {
                        // CR-51 — automatic fallback to Strict Hierarchy on AI failure.
                        var hierarchyRadio = strategyToggles.querySelector('input[value="hierarchy"]');
                        if (hierarchyRadio) hierarchyRadio.checked = true;
                        currentStrategy = 'hierarchy';
                        runHierarchy();
                    }
                    return;
                }

                statusBox.style.display = 'none';

                latestProposal = {
                    concessions: result.data.concessions.map(function (c) {
                        return { event_id: c.event_id, concession: c.concession_amount, rationale: c.rationale, rank: null, score: null };
                    }),
                    residual: result.data.residual || 0,
                    rationaleText: null
                };

                var lines = latestProposal.concessions.map(function (c) {
                    return '<strong>' + eventById(c.event_id).name + '</strong> — ' + c.rationale;
                });

                renderAll();
                if (lines.length) revealStream(lines, renderAll);
            })
            .catch(function () {
                statusBox.className = 'rebalance-status is-error';
                statusBox.textContent = 'Could not reach the negotiation service. Please try again.';
            });
    }

    function refresh() {
        if (revealTimer) { clearInterval(revealTimer); revealTimer = null; }
        statusBox.style.display = 'none';

        if (currentStrategy === 'ai') {
            runAiNegotiation();
        } else {
            runHierarchy();
        }
    }

    function loadSnapshot() {
        eventListEl.innerHTML = '';
        statusBox.style.display = 'block';
        statusBox.className = 'rebalance-status is-loading';
        statusBox.textContent = 'Loading contention snapshot...';
        commitBtn.disabled = true;

        fetch(window.contentionSnapshotUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    statusBox.className = 'rebalance-status is-error';
                    statusBox.textContent = (result.data && result.data.message) || 'Could not load the contention snapshot.';
                    return;
                }

                pooledCap = result.data.pooled_budget_cap;
                globalDeficit = result.data.global_deficit;
                sandboxEvents = result.data.events.map(function (e) {
                    return Object.assign({}, e, { is_immune: false });
                });
                currentStrategy = 'hierarchy';
                var hierarchyRadio = strategyToggles.querySelector('input[value="hierarchy"]');
                if (hierarchyRadio) hierarchyRadio.checked = true;

                refresh();
            })
            .catch(function () {
                statusBox.className = 'rebalance-status is-error';
                statusBox.textContent = 'Could not load the contention snapshot. Please try again.';
            });
    }

    openBtns.forEach(function (btn) {
        btn.addEventListener('click', loadSnapshot);
    });

    strategyToggles.addEventListener('change', function (e) {
        if (e.target.name !== 'sandbox_strategy') return;
        currentStrategy = e.target.value;
        refresh();
    });

    eventListEl.addEventListener('change', function (e) {
        var immunityId = e.target.getAttribute('data-immunity-toggle');
        if (immunityId) {
            var event = eventById(Number(immunityId));
            if (event) event.is_immune = e.target.checked;

            // CR-57 — never allow every eligible event to be locked at once.
            var eligibleCount = sandboxEvents.length;
            var immuneCount = immuneIds().length;
            if (immuneCount >= eligibleCount) {
                event.is_immune = false;
                statusBox.style.display = 'block';
                statusBox.className = 'rebalance-status is-warning';
                statusBox.textContent = 'At least one event must remain eligible to concede — release another lock first.';
                setTimeout(function () { statusBox.style.display = 'none'; }, 3000);
                refresh();
                return;
            }

            refresh();
            return;
        }

        var concessionId = e.target.getAttribute('data-concession-input');
        if (concessionId && latestProposal) {
            var amount = Math.max(0, Number(e.target.value) || 0);
            var event2 = eventById(Number(concessionId));
            var headroom = Math.max(0, event2.total_budget - event2.budget_spent);

            if (amount > headroom) {
                amount = headroom;
                e.target.value = amount.toFixed(2);
            }

            manualOverrides[concessionId] = amount;
            applyManualOverrides();
            renderAll();
        }
    });

    commitBtn.addEventListener('click', function () {
        if (!latestProposal || !window.contentionCommitUrl) return;

        commitBtn.disabled = true;
        statusBox.style.display = 'block';
        statusBox.className = 'rebalance-status is-loading';
        statusBox.textContent = 'Committing allocation across all affected events...';

        fetch(window.contentionCommitUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                strategy: currentStrategy === 'ai' ? 'Multi-Agent Negotiation' : 'Strict Hierarchy',
                concessions: latestProposal.concessions.map(function (c) {
                    return { event_id: c.event_id, concession_amount: c.concession, rationale: c.rationale || '' };
                }),
                manually_amended: Object.keys(manualOverrides).length > 0
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
                    statusBox.textContent = (result.data && result.data.message) || 'Could not commit the allocation.';
                    commitBtn.disabled = false;
                }
            })
            .catch(function () {
                statusBox.className = 'rebalance-status is-error';
                statusBox.textContent = 'Could not commit the allocation. Please try again.';
                commitBtn.disabled = false;
            });
    });
});
