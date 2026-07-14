document.addEventListener('DOMContentLoaded', function () {
    var palette = ['#4f46e5', '#8b5cf6', '#0d9488', '#ef4444', '#f59e0b', '#06b6d4'];

    /* ── Allocation by Category (donut) ── */
    var donutCanvas = document.getElementById('allocationChart');
    if (donutCanvas) {
        var labels = window.budgetCategoryLabels || [];
        var data = window.budgetCategoryAllocations || [];

        new Chart(donutCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: palette.slice(0, labels.length),
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + ctx.label + ': ' + Number(ctx.parsed).toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    /* ── Spending Trend (area line) ── */
    var trendCanvas = document.getElementById('trendChart');
    if (trendCanvas) {
        var ctx = trendCanvas.getContext('2d');
        var gradient = ctx.createLinearGradient(0, 0, 0, trendCanvas.parentElement.clientHeight || 260);
        gradient.addColorStop(0, 'rgba(79, 70, 229, 0.25)');
        gradient.addColorStop(1, 'rgba(79, 70, 229, 0.02)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: window.budgetTrendLabels || [],
                datasets: [{
                    data: window.budgetTrendData || [],
                    borderColor: '#4f46e5',
                    backgroundColor: gradient,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointBackgroundColor: '#4f46e5'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + Number(ctx.parsed.y).toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxTicksLimit: 8, color: '#9ca3af', font: { size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f3f4f6' },
                        ticks: { color: '#9ca3af', font: { size: 11 } }
                    }
                }
            }
        });
    }
});
