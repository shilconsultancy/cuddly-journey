<?php
// api/get-sales-data.php

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!check_permission('Sales', 'view')) {
    echo json_encode(['error' => 'Permission Denied']);
    exit();
}

$response = [
    'kpi' => [],
    'charts' => []
];
$currency_symbol = $app_config['currency_symbol'] ?? '$';

// --- Data Scope Filtering ---
$has_global_scope = ($_SESSION['data_scope'] ?? 'Local') === 'Global';
$user_location_id = $_SESSION['location_id'] ?? null;

// --- 1. KPI: Revenue (Last 30 Days) ---
$thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
$sql_revenue = "
    SELECT SUM(ip.amount) as total_revenue 
    FROM scs_invoice_payments ip
    JOIN scs_invoices i ON ip.invoice_id = i.id
    JOIN scs_sales_orders so ON i.sales_order_id = so.id
    WHERE ip.payment_date >= ?
";
$revenue_params = [$thirty_days_ago];
$revenue_types = 's';
if (!$has_global_scope && $user_location_id) {
    $sql_revenue .= " AND so.location_id = ? ";
    $revenue_params[] = $user_location_id;
    $revenue_types .= 'i';
}
$stmt_revenue = $conn->prepare($sql_revenue);
$stmt_revenue->bind_param($revenue_types, ...$revenue_params);
$stmt_revenue->execute();
$revenue_result = $stmt_revenue->get_result();
$revenue_row = $revenue_result->fetch_assoc();
$response['kpi']['revenue_last_30_days'] = $revenue_row['total_revenue'] ?? 0;
$stmt_revenue->close();


// --- 2. KPI: New Orders (This Month) ---
$current_month_start = date('Y-m-01');
$sql_orders = "
    SELECT COUNT(id) as new_orders 
    FROM scs_sales_orders so
    WHERE so.order_date >= ? AND so.status != 'Cancelled'
";
$order_params = [$current_month_start];
$order_types = 's';
if (!$has_global_scope && $user_location_id) {
    $sql_orders .= " AND so.location_id = ? ";
    $order_params[] = $user_location_id;
    $order_types .= 'i';
}
$stmt_orders = $conn->prepare($sql_orders);
$stmt_orders->bind_param($order_types, ...$order_params);
$stmt_orders->execute();
$response['kpi']['new_orders_this_month'] = $stmt_orders->get_result()->fetch_assoc()['new_orders'] ?? 0;
$stmt_orders->close();


// --- 3. KPI: Average Order Value ---
$sql_avg = "
    SELECT AVG(so.total_amount) as avg_value 
    FROM scs_sales_orders so
    WHERE so.status NOT IN ('Cancelled', 'Draft')
";
$avg_params = [];
$avg_types = '';
if (!$has_global_scope && $user_location_id) {
    $sql_avg .= " AND so.location_id = ? ";
    $avg_params[] = $user_location_id;
    $avg_types .= 'i';
}
$stmt_avg = $conn->prepare($sql_avg);
if (!empty($avg_params)) {
    $stmt_avg->bind_param($avg_types, ...$avg_params);
}
$stmt_avg->execute();
$response['kpi']['avg_order_value'] = $stmt_avg->get_result()->fetch_assoc()['avg_value'] ?? 0;
$stmt_avg->close();


// --- 4. Chart: Sales Over Time (Last 12 Months) ---
$sales_over_time = [];
for ($i = 11; $i >= 0; $i--) {
    $month_key = date('Y-m', strtotime("-$i months"));
    $month_label = date('M Y', strtotime("-$i months"));
    $sales_over_time[$month_key] = ['label' => $month_label, 'total' => 0];
}

$twelve_months_ago = date('Y-m-d', strtotime('-12 months'));
$sql_sales_chart = "
    SELECT DATE_FORMAT(ip.payment_date, '%Y-%m') as month, SUM(ip.amount) as monthly_total
    FROM scs_invoice_payments ip
    JOIN scs_invoices i ON ip.invoice_id = i.id
    JOIN scs_sales_orders so ON i.sales_order_id = so.id
    WHERE ip.payment_date >= ?
";
$chart_params = [$twelve_months_ago];
$chart_types = 's';
if (!$has_global_scope && $user_location_id) {
    $sql_sales_chart .= " AND so.location_id = ? ";
    $chart_params[] = $user_location_id;
    $chart_types .= 'i';
}
$sql_sales_chart .= " GROUP BY month ORDER BY month ASC";
$stmt_sales_chart = $conn->prepare($sql_sales_chart);
$stmt_sales_chart->bind_param($chart_types, ...$chart_params);
$stmt_sales_chart->execute();
$sales_result = $stmt_sales_chart->get_result();
$stmt_sales_chart->close();

if($sales_result) {
    while ($row = $sales_result->fetch_assoc()) {
        if(isset($sales_over_time[$row['month']])) {
            $sales_over_time[$row['month']]['total'] = (float)$row['monthly_total'];
        }
    }
}
$response['charts']['sales_over_time'] = [
    'labels' => array_column(array_values($sales_over_time), 'label'),
    'data' => array_column(array_values($sales_over_time), 'total')
];


// --- 5. Chart: Top 5 Selling Products (Last 90 Days) ---
$ninety_days_ago = date('Y-m-d', strtotime('-90 days'));
$sql_top_products = "
    SELECT p.product_name, SUM(soi.quantity) as total_sold
    FROM scs_sales_order_items soi
    JOIN scs_products p ON soi.product_id = p.id
    JOIN scs_sales_orders so ON soi.sales_order_id = so.id
    WHERE so.order_date >= ? AND so.status NOT IN ('Cancelled', 'Draft')
";
$top_prod_params = [$ninety_days_ago];
$top_prod_types = 's';
if (!$has_global_scope && $user_location_id) {
    $sql_top_products .= " AND so.location_id = ? ";
    $top_prod_params[] = $user_location_id;
    $top_prod_types .= 'i';
}
$sql_top_products .= " GROUP BY p.product_name ORDER BY total_sold DESC LIMIT 5";
$stmt_top_products = $conn->prepare($sql_top_products);
$stmt_top_products->bind_param($top_prod_types, ...$top_prod_params);
$stmt_top_products->execute();
$top_products_result = $stmt_top_products->get_result();
$stmt_top_products->close();

$top_products = ['labels' => [], 'data' => []];
if($top_products_result) {
    while ($row = $top_products_result->fetch_assoc()) {
        $top_products['labels'][] = $row['product_name'];
        $top_products['data'][] = (int)$row['total_sold'];
    }
}
$response['charts']['top_products'] = $top_products;


echo json_encode($response);
$conn->close();
?>