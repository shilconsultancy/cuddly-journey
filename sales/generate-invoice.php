<?php
// sales/generate-invoice.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (!check_permission('Sales', 'create')) {
    die('Permission denied.');
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id === 0) {
    header('Location: sales-orders.php?error=invalid_id');
    exit();
}

$conn->begin_transaction();
try {
    // Step 1: Check if an invoice for this order already exists
    $check_stmt = $conn->prepare("SELECT id FROM scs_invoices WHERE sales_order_id = ?");
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $existing_invoice = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($existing_invoice) {
        // FIX: Redirect to the correct invoice details page
        header('Location: invoice-details.php?id=' . $existing_invoice['id'] . '&notice=invoice_exists');
        exit();
    }

    // Step 2: Fetch the sales order and its items
    $order_stmt = $conn->prepare("SELECT * FROM scs_sales_orders WHERE id = ?");
    $order_stmt->bind_param("i", $order_id);
    $order_stmt->execute();
    $order = $order_stmt->get_result()->fetch_assoc();
    $order_stmt->close();
    
    if (!$order) {
        throw new Exception("Sales order not found.");
    }

    $items_stmt = $conn->prepare("SELECT * FROM scs_sales_order_items WHERE sales_order_id = ?");
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    // Step 3: Create the main invoice record
    $invoice_date = date('Y-m-d');
    $due_date = date('Y-m-d', strtotime('+30 days')); // Default 30-day due date
    $created_by = $_SESSION['user_id'];
    $placeholder_invoice_number = "TEMP-" . time();

    $stmt_invoice = $conn->prepare("
        INSERT INTO scs_invoices 
        (invoice_number, sales_order_id, customer_id, invoice_date, due_date, total_amount, status, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, 'Draft', ?)
    ");
    $stmt_invoice->bind_param("siissdi", $placeholder_invoice_number, $order_id, $order['customer_id'], $invoice_date, $due_date, $order['total_amount'], $created_by);
    $stmt_invoice->execute();
    $new_invoice_id = $conn->insert_id;
    $stmt_invoice->close();

    // Generate and update the real invoice number
    $invoice_number = 'INV-' . date('Y') . '-' . str_pad($new_invoice_id, 5, '0', STR_PAD_LEFT);
    $conn->query("UPDATE scs_invoices SET invoice_number = '$invoice_number' WHERE id = $new_invoice_id");

    // Step 4: Copy the line items from the sales order to the invoice
    $stmt_items = $conn->prepare("INSERT INTO scs_invoice_items (invoice_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
    while ($item = $items_result->fetch_assoc()) {
        $stmt_items->bind_param("iiidd", $new_invoice_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['line_total']);
        $stmt_items->execute();
    }
    $stmt_items->close();

    // If everything succeeded, commit the transaction
    $conn->commit();
    log_activity('INVOICE_GENERATED', "Generated invoice {$invoice_number} from Sales Order {$order['order_number']}", $conn);
    
    // FIX: Redirect to the correct invoice details page
    header('Location: invoice-details.php?id=' . $new_invoice_id . '&success=generated');
    exit();

} catch (Exception $e) {
    $conn->rollback();
    header('Location: sales-order-details.php?id=' . $order_id . '&error=' . urlencode($e->getMessage()));
    exit();
}