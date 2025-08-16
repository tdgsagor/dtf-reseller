document.addEventListener('DOMContentLoaded', () => {
    const data = DtfResellerStatsData;

    function renderLineChart(id, labels, datasetLabel, dataset) {
        new Chart(document.getElementById(id), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: datasetLabel,
                    data: dataset,
                    borderColor: 'blue',
                    fill: false
                }]
            }
        });
    }

    function renderBarChart(id, labels, label, dataset) {
        new Chart(document.getElementById(id), {
            type: 'bar',
            data: {
                labels,
                datasets: [{ label, data: dataset, backgroundColor: 'teal' }]
            }
        });
    }

    function renderPieChart(id, labels, dataValues) {
        new Chart(document.getElementById(id), {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{ data: dataValues, backgroundColor: ['#28a745', '#ffc107', '#dc3545'] }]
            }
        });
    }

    function renderScatterChart(id, dataPoints) {
        new Chart(document.getElementById(id), {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'Markup vs Base Price',
                    data: dataPoints.map(p => ({ x: p.base, y: p.markup })),
                    backgroundColor: '#0073aa'
                }]
            },
            options: {
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Base Price'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Markup %'
                        }
                    }
                }
            }
        });
    }

    function renderHistogramChart(id, labels, values) {
        new Chart(document.getElementById(id), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Products per Markup Range',
                    data: values,
                    backgroundColor: '#46b450'
                }]
            },
            options: {
                indexAxis: 'x',
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Product Count'
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function renderColdProductsChart(id, productMap) {
        const now = Math.floor(Date.now() / 1000);
        const labels = [];
        const values = [];

        for (const [productId, lastSold] of Object.entries(productMap)) {
            const daysSince = Math.round((now - lastSold) / 86400);
            labels.push(`Product #${productId}`);
            values.push(daysSince);
        }

        new Chart(document.getElementById(id), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Days Since Last Sale',
                    data: values,
                    backgroundColor: '#dc3232'
                }]
            },
            options: {
                indexAxis: 'x',
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Days'
                        }
                    }
                }
            }
        });
    }

    // Example chart rendering:
    renderLineChart('revenue_by_date', Object.keys(data.revenue_by_date), 'Revenue', Object.values(data.revenue_by_date));
    renderLineChart('orders_by_date', Object.keys(data.orders_by_date), 'Orders', Object.values(data.orders_by_date));
    renderBarChart('product_revenue', Object.keys(data.product_revenue), 'Revenue', Object.values(data.product_revenue));
    renderBarChart('product_profit', Object.keys(data.product_profit), 'Profit', Object.values(data.product_profit));
    renderBarChart('reseller_revenue', Object.keys(data.reseller_revenue), 'Revenue', Object.values(data.reseller_revenue));
    renderPieChart('reseller_activity', ['Active', 'Idle', 'Inactive'], [
        data.reseller_activity.active,
        data.reseller_activity.idle,
        data.reseller_activity.inactive
    ]);

    // Scatter chart for markup per product
    renderScatterChart('markup_per_product', data.markup_per_product);

    // Histogram for markup distribution
    renderHistogramChart(
        'markup_distribution',
        Object.keys(data.markup_distribution),
        Object.values(data.markup_distribution)
    );

    // Cold products chart
    renderColdProductsChart('cold_products', data.cold_products);

});
