<?php
// reports/sales_overview.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Reports', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to view the sales dashboard.</div>');
}

$page_title = "Sales Overview - BizManager";
?>

<title><?php echo htmlspecialchars($page_title); ?></title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Sales Overview</h2>
        <p class="text-gray-600 mt-1">A real-time overview of your sales performance.</p>
    </div>
    <a href="sales_reports.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Sales Reports
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="glass-card p-6">
        <h3 class="text-sm font-medium text-gray-500">Revenue (Last 30 Days)</h3>
        <p id="kpi-revenue" class="mt-1 text-3xl font-semibold text-gray-900">Loading...</p>
    </div>
    <div class="glass-card p-6">
        <h3 class="text-sm font-medium text-gray-500">New Orders (This Month)</h3>
        <p id="kpi-orders" class="mt-1 text-3xl font-semibold text-gray-900">Loading...</p>
    </div>
    <div class="glass-card p-6">
        <h3 class="text-sm font-medium text-gray-500">Average Order Value</h3>
        <p id="kpi-avg-value" class="mt-1 text-3xl font-semibold text-gray-900">Loading...</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div class="glass-card p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Sales Over Time (Last 12 Months)</h3>
        <canvas id="salesOverTimeChart"></canvas>
    </div>
    <div class="glass-card p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Selling Products (Last 90 Days)</h3>
        <canvas id="topProductsChart"></canvas>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const currencySymbol = '<?php echo $app_config['currency_symbol'] ?? '$'; ?>';

    // Fetch data from the API
    fetch('../api/get-sales-data.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Error fetching dashboard data:', data.error);
                return;
            }

            // Populate KPI Cards
            document.getElementById('kpi-revenue').textContent = currencySymbol + parseFloat(data.kpi.revenue_last_30_days).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('kpi-orders').textContent = data.kpi.new_orders_this_month;
            document.getElementById('kpi-avg-value').textContent = currencySymbol + parseFloat(data.kpi.avg_order_value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // Render Sales Over Time Chart (Line Chart)
            const salesCtx = document.getElementById('salesOverTimeChart').getContext('2d');
            new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: data.charts.sales_over_time.labels,
                    datasets: [{
                        label: 'Total Sales',
                        data: data.charts.sales_over_time.data,
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        borderColor: 'rgba(79, 70, 229, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    scales: { y: { beginAtZero: true } },
                    plugins: { legend: { display: false } }
                }
            });

            // Render Top Products Chart (Bar Chart)
            const productsCtx = document.getElementById('topProductsChart').getContext('2d');
            new Chart(productsCtx, {
                type: 'bar',
                data: {
                    labels: data.charts.top_products.labels,
                    datasets: [{
                        label: 'Units Sold',
                        data: data.charts.top_products.data,
                        backgroundColor: [
                            'rgba(5, 150, 105, 0.6)',
                            'rgba(2, 132, 199, 0.6)',
                            'rgba(219, 39, 119, 0.6)',
                            'rgba(245, 158, 11, 0.6)',
                            'rgba(107, 114, 128, 0.6)'
                        ],
                        borderColor: [
                            'rgba(5, 150, 105, 1)',
                            'rgba(2, 132, 199, 1)',
                            'rgba(219, 39, 119, 1)',
                            'rgba(245, 158, 11, 1)',
                            'rgba(107, 114, 128, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y', // Horizontal bars
                    scales: { x: { beginAtZero: true } },
                    plugins: { legend: { display: false } }
                }
            });
        })
        .catch(error => console.error('Error fetching or parsing dashboard data:', error));
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>