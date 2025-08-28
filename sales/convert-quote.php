<?php
// sales/convert-quote.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (!check_permission('Sales', 'create')) {
    die('Permission denied.');
}

$quote_id = isset($_POST['quote_id']) ? (int)$_POST['quote_id'] : 0;
if ($quote_id === 0) {
    header('Location: quotations.php?error=invalid_id');
    exit();
}

$conn->begin_transaction();
try {
    // Step 1: Fetch the quotation and check if it's 'Accepted' and not already converted
    $quote_stmt = $conn->prepare("SELECT * FROM scs_quotations WHERE id = ?");
    $quote_stmt->bind_param("i", $quote_id);
    $quote_stmt->execute();
    $quote = $quote_stmt->get_result()->fetch_assoc();
    $quote_stmt->close();
    
    if (!$quote) { throw new Exception("Quotation not found."); }
    if ($quote['status'] !== 'Accepted') { throw new Exception("Only 'Accepted' quotations can be converted."); }
    if (!empty($quote['converted_to_order_id'])) {
        header('Location: sales-order-details.php?id=' . $quote['converted_to_order_id'] . '&notice=already_converted');
        exit();
    }

    // Step 2: Fetch the quotation items
    $items_stmt = $conn->prepare("SELECT * FROM scs_quotation_items WHERE quotation_id = ?");
    $items_stmt->bind_param("i", $quote_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();

    // Step 3: Create the new Sales Order record
    $created_by = $_SESSION['user_id'];
    $placeholder_order_number = "TEMP-" . time();

    $stmt_order = $conn->prepare("
        INSERT INTO scs_sales_orders 
        (order_number, customer_id, contact_id, opportunity_id, order_date, status, subtotal, tax_amount, total_amount, notes, created_by) 
        VALUES (?, ?, ?, ?, CURDATE(), 'Confirmed', ?, ?, ?, ?, ?)
    ");
    
    // FIX: Corrected the bind_param type string to have 9 characters for the 9 variables.
    $stmt_order->bind_param("siiidddsi", $placeholder_order_number, $quote['customer_id'], $quote['contact_id'], $quote['opportunity_id'], $quote['subtotal'], $quote['tax_amount'], $quote['total_amount'], $quote['notes'], $created_by);
    $stmt_order->execute();
    $new_sales_order_id = $conn->insert_id;
    $stmt_order->close();

    // Generate and update the real order number
    $order_number = 'SO-' . date('Y') . '-' . str_pad($new_sales_order_id, 5, '0', STR_PAD_LEFT);
    $conn->query("UPDATE scs_sales_orders SET order_number = '$order_number' WHERE id = $new_sales_order_id");

    // Step 4: Copy line items from quote to sales order
    $stmt_items = $conn->prepare("INSERT INTO scs_sales_order_items (sales_order_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
    while ($item = $items_result->fetch_assoc()) {
        $stmt_items->bind_param("iiidd", $new_sales_order_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['line_total']);
        $stmt_items->execute();
    }
    $stmt_items->close();
    
    // Step 5: Update the original quotation to link it to the new order
    $stmt_update_quote = $conn->prepare("UPDATE scs_quotations SET converted_to_order_id = ?, status = 'Accepted' WHERE id = ?");
    $stmt_update_quote->bind_param("ii", $new_sales_order_id, $quote_id);
    $stmt_update_quote->execute();
    $stmt_update_quote->close();

    $conn->commit();
    log_activity('QUOTE_CONVERTED', "Converted Quote {$quote['quote_number']} to Order {$order_number}", $conn);

    // Redirect to the new sales order's EDIT page to confirm details (like location)
    header("Location: sales-order-form.php?id=" . $new_sales_order_id . "&success=converted");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    header('Location: quotation-details.php?id=' . $quote_id . '&error=' . urlencode($e->getMessage()));
    exit();
}