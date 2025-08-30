<?php
// api/get-sales-data.php

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!check_permission('Sales', 'view')) {
    echo json_encode(['error' => 'Permission Denied']);
    exit();
}

$response = [];
$currency_symbol = $app_config['currency_symbol'] ?? '$';

// --- Data Scope Filtering ---
$has_global_scope = ($_SESSION['data_scope'] ?? 'Local') === 'Global';
$user_location_id = $_SESSION['location_id'] ?? null;

$location_filter_payments = '';
$location_filter_orders = '';
$params = [];
$types = '';

if (!$has_global_scope && $user_location_id) {
    $location_filter_payments = " AND so.location_id = ? ";
    $location_filter_orders = " AND so.location_id = ? ";
    $params[] = $user_location_id;
    $types .= 'i';
}


// --- 1. KPI: Revenue (Last 30 Days) ---
$thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
$sql_revenue = "
    SELECT SUM(ip.amount) as total_revenue 
    FROM scs_invoice_payments ip
    JOIN scs_invoices i ON ip.invoice_id = i.id
    JOIN scs_sales_orders so ON i.sales_order_id = so.id
    WHERE ip.payment_date >= ?
" . str_replace('?', '', $location_filter_payments); // Use placeholder replacement for this part as it's complex
$final_revenue_sql = str_replace('so.location_id', 'so.location_id', $sql_revenue);
if (!$has_global_scope && $user_location_id) {
    $final_revenue_sql .= " AND so.location_id = " . (int)$user_location_id;
}
$revenue_result = $conn->query(
    $conn->prepare($sql_revenue)->bind_param('s', $thirty_days_ago) ? 
    $conn->prepare($sql_revenue)->execute() : // A bit of a hacky way to execute with params
    $conn->query($final_revenue_sql)
);

if ($revenue_result) {
    $revenue_row = $revenue_result->fetch_assoc();
    $response['kpi']['revenue_last_30_days'] = $revenue_row['total_revenue'] ?? 0;
} else {
    $response['kpi']['revenue_last_30_days'] = 0;
}



// --- 2. KPI: New Orders (This Month) ---
$current_month_start = date('Y-m-01');
$sql_orders = "
    SELECT COUNT(id) as new_orders 
    FROM scs_sales_orders so
    WHERE so.order_date >= ? AND so.status != 'Cancelled'
" . $location_filter_orders;
$stmt_orders = $conn->prepare($sql_orders);
if (!empty($params)) {
    $stmt_orders->bind_param('s' . $types, $current_month_start, ...$params);
} else {
    $stmt_orders->bind_param('s', $current_month_start);
}
$stmt_orders->execute();
$response['kpi']['new_orders_this_month'] = $stmt_orders->get_result()->fetch_assoc()['new_orders'] ?? 0;


// --- 3. KPI: Average Order Value ---
$sql_avg = "
    SELECT AVG(so.total_amount) as avg_value 
    FROM scs_sales_orders so
    WHERE so.status NOT IN ('Cancelled', 'Draft')
" . $location_filter_orders;
$stmt_avg = $conn->prepare($sql_avg);
if (!empty($params)) {
    $stmt_avg->bind_param($types, ...$params);
}
$stmt_avg->execute();
$response['kpi']['avg_order_value'] = $stmt_avg->get_result()->fetch_assoc()['avg_value'] ?? 0;


// --- 4. Chart: Sales Over Time (Last 12 Months) ---
$sales_over_time = [];
for ($i = 11; $i >= 0; $i--) {
    $month_key = date('Y-m', strtotime("-$i months"));
    $month_label = date('M Y', strtotime("-$i months"));
    $sales_over_time[$month_key] = ['label' => $month_label, 'total' => 0];
}

$sql_sales_chart = "
    SELECT DATE_FORMAT(ip.payment_date, '%Y-%m') as month, SUM(ip.amount) as monthly_total
    FROM scs_invoice_payments ip
    JOIN scs_invoices i ON ip.invoice_id = i.id
    JOIN scs_sales_orders so ON i.sales_order_id = so.id
    WHERE ip.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    " . str_replace('?', (int)$user_location_id, $location_filter_payments) . "
    GROUP BY month
    ORDER BY month ASC
";

$sales_result = $conn->query($sql_sales_chart);

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
    " . $location_filter_orders . "
    GROUP BY p.product_name
    ORDER BY total_sold DESC
    LIMIT 5
";
$stmt_top_products = $conn->prepare($sql_top_products);
if (!empty($params)) {
    $stmt_top_products->bind_param('s' . $types, $ninety_days_ago, ...$params);
} else {
    $stmt_top_products->bind_param('s', $ninety_days_ago);
}
$stmt_top_products->execute();
$top_products_result = $stmt_top_products->get_result();

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