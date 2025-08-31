<?php
// api/get-shop-dashboard-data.php

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
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

$start_datetime = $start_date . ' 00:00:00';
$end_datetime = $end_date . ' 23:59:59';


$response = [
    'kpi' => [],
    'live_data' => []
];

// --- KPI CALCULATIONS ---

// 1. KPI: Revenue & Transactions for the period
$stmt_sales = $conn->prepare("
    SELECT 
        COALESCE(SUM(i.total_amount), 0) as total_revenue,
        COUNT(ps.id) as transaction_count
    FROM scs_pos_sales ps
    JOIN scs_invoices i ON ps.invoice_id = i.id
    WHERE ps.created_at BETWEEN ? AND ?
    AND i.sales_order_id IN (SELECT id FROM scs_sales_orders WHERE location_id = ?)
");
$stmt_sales->bind_param("ssi", $start_datetime, $end_datetime, $location_id);
$stmt_sales->execute();
$sales_today = $stmt_sales->get_result()->fetch_assoc();
$response['kpi']['revenue_today'] = $sales_today['total_revenue'] ?? 0;
$response['kpi']['transactions_today'] = $sales_today['transaction_count'] ?? 0;

// 2. KPI: Gross Profit for the period
$stmt_profit = $conn->prepare("
    SELECT 
        COALESCE(SUM(ii.quantity * (p.selling_price - p.cost_price)), 0) as gross_profit
    FROM scs_invoice_items ii
    JOIN scs_invoices i ON ii.invoice_id = i.id
    JOIN scs_pos_sales ps ON i.id = ps.invoice_id
    JOIN scs_products p ON ii.product_id = p.id
    WHERE ps.created_at BETWEEN ? AND ?
    AND i.sales_order_id IN (SELECT id FROM scs_sales_orders WHERE location_id = ?)
");
$stmt_profit->bind_param("ssi", $start_datetime, $end_datetime, $location_id);
$stmt_profit->execute();
$profit_today = $stmt_profit->get_result()->fetch_assoc();
$response['kpi']['profit_today'] = $profit_today['gross_profit'] ?? 0;

// 3. KPI: Active Staff (only relevant for 'today' view)
if ($start_date == date('Y-m-d') && $end_date == date('Y-m-d')) {
    $stmt_staff = $conn->prepare("
        SELECT COUNT(DISTINCT user_id) as active_staff
        FROM scs_pos_sessions
        WHERE status = 'Active' AND location_id = ?
    ");
    $stmt_staff->bind_param("i", $location_id);
    $stmt_staff->execute();
    $active_staff = $stmt_staff->get_result()->fetch_assoc();
    $response['kpi']['active_staff'] = $active_staff['active_staff'] ?? 0;
} else {
    $response['kpi']['active_staff'] = 'N/A';
}


// --- LIVE DATA TABLES ---

// 1. Live Data: Recent Sales (still shows latest 5 regardless of date range)
$stmt_recent = $conn->prepare("
    SELECT i.invoice_number, i.total_amount, ps.created_at
    FROM scs_pos_sales ps
    JOIN scs_invoices i ON ps.invoice_id = i.id
    WHERE i.sales_order_id IN (SELECT id FROM scs_sales_orders WHERE location_id = ?)
    ORDER BY ps.created_at DESC
    LIMIT 5
");
$stmt_recent->bind_param("i", $location_id);
$stmt_recent->execute();
$response['live_data']['recent_sales'] = $stmt_recent->get_result()->fetch_all(MYSQLI_ASSOC);

// 2. Live Data: Top Selling Products for the period
$stmt_top_products = $conn->prepare("
    SELECT p.product_name, SUM(ii.quantity) as total_quantity
    FROM scs_invoice_items ii
    JOIN scs_invoices i ON ii.invoice_id = i.id
    JOIN scs_pos_sales ps ON i.id = ps.invoice_id
    JOIN scs_products p ON ii.product_id = p.id
    WHERE ps.created_at BETWEEN ? AND ?
    AND i.sales_order_id IN (SELECT id FROM scs_sales_orders WHERE location_id = ?)
    GROUP BY ii.product_id
    ORDER BY total_quantity DESC
    LIMIT 5
");
$stmt_top_products->bind_param("ssi", $start_datetime, $end_datetime, $location_id);
$stmt_top_products->execute();
$response['live_data']['top_products_today'] = $stmt_top_products->get_result()->fetch_all(MYSQLI_ASSOC);


echo json_encode($response);
$conn->close();
?>