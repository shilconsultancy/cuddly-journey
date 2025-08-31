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

$response = [
    'sales_by_hour' => [],
    'payment_methods' => []
];
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');

// --- 1. Sales by Hour Data ---
$hours = [];
for ($i = 0; $i < 24; $i++) {
    $hour_label = date('ha', strtotime("$i:00")); // e.g., 8am, 1pm
    $hours[$i] = ['hour' => $hour_label, 'total' => 0];
}

$stmt_sales = $conn->prepare("
    SELECT 
        HOUR(ps.created_at) as sale_hour,
        COALESCE(SUM(i.total_amount), 0) as total_revenue
    FROM scs_pos_sales ps
    JOIN scs_invoices i ON ps.invoice_id = i.id
    WHERE ps.created_at BETWEEN ? AND ?
    AND i.sales_order_id IN (SELECT id FROM scs_sales_orders WHERE location_id = ?)
    GROUP BY sale_hour
");
$stmt_sales->bind_param("ssi", $today_start, $today_end, $location_id);
$stmt_sales->execute();
$sales_result = $stmt_sales->get_result();
while ($row = $sales_result->fetch_assoc()) {
    $hours[(int)$row['sale_hour']]['total'] = (float)$row['total_revenue'];
}

$response['sales_by_hour']['labels'] = array_column($hours, 'hour');
$response['sales_by_hour']['data'] = array_column($hours, 'total');

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
$stmt_payments->bind_param("ssi", $today_start, $today_end, $location_id);
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