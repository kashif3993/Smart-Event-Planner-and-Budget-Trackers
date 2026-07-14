document.addEventListener('DOMContentLoaded', function () {
    var trendCanvas = document.getElementById('progressTrendChart');
    if (!trendCanvas) return;

    var ctx = trendCanvas.getContext('2d');
    var gradient = ctx.createLinearGradient(0, 0, 0, trendCanvas.parentElement.clientHeight || 260);
    gradient.addColorStop(0, 'rgba(92, 60, 230, 0.25)');
    gradient.addColorStop(1, 'rgba(92, 60, 230, 0.02)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: window.progressTrendLabels || [],
            datasets: [{
                data: window.progressTrendData || [],
                borderColor: '#5c3ce6',
                backgroundColor: gradient,
                borderWidth: 2,
                fill: true,
                tension: 0.35,
                pointRadius: 0,
                pointHoverRadius: 4,
                pointBackgroundColor: '#5c3ce6'
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
                            return ' ' + Number(ctx.parsed.y).toLocaleString() + ' completed';
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
                    ticks: { precision: 0, color: '#9ca3af', font: { size: 11 } },
                    grid: { color: '#f3f4f6' }
                }
            }
        }
    });
});
