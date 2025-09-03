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

    // UPDATED QUERY: Fetch the current cost_price (WAC) from the products table
    $items_stmt = $conn->prepare("
        SELECT soi.*, p.cost_price 
        FROM scs_sales_order_items soi
        JOIN scs_products p ON soi.product_id = p.id
        WHERE soi.sales_order_id = ?
    ");
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    // Step 3: Create the main invoice record
    $invoice_date = date('Y-m-d');
    $due_date = date('Y-m-d', strtotime('+30 days'));
    $created_by = $_SESSION['user_id'];
    $placeholder_invoice_number = "TEMP-" . time();

    $stmt_invoice = $conn->prepare("
        INSERT INTO scs_invoices 
        (invoice_number, sales_order_id, customer_id, invoice_date, due_date, total_amount, status, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, 'Sent', ?)
    ");
    $stmt_invoice->bind_param("siissdi", $placeholder_invoice_number, $order_id, $order['customer_id'], $invoice_date, $due_date, $order['total_amount'], $created_by);
    $stmt_invoice->execute();
    $new_invoice_id = $conn->insert_id;
    $stmt_invoice->close();

    $invoice_number = 'INV-' . date('Y') . '-' . str_pad($new_invoice_id, 5, '0', STR_PAD_LEFT);
    $conn->query("UPDATE scs_invoices SET invoice_number = '$invoice_number' WHERE id = $new_invoice_id");

    // Step 4: Copy items and calculate total cost of goods sold (COGS) using the fetched WAC
    $stmt_items = $conn->prepare("INSERT INTO scs_invoice_items (invoice_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
    $total_cogs = 0;
    $items_data = $items_result->fetch_all(MYSQLI_ASSOC); // Fetch all items into an array
    foreach ($items_data as $item) {
        $stmt_items->bind_param("iiidd", $new_invoice_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['line_total']);
        $stmt_items->execute();
        // Use the fetched cost_price (WAC) for COGS calculation
        $total_cogs += $item['quantity'] * $item['cost_price'];
    }
    $stmt_items->close();

    // Step 5: Create the journal entry for the sale (Revenue and AR)
    $je_description_revenue = "Sale on credit - Invoice " . $invoice_number;
    // Account IDs: 3=AR, 6=Sales Revenue
    $debits_revenue = [ ['account_id' => 3, 'amount' => $order['total_amount']] ];
    $credits_revenue = [ ['account_id' => 6, 'amount' => $order['total_amount']] ];
    create_journal_entry($conn, $invoice_date, $je_description_revenue, $debits_revenue, $credits_revenue, 'Invoice', $new_invoice_id);

    // Step 6: Create the journal entry for the Cost of Goods Sold using WAC
    $je_description_cogs = "Cost of Goods Sold for Invoice " . $invoice_number;
    // Account IDs: 7=COGS, 4=Inventory Asset
    $debits_cogs = [ ['account_id' => 7, 'amount' => $total_cogs] ];
    $credits_cogs = [ ['account_id' => 4, 'amount' => $total_cogs] ];
    create_journal_entry($conn, $invoice_date, $je_description_cogs, $debits_cogs, $credits_cogs, 'Invoice', $new_invoice_id);

    $conn->commit();
    log_activity('INVOICE_GENERATED', "Generated invoice {$invoice_number} from Sales Order {$order['order_number']}", $conn);
    
    header('Location: invoice-details.php?id=' . $new_invoice_id . '&success=generated');
    exit();

} catch (Exception $e) {
    $conn->rollback();
    header('Location: sales-order-details.php?id=' . $order_id . '&error=' . urlencode($e->getMessage()));
    exit();
}