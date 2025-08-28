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

// --- 1. KPI: Revenue (Last 30 Days) ---
$thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
$revenue_result = $conn->query("
    SELECT SUM(amount) as total_revenue 
    FROM scs_invoice_payments 
    WHERE payment_date >= '$thirty_days_ago'
");
$response['kpi']['revenue_last_30_days'] = $revenue_result->fetch_assoc()['total_revenue'] ?? 0;

// --- 2. KPI: New Orders (This Month) ---
$current_month_start = date('Y-m-01');
$orders_result = $conn->query("
    SELECT COUNT(id) as new_orders 
    FROM scs_sales_orders 
    WHERE order_date >= '$current_month_start' AND status != 'Cancelled'
");
$response['kpi']['new_orders_this_month'] = $orders_result->fetch_assoc()['new_orders'] ?? 0;

// --- 3. KPI: Average Order Value ---
$avg_order_result = $conn->query("
    SELECT AVG(total_amount) as avg_value 
    FROM scs_sales_orders 
    WHERE status NOT IN ('Cancelled', 'Draft')
");
$response['kpi']['avg_order_value'] = $avg_order_result->fetch_assoc()['avg_value'] ?? 0;

// --- 4. Chart: Sales Over Time (Last 12 Months) ---
$sales_over_time = [];
for ($i = 11; $i >= 0; $i--) {
    $month_key = date('Y-m', strtotime("-$i months"));
    $month_label = date('M Y', strtotime("-$i months"));
    $sales_over_time[$month_key] = ['label' => $month_label, 'total' => 0];
}

$sales_result = $conn->query("
    SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as monthly_total
    FROM scs_invoice_payments
    WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month ASC
");
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
$top_products_result = $conn->query("
    SELECT p.product_name, SUM(soi.quantity) as total_sold
    FROM scs_sales_order_items soi
    JOIN scs_products p ON soi.product_id = p.id
    JOIN scs_sales_orders so ON soi.sales_order_id = so.id
    WHERE so.order_date >= '$ninety_days_ago' AND so.status NOT IN ('Cancelled', 'Draft')
    GROUP BY p.product_name
    ORDER BY total_sold DESC
    LIMIT 5
");
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