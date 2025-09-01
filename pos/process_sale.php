<?php
// pos/process_sale.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Get the raw POST data from the JS fetch call
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

$cart = $data['cart'] ?? [];
$payment_method = $data['payment_method'] ?? 'Cash';
$amount_tendered = $data['amount_tendered'] ?? 0;
$total_amount = $data['total_amount'] ?? 0;

$user_id = $_SESSION['user_id'] ?? 0;
$location_id = $_SESSION['location_id'] ?? 0;
$session_id = $_SESSION['pos_session_id'] ?? 0;

if (empty($cart) || !$user_id || !$location_id || !$session_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid sale data or session.']);
    exit;
}

$conn->begin_transaction();
try {
    // 1. Get or Create a 'Walk-in Customer'
    $customer_id = 0;
    $cust_stmt = $conn->prepare("SELECT id FROM scs_customers WHERE customer_name = 'Walk-in Customer'");
    $cust_stmt->execute();
    $cust_result = $cust_stmt->get_result();
    if ($cust_result->num_rows > 0) {
        $customer_id = $cust_result->fetch_assoc()['id'];
    } else {
        $stmt_new_cust = $conn->prepare("INSERT INTO scs_customers (customer_name, customer_type, is_active, created_by) VALUES ('Walk-in Customer', 'B2C', 1, ?)");
        $stmt_new_cust->bind_param("i", $user_id);
        $stmt_new_cust->execute();
        $customer_id = $conn->insert_id;
        log_activity('CUSTOMER_CREATED', "Auto-created 'Walk-in Customer' for POS.", $conn);
    }

    // 2. Create Sales Order
    $placeholder_so = "TEMP-SO-" . time();
    $stmt_so = $conn->prepare("INSERT INTO scs_sales_orders (order_number, customer_id, location_id, order_date, status, subtotal, total_amount, created_by) VALUES (?, ?, ?, CURDATE(), 'Completed', ?, ?, ?)");
    $stmt_so->bind_param("siiddi", $placeholder_so, $customer_id, $location_id, $total_amount, $total_amount, $user_id);
    $stmt_so->execute();
    $sales_order_id = $conn->insert_id;
    $order_number = 'SO-' . date('Y') . '-' . str_pad($sales_order_id, 5, '0', STR_PAD_LEFT);
    $conn->query("UPDATE scs_sales_orders SET order_number = '$order_number' WHERE id = $sales_order_id");

    // 3. Create Sales Order Items & Update Inventory
    $stmt_so_item = $conn->prepare("INSERT INTO scs_sales_order_items (sales_order_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
    $stmt_inv_update = $conn->prepare("UPDATE scs_inventory SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?");
    $total_cost = 0;
    $prod_cost_stmt = $conn->prepare("SELECT cost_price FROM scs_products WHERE id = ?");

    foreach ($cart as $product_id => $item) {
        $line_total = $item['quantity'] * $item['price'];
        $stmt_so_item->bind_param("iiidd", $sales_order_id, $product_id, $item['quantity'], $item['price'], $line_total);
        $stmt_so_item->execute();
        
        $stmt_inv_update->bind_param("iii", $item['quantity'], $product_id, $location_id);
        $stmt_inv_update->execute();
        
        // Calculate total cost of goods sold
        $prod_cost_stmt->bind_param("i", $product_id);
        $prod_cost_stmt->execute();
        $cost_price = $prod_cost_stmt->get_result()->fetch_assoc()['cost_price'] ?? 0;
        $total_cost += $item['quantity'] * $cost_price;
    }
    $prod_cost_stmt->close();

    // 4. Create Invoice
    $placeholder_inv = "TEMP-INV-" . time();
    $stmt_inv = $conn->prepare("INSERT INTO scs_invoices (invoice_number, sales_order_id, customer_id, invoice_date, due_date, total_amount, amount_paid, status, created_by) VALUES (?, ?, ?, CURDATE(), CURDATE(), ?, ?, 'Paid', ?)");
    $stmt_inv->bind_param("siiddi", $placeholder_inv, $sales_order_id, $customer_id, $total_amount, $total_amount, $user_id);
    $stmt_inv->execute();
    $invoice_id = $conn->insert_id;
    $invoice_number = 'INV-' . date('Y') . '-' . str_pad($invoice_id, 5, '0', STR_PAD_LEFT);
    $conn->query("UPDATE scs_invoices SET invoice_number = '$invoice_number' WHERE id = $invoice_id");

    // 5. Create Invoice Items
    $stmt_inv_item = $conn->prepare("INSERT INTO scs_invoice_items (invoice_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
     foreach ($cart as $product_id => $item) {
        $line_total = $item['quantity'] * $item['price'];
        $stmt_inv_item->bind_param("iiidd", $invoice_id, $product_id, $item['quantity'], $item['price'], $line_total);
        $stmt_inv_item->execute();
    }

    // 6. Record Payment
    $stmt_payment = $conn->prepare("INSERT INTO scs_invoice_payments (invoice_id, payment_date, amount, payment_method, recorded_by) VALUES (?, CURDATE(), ?, ?, ?)");
    $stmt_payment->bind_param("idsi", $invoice_id, $total_amount, $payment_method, $user_id);
    $stmt_payment->execute();

    // 7. Create POS Sale Record
    $change_given = $amount_tendered - $total_amount;
    $stmt_pos_sale = $conn->prepare("INSERT INTO scs_pos_sales (pos_session_id, sales_order_id, invoice_id, customer_id, payment_method, amount_tendered, change_given) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_pos_sale->bind_param("iiiisdd", $session_id, $sales_order_id, $invoice_id, $customer_id, $payment_method, $amount_tendered, $change_given);
    $stmt_pos_sale->execute();
    
    // 8. Create Journal Entry for the cash sale
    $je_description = "POS Sale - Invoice " . $invoice_number;
    $debits = [
        ['account_id' => 1, 'amount' => $total_amount], // Debit Cash on Hand
        ['account_id' => 5, 'amount' => $total_cost]      // Debit Cost of Goods Sold
    ];
    $credits = [
        ['account_id' => 4, 'amount' => $total_amount], // Credit Sales Revenue
        ['account_id' => 3, 'amount' => $total_cost]      // Credit Inventory Asset
    ];
    create_journal_entry($conn, date('Y-m-d'), $je_description, $debits, $credits, 'POS Sale', $invoice_id);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Sale processed successfully.', 'invoice_id' => $invoice_id]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
}

$conn->close();
?>