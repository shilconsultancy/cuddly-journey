<?php
// shops/index.php (The New Combined Dashboard)

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Shops', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Shops Dashboard - BizManager";

// --- Data Scope Logic ---
$user_role_id = $_SESSION['user_role_id'] ?? 0;
$is_super_admin = ($user_role_id == 1);
$has_global_scope = ($_SESSION['data_scope'] ?? 'Local') === 'Global';
$user_location_id = $_SESSION['location_id'] ?? null;

// A user has a global view if they are a super admin OR their scope is set to Global.
$can_view_global = $is_super_admin || $has_global_scope;

// Determine which view to show
$show_global_view = $can_view_global && !isset($_GET['location_id']);

// If the user has local scope and no location, they can't see anything.
if (!$can_view_global && !$user_location_id) {
    die('<div class="glass-card p-8 text-center">You are not assigned to a specific location. Please contact an administrator.</div>');
}

// For Global users, fetch all shop locations for the comparative table
if ($show_global_view) {
    $shops_result = $conn->query("
        SELECT 
            l.id, l.location_name,
            (SELECT COALESCE(SUM(so.total_amount), 0) 
             FROM scs_sales_orders so 
             WHERE so.location_id = l.id AND so.order_date >= DATE_FORMAT(NOW(), '%Y-%m-01')) as month_sales,
            (SELECT COALESCE(SUM(inv.quantity * p.cost_price), 0) 
             FROM scs_inventory inv 
             JOIN scs_products p ON inv.product_id = p.id 
             WHERE inv.location_id = l.id) as stock_value
        FROM scs_locations l
        WHERE l.location_type = 'Shop'
        ORDER BY l.location_name ASC
    ");
} else {
    // For Local users OR Global users drilling down
    $display_location_id = $_GET['location_id'] ?? $user_location_id;

    // Security: If a local user tries to view a different location via URL
    if (!$can_view_global && $display_location_id != $user_location_id) {
        die('<div class="glass-card p-8 text-center">You do not have permission to view this location.</div>');
    }

    if ($display_location_id) {
        $stmt = $conn->prepare("SELECT location_name FROM scs_locations WHERE id = ?");
        $stmt->bind_param("i", $display_location_id);
        $stmt->execute();
        $location_details = $stmt->get_result()->fetch_assoc();
    } else {
        die('<div class="glass-card p-8 text-center">Could not determine which location to display.</div>');
    }
}
?>

<title><?php echo htmlspecialchars($page_title); ?></title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<?php if ($show_global_view): ?>
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-gray-800">All Shops Overview</h2>
        <a href="../dashboard.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">&larr; Back to Dashboard</a>
    </div>

    <div class="glass-card p-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                    <tr>
                        <th class="px-6 py-3">Shop Name</th>
                        <th class="px-6 py-3 text-right">Sales (This Month)</th>
                        <th class="px-6 py-3 text-right">Stock Valuation</th>
                        <th class="px-6 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($shop = $shops_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4 font-bold"><?php echo htmlspecialchars($shop['location_name']); ?></td>
                        <td class="px-6 py-4 text-right font-semibold"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($shop['month_sales'], 2)); ?></td>
                        <td class="px-6 py-4 text-right"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($shop['stock_value'], 2)); ?></td>
                        <td class="px-6 py-4 text-center">
                            <a href="?location_id=<?php echo $shop['id']; ?>" class="font-medium text-indigo-600 hover:underline">View Dashboard</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: 
    // Set default dates for the filter
    $start_date_filter = $_GET['start_date'] ?? date('Y-m-01');
    $end_date_filter = $_GET['end_date'] ?? date('Y-m-t');
