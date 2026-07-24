document.addEventListener('DOMContentLoaded', function () {
    var palette = ['#4f46e5', '#8b5cf6', '#0d9488', '#ef4444', '#f59e0b', '#06b6d4', '#ec4899', '#84cc16'];

    (window.orgGroups || []).forEach(function (g) {
        /* ── Budget vs Actual vs Forecast (per event, grouped bar) ── */
        var barCanvas = document.getElementById('orgBarChart-' + g.index);
        if (barCanvas) {
            new Chart(barCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: g.eventLabels,
                    datasets: [
                        { label: 'Budget', data: g.eventBudget, backgroundColor: '#c7d2fe' },
                        { label: 'Actual Spent', data: g.eventSpent, backgroundColor: '#4f46e5' },
                        { label: 'Forecast', data: g.eventForecast, backgroundColor: '#f59e0b' }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                        y: { beginAtZero: true, grid: { color: '#f3f4f6' } }
                    }
                }
            });
        }

        /* ── Remaining Budget (doughnut: consumed vs remaining) ── */
        var donutCanvas = document.getElementById('orgDoughnut-' + g.index);
        if (donutCanvas) {
            new Chart(donutCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Consumed (Spent + Expected)', 'Remaining'],
                    datasets: [{
                        data: [g.remainingConsumed, g.remainingLeft],
                        backgroundColor: ['#ef4444', '#10b981'],
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
                }
            });
        }

        /* ── Top Vendors by Amount Paid (horizontal bar) ── */
        var vendorCanvas = document.getElementById('orgVendorChart-' + g.index);
        if (vendorCanvas) {
            new Chart(vendorCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: g.vendorLabels,
                    datasets: [{ data: g.vendorPaid, backgroundColor: palette.slice(0, g.vendorLabels.length) }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, grid: { color: '#f3f4f6' } },
                        y: { grid: { display: false }, ticks: { font: { size: 11 } } }
                    }
                }
            });
        }

        /* ── Cash Flow (monthly actual spend vs flat budget line) ── */
        var cashCanvas = document.getElementById('orgCashflow-' + g.index);
        if (cashCanvas) {
            new Chart(cashCanvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: g.cashFlowLabels,
                    datasets: [
                        {
                            label: 'Actual Spend',
                            data: g.cashFlowActual,
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.12)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 2
                        },
                        {
                            label: 'Total Budget',
                            data: g.cashFlowBudget,
                            borderColor: '#9ca3af',
                            borderDash: [6, 4],
                            borderWidth: 1.5,
                            fill: false,
                            pointRadius: 0
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                        y: { beginAtZero: true, grid: { color: '#f3f4f6' } }
                    }
                }
            });
        }
    });

    /* ── Click-to-sort Event Financial Comparison table ── */
    document.querySelectorAll('.org-sortable-table').forEach(function (table) {
        var headers = table.querySelectorAll('th[data-sort]');
        headers.forEach(function (th, colIndex) {
            th.addEventListener('click', function () {
                var type = th.getAttribute('data-type') || 'text';
                var currentDir = th.classList.contains('sorted-asc') ? 'asc' : (th.classList.contains('sorted-desc') ? 'desc' : null);
                var nextDir = currentDir === 'asc' ? 'desc' : 'asc';

                headers.forEach(function (h) { h.classList.remove('sorted-asc', 'sorted-desc'); });
                th.classList.add(nextDir === 'asc' ? 'sorted-asc' : 'sorted-desc');

                var tbody = table.querySelector('tbody');
                var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));

                rows.sort(function (rowA, rowB) {
                    var cellA = rowA.children[colIndex].getAttribute('data-value');
                    var cellB = rowB.children[colIndex].getAttribute('data-value');

                    if (type === 'number') {
                        cellA = parseFloat(cellA) || 0;
                        cellB = parseFloat(cellB) || 0;
                        return nextDir === 'asc' ? cellA - cellB : cellB - cellA;
                    }

                    cellA = (cellA || '').toString().toLowerCase();
                    cellB = (cellB || '').toString().toLowerCase();
                    if (cellA < cellB) return nextDir === 'asc' ? -1 : 1;
                    if (cellA > cellB) return nextDir === 'asc' ? 1 : -1;
                    return 0;
                });

                rows.forEach(function (row) { tbody.appendChild(row); });
            });
        });
    });
});
