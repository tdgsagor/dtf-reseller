document.addEventListener('DOMContentLoaded', function () {
    const data = window.DtfResellerStatsData || {};

    // Line Chart - Revenue Over Time
    const revenueCtx = document.getElementById('revenue_by_date');
    if (revenueCtx && data.revenue_by_date) {
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: Object.keys(data.revenue_by_date),
                datasets: [{
                    label: 'Revenue',
                    data: Object.values(data.revenue_by_date),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { x: { title: { display: true, text: 'Date' } } }
            }
        });
    }

    // Bar Chart - Top Products
    const productRevenue = data.product_revenue || {};
    const topProducts = Object.entries(productRevenue).sort((a, b) => b[1] - a[1]).slice(0, 5);
    const [labels, values] = [topProducts.map(p => `#${p[0]}`), topProducts.map(p => p[1])];

    console.log({labels, values});

    const productChartCtx = document.getElementById('product_revenue');
    if (productChartCtx && labels.length > 0) {
        new Chart(productChartCtx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Revenue',
                    data: values,
                    backgroundColor: '#10b981'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true },
                    x: { title: { display: true, text: 'Product ID' } }
                }
            }
        });
    }
});
