<?php
// api/get-shop-chart-data.php

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!check_permission('Shops', 'view')) {
    echo json_encode(['error' => 'Permission Denied']);
    exit();
}

$location_id = $_GET['location_id'] ?? 0;
if (!$location_id) {
    echo json_encode(['error' => 'No location ID provided.']);
    exit();
}

// --- Handle Date Range ---
$start_date_str = $_GET['start_date'] ?? date('Y-m-01');
$end_date_str = $_GET['end_date'] ?? date('Y-m-t');

$start_datetime = $start_date_str . ' 00:00:00';
$end_datetime = $end_date_str . ' 23:59:59';

$response = [
    'sales_trend' => [],
    'payment_methods' => []
];

// --- 1. Sales Trend Data (Dynamic Grouping) ---
$date1 = new DateTime($start_date_str);
$date2 = new DateTime($end_date_str);
$interval = $date1->diff($date2);
$days_diff = $interval->days;

if ($days_diff === 0) { // Single Day View
    $group_by = "HOUR(ps.created_at)";
    $labels = [];
    $data = array_fill(0, 24, 0);
    for ($i = 0; $i < 24; $i++) {
        $labels[] = date('ha', strtotime("$i:00")); // e.g., 8am, 1pm
    }
} else { // Multi-Day View
    $group_by = "DATE(ps.created_at)";
    $labels = [];
    $data_map = [];
    $current_date = clone $date1;
    while ($current_date <= $date2) {
        $date_key = $current_date->format('Y-m-d');
        $labels[] = $current_date->format('M d');
        $data_map[$date_key] = 0;
        $current_date->modify('+1 day');
    }
}

$stmt_sales = $conn->prepare("
    SELECT 
        $group_by as time_group,
        COALESCE(SUM(i.total_amount), 0) as total_revenue
    FROM scs_pos_sales ps
    JOIN scs_invoices i ON ps.invoice_id = i.id
    WHERE ps.created_at BETWEEN ? AND ?
    AND i.sales_order_id IN (SELECT id FROM scs_sales_orders WHERE location_id = ?)
    GROUP BY time_group
");
$stmt_sales->bind_param("ssi", $start_datetime, $end_datetime, $location_id);
$stmt_sales->execute();
$sales_result = $stmt_sales->get_result();

while ($row = $sales_result->fetch_assoc()) {
    if ($days_diff === 0) {
        $data[(int)$row['time_group']] = (float)$row['total_revenue'];
    } else {
        $data_map[$row['time_group']] = (float)$row['total_revenue'];
    }
}

$response['sales_trend']['labels'] = $labels;
$response['sales_trend']['data'] = ($days_diff === 0) ? $data : array_values($data_map);
$response['sales_trend']['view_type'] = ($days_diff === 0) ? 'hourly' : 'daily';


// --- 2. Payment Method Breakdown Data ---
$stmt_payments = $conn->prepare("
    SELECT 
        ps.payment_method, 
        COALESCE(SUM(i.total_amount), 0) as total_amount
    FROM scs_pos_sales ps
    JOIN scs_invoices i ON ps.invoice_id = i.id
    WHERE ps.created_at BETWEEN ? AND ?
    AND i.sales_order_id IN (SELECT id FROM scs_sales_orders WHERE location_id = ?)
    GROUP BY ps.payment_method
");
$stmt_payments->bind_param("ssi", $start_datetime, $end_datetime, $location_id);
$stmt_payments->execute();
$payments_result = $stmt_payments->get_result();

$payment_labels = [];
$payment_data = [];
while ($row = $payments_result->fetch_assoc()) {
    $payment_labels[] = $row['payment_method'];
    $payment_data[] = (float)$row['total_amount'];
}
$response['payment_methods']['labels'] = $payment_labels;
$response['payment_methods']['data'] = $payment_data;

echo json_encode($response);
$conn->close();
?>