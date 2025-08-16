document.addEventListener('DOMContentLoaded', function () {
    if (typeof DtfResellerStatsData === 'undefined') return;

    // Revenue Over Time Line Chart
    const revCtx = document.getElementById('revenue_by_date');
    if (revCtx) {
        const revData = DtfResellerStatsData.revenue_by_date || {};
        new Chart(revCtx, {
            type: 'line',
            data: {
                labels: Object.keys(revData),
                datasets: [{
                    label: 'Revenue',
                    data: Object.values(revData),
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Reseller Activity Pie Chart
    const actCtx = document.getElementById('reseller_activity');
    if (actCtx) {
        const actData = DtfResellerStatsData.reseller_activity || {};
        new Chart(actCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Idle', 'Inactive'],
                datasets: [{
                    data: [actData.active, actData.idle, actData.inactive],
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
});