?>
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
        <div>
            <h2 class="text-2xl font-semibold text-gray-800">Shop Dashboard: <span class="text-indigo-600"><?php echo htmlspecialchars($location_details['location_name']); ?></span></h2>
            <p class="text-gray-600 mt-1">Performance overview for the selected period.</p>
        </div>
        <div>
            <?php if ($can_view_global): ?>
                <a href="index.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors mr-2">&larr; Back to All Shops</a>
            <?php endif; ?>
            <a href="../dashboard.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">&larr; Back to Dashboard</a>
        </div>
    </div>
    
    <div class="glass-card p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <input type="hidden" id="location_id_input" value="<?php echo $display_location_id; ?>">
            <div>
                 <label for="start_date" class="block text-sm font-medium text-gray-700">From</label>
                 <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date_filter); ?>" class="form-input mt-1 block w-full">
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700">To</label>
                <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date_filter); ?>" class="form-input mt-1 block w-full">
            </div>
            <div class="flex space-x-2">
                <button type="button" id="filter-btn" class="w-full inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Filter</button>
            </div>
            <div class="flex space-x-2 text-sm">
                 <button type="button" class="date-preset-btn w-full py-2 px-2 rounded-md bg-gray-200 hover:bg-gray-300" data-range="today">Today</button>
                 <button type="button" class="date-preset-btn w-full py-2 px-2 rounded-md bg-gray-200 hover:bg-gray-300" data-range="week">This Week</button>
                 <button type="button" class="date-preset-btn w-full py-2 px-2 rounded-md bg-gray-200 hover:bg-gray-300" data-range="month">This Month</button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="glass-card p-6"><h3 class="text-sm font-medium text-gray-500">Revenue</h3><p id="kpi-revenue" class="mt-1 text-3xl font-semibold text-gray-900">...</p></div>
        <div class="glass-card p-6"><h3 class="text-sm font-medium text-gray-500">Gross Profit</h3><p id="kpi-profit" class="mt-1 text-3xl font-semibold text-gray-900">...</p></div>
        <div class="glass-card p-6"><h3 class="text-sm font-medium text-gray-500">Transactions</h3><p id="kpi-transactions" class="mt-1 text-3xl font-semibold text-gray-900">...</p></div>
        <div class="glass-card p-6"><h3 class="text-sm font-medium text-gray-500">Active Staff (Today)</h3><p id="kpi-staff" class="mt-1 text-3xl font-semibold text-gray-900">...</p></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-8 mb-8">
        <div class="lg:col-span-3 glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Sales Trend</h3>
            <canvas id="salesTrendChart"></canvas>
        </div>
        <div class="lg:col-span-2 glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Methods</h3>
            <canvas id="paymentMethodsChart"></canvas>
        </div>
    </div>


    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Sales (Latest 5)</h3>
            <div id="recent-sales-container" class="space-y-3"></div>
        </div>
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Selling Products (in Period)</h3>
            <div id="top-products-container" class="space-y-3"></div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const locationId = document.getElementById('location_id_input').value;
        const currencySymbol = '<?php echo $app_config['currency_symbol'] ?? '$'; ?>';
        let salesChart, paymentsChart;
        
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        const filterButton = document.getElementById('filter-btn');

        function fetchAllData() {
            const startDate = startDateInput.value;
            const endDate = endDateInput.value;
            
            // Update URL for persistence
            const newUrl = new URL(window.location.href);
            newUrl.searchParams.set('location_id', locationId);
            newUrl.searchParams.set('start_date', startDate);
            newUrl.searchParams.set('end_date', endDate);
            window.history.pushState({ path: newUrl.href }, '', newUrl.href);

            fetchDashboardData(startDate, endDate);
            fetchChartData(startDate, endDate);
        }

        function fetchDashboardData(startDate, endDate) {
            fetch(`../api/get-shop-dashboard-data.php?location_id=${locationId}&start_date=${startDate}&end_date=${endDate}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) { console.error('API Error:', data.error); return; }
                    
                    document.getElementById('kpi-revenue').textContent = currencySymbol + parseFloat(data.kpi.revenue_today).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    document.getElementById('kpi-profit').textContent = currencySymbol + parseFloat(data.kpi.profit_today).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    document.getElementById('kpi-transactions').textContent = data.kpi.transactions_today;
                    document.getElementById('kpi-staff').textContent = data.kpi.active_staff;

                    const salesContainer = document.getElementById('recent-sales-container');
                    salesContainer.innerHTML = '';
                    if (data.live_data.recent_sales.length > 0) {
                        data.live_data.recent_sales.forEach(sale => {
                            const date = new Date(sale.created_at);
                            const time = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                            salesContainer.innerHTML += `<div class="flex justify-between items-center text-sm"><span>${sale.invoice_number} <span class="text-gray-500 text-xs">@ ${time}</span></span><span class="font-semibold">${currencySymbol}${parseFloat(sale.total_amount).toFixed(2)}</span></div>`;
                        });
                    } else {
                        salesContainer.innerHTML = '<p class="text-center text-gray-500">No recent sales found.</p>';
                    }

                    const productsContainer = document.getElementById('top-products-container');
                    productsContainer.innerHTML = '';
                     if (data.live_data.top_products_today.length > 0) {
                        data.live_data.top_products_today.forEach(product => {
                            productsContainer.innerHTML += `<div class="flex justify-between items-center text-sm"><span>${product.product_name}</span><span class="font-semibold">${product.total_quantity} units</span></div>`;
                        });
                    } else {
                        productsContainer.innerHTML = '<p class="text-center text-gray-500">No products sold in this period.</p>';
                    }
                })
                .catch(error => console.error('Fetch Error:', error));
        }

        function fetchChartData(startDate, endDate) {
             fetch(`../api/get-shop-chart-data.php?location_id=${locationId}&start_date=${startDate}&end_date=${endDate}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) { console.error('Chart API Error:', data.error); return; }
                    renderSalesTrendChart(data.sales_trend);
                    renderPaymentMethodsChart(data.payment_methods);
                });
        }
        
        function renderSalesTrendChart(chartData) {
            const ctx = document.getElementById('salesTrendChart').getContext('2d');
            if(salesChart) { salesChart.destroy(); }
            salesChart = new Chart(ctx, {
                type: 'line',
                data: { labels: chartData.labels, datasets: [{ label: 'Total Sales', data: chartData.data, backgroundColor: 'rgba(79, 70, 229, 0.1)', borderColor: 'rgba(79, 70, 229, 1)', borderWidth: 2, fill: true, tension: 0.4 }] },
                options: { scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
            });
        }

        function renderPaymentMethodsChart(chartData) {
            const ctx = document.getElementById('paymentMethodsChart').getContext('2d');
             if(paymentsChart) { paymentsChart.destroy(); }
            paymentsChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{ label: 'Payments', data: chartData.data, backgroundColor: ['rgba(5, 150, 105, 0.7)', 'rgba(2, 132, 199, 0.7)', 'rgba(219, 39, 119, 0.7)', 'rgba(245, 158, 11, 0.7)'], borderColor: ['#fff'], borderWidth: 2 }]
                },
                options: { responsive: true, plugins: { legend: { position: 'top' } } }
            });
        }
        
        document.querySelectorAll('.date-preset-btn').forEach(button => {
            button.addEventListener('click', function() {
                const range = this.dataset.range;
                const today = new Date();
                let startDate, endDate;
                if (range === 'today') {
                    startDate = endDate = today.toISOString().split('T')[0];
                } else if (range === 'week') {
                    // Start of the week (Sunday)
                    const firstDayOfWeek = new Date(today.setDate(today.getDate() - today.getDay()));
                    startDate = firstDayOfWeek.toISOString().split('T')[0];
                    endDate = new Date().toISOString().split('T')[0];
                } else if (range === 'month') {
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                    endDate = new Date().toISOString().split('T')[0];
                }
                startDateInput.value = startDate;
                endDateInput.value = endDate;
                fetchAllData();
            });
        });

        filterButton.addEventListener('click', function(e) {
            e.preventDefault();
            fetchAllData();
        });

        // Initial data load
        fetchAllData();
    });
    </script>
<?php endif; ?>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>