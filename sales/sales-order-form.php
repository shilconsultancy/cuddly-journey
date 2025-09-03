<?php
// sales/sales-order-form.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// --- INITIALIZE VARIABLES ---
$edit_mode = false;
$order_data = [];
$line_items_data = [];
$message = '';
$message_type = '';
$is_invoice_mode = (isset($_GET['mode']) && $_GET['mode'] === 'invoice');

// --- DETECT EDIT MODE & CHECK PERMISSIONS ---
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    if (!check_permission('Sales', 'edit')) { die('Permission denied.'); }
    $edit_mode = true;
    $order_id = (int)$_GET['id'];
    $page_title = "Edit Sales Order";
} else {
    if (!check_permission('Sales', 'create')) { die('Permission denied.'); }
    $page_title = $is_invoice_mode ? "Create New Invoice" : "Create New Sales Order";
}

// --- FORM PROCESSING LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    try {
        $customer_id = $_POST['customer_id'];
        $contact_id = !empty($_POST['contact_id']) ? $_POST['contact_id'] : NULL;
        $location_id = $_POST['location_id'];
        $order_date = $_POST['order_date'];
        $status = $_POST['status'];
        $notes = $_POST['notes'];
        $created_by = $_SESSION['user_id'];
        $product_ids = $_POST['products'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $prices = $_POST['prices'] ?? [];
        $order_id_post = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $is_invoice_mode_post = (isset($_POST['is_invoice_mode']) && $_POST['is_invoice_mode'] === '1');

        // --- Server-side recalculation and data preparation ---
        $subtotal = 0;
        $recalculated_line_items = [];
        if(!empty($product_ids)){
            for ($i = 0; $i < count($product_ids); $i++) {
                $product_id = (int)$product_ids[$i];
                $quantity = (int)$quantities[$i];
                $unit_price = (float)$prices[$i];
                if ($product_id > 0 && $quantity > 0) {
                    $prod_stmt = $conn->prepare("SELECT product_name FROM scs_products WHERE id = ?");
                    $prod_stmt->bind_param("i", $product_id);
                    $prod_stmt->execute();
                    $product_name_res = $prod_stmt->get_result()->fetch_assoc();
                    $product_name = $product_name_res ? $product_name_res['product_name'] : 'Unknown Product';
                    $prod_stmt->close();
                    
                    $line_total = $unit_price * $quantity;
                    $subtotal += $line_total;
                    $recalculated_line_items[] = ['product_id' => $product_id, 'product_name' => $product_name, 'quantity' => $quantity, 'unit_price' => $unit_price, 'line_total' => $line_total];
                }
            }
        }
        $tax_amount = 0.00;
        $total_amount = $subtotal + $tax_amount;
        
        usort($recalculated_line_items, function($a, $b) {
            return $a['product_id'] <=> $b['product_id'];
        });
        $items_hash = md5(json_encode($recalculated_line_items));
        
        $statuses_that_affect_inventory = ['Confirmed', 'Processing', 'Shipped', 'Completed'];
        
        // --- INVENTORY CHECK ---
        if (in_array($status, $statuses_that_affect_inventory)) {
            if(empty($location_id)) { throw new Exception("Please select a 'Sell From Location' to confirm the order."); }
            foreach ($recalculated_line_items as $item) {
                // Get current stock
                $stock_stmt = $conn->prepare("SELECT quantity FROM scs_inventory WHERE product_id = ? AND location_id = ?");
                $stock_stmt->bind_param("ii", $item['product_id'], $location_id);
                $stock_stmt->execute();
                $stock_result = $stock_stmt->get_result()->fetch_assoc();
                $available_stock = $stock_result['quantity'] ?? 0;
                $stock_stmt->close();
                
                if ($available_stock < $item['quantity']) {
                    throw new Exception("Not enough stock for product '{$item['product_name']}'. Available: {$available_stock}, Ordered: {$item['quantity']}.");
                }
            }
        }

        if ($order_id_post > 0) {
            // UPDATE LOGIC
            // NOTE: Inventory adjustment on update is complex and should be handled by a separate "fulfill order" process.
            // This form will primarily update order details.
            $order_id_to_update = $order_id_post;
            $stmt_order = $conn->prepare("UPDATE scs_sales_orders SET customer_id=?, contact_id=?, location_id=?, order_date=?, status=?, subtotal=?, tax_amount=?, total_amount=?, items_hash=?, notes=? WHERE id=?");
            $stmt_order->bind_param("iiisssddssi", $customer_id, $contact_id, $location_id, $order_date, $status, $subtotal, $tax_amount, $total_amount, $items_hash, $notes, $order_id_to_update);
            $stmt_order->execute();

            $conn->query("DELETE FROM scs_sales_order_items WHERE sales_order_id = $order_id_to_update");
            $stmt_items = $conn->prepare("INSERT INTO scs_sales_order_items (sales_order_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
            foreach ($recalculated_line_items as $item) { 
                $stmt_items->bind_param("iiidd", $order_id_to_update, $item['product_id'], $item['quantity'], $item['unit_price'], $item['line_total']);
                $stmt_items->execute(); 
            }
            $stmt_items->close();
            log_activity('SALES_ORDER_UPDATED', "Updated sales order ID: " . $order_id_to_update, $conn);
            $redirect_url = "sales-orders.php?success=updated";
        } else {
            // CREATE LOGIC
            $placeholder_order_number = "TEMP-" . time() . "-" . $created_by;
            $stmt_order = $conn->prepare("INSERT INTO scs_sales_orders (order_number, customer_id, contact_id, location_id, order_date, status, subtotal, tax_amount, total_amount, items_hash, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_order->bind_param("siiisssddssi", $placeholder_order_number, $customer_id, $contact_id, $location_id, $order_date, $status, $subtotal, $tax_amount, $total_amount, $items_hash, $notes, $created_by);
            $stmt_order->execute();
            $new_sales_order_id = $conn->insert_id;
            $stmt_order->close();

            $order_number = 'SO-' . date('Y') . '-' . str_pad($new_sales_order_id, 5, '0', STR_PAD_LEFT);
            $conn->query("UPDATE scs_sales_orders SET order_number = '$order_number' WHERE id = $new_sales_order_id");

            $stmt_items = $conn->prepare("INSERT INTO scs_sales_order_items (sales_order_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
            foreach ($recalculated_line_items as $item) { 
                $stmt_items->bind_param("iiidd", $new_sales_order_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['line_total']);
                $stmt_items->execute(); 
            }
            $stmt_items->close();
            log_activity('SALES_ORDER_CREATED', "Created new sales order: " . $order_number, $conn);
            
            // --- DEDUCT INVENTORY ON CREATE IF APPLICABLE ---
            if (in_array($status, $statuses_that_affect_inventory)) {
                $update_stock_stmt = $conn->prepare("UPDATE scs_inventory SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?");
                foreach ($recalculated_line_items as $item) { 
                    $update_stock_stmt->bind_param("iii", $item['quantity'], $item['product_id'], $location_id);
                    $update_stock_stmt->execute(); 
                }
                $update_stock_stmt->close();
            }
            
            if ($is_invoice_mode_post) {
                $redirect_url = "generate-invoice.php?id=" . $new_sales_order_id;
            } else {
                $redirect_url = "sales-orders.php?success=created";
            }
        }
        
        $conn->commit();
        header("Location: " . $redirect_url);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// ... (The rest of the file (data fetching, HTML, JS) remains unchanged) ...
if ($edit_mode) {
    $order_stmt = $conn->prepare("SELECT * FROM scs_sales_orders WHERE id = ?");
    $order_stmt->bind_param("i", $order_id);
    $order_stmt->execute();
    $order_data = $order_stmt->get_result()->fetch_assoc();
    $order_stmt->close();
    if (!$order_data) { die("Sales order not found."); }
    $items_stmt = $conn->prepare("SELECT * FROM scs_sales_order_items WHERE sales_order_id = ?");
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    while ($row = $items_result->fetch_assoc()) { $line_items_data[] = $row; }
    $items_stmt->close();
}
$customers_result = $conn->query("SELECT id, customer_name FROM scs_customers WHERE is_active = 1 ORDER BY customer_name ASC");
$products_result = $conn->query("SELECT id, product_name, selling_price FROM scs_products ORDER BY product_name ASC");
$locations_result = $conn->query("SELECT id, location_name FROM scs_locations ORDER BY location_name ASC");
$products = [];
while ($row = $products_result->fetch_assoc()) { $products[] = $row; }
$line_items_json = json_encode($line_items_data);
require_once __DIR__ . '/../templates/header.php';
?>
<title><?php echo htmlspecialchars($page_title); ?></title>
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div><h2 class="text-2xl font-semibold text-gray-800"><?php echo htmlspecialchars($page_title); ?></h2><p class="text-gray-600 mt-1"><?php if($edit_mode) { echo 'Editing order ' . htmlspecialchars($order_data['order_number']); } else { echo 'Fill in the details to create a new record.'; } ?></p></div>
    <a href="<?php echo $is_invoice_mode ? 'invoices.php' : 'sales-orders.php'; ?>" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">&larr; Back to List</a>
</div>
<div class="glass-card p-6 lg:p-8">
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'error' ? 'bg-red-100/80 text-red-800' : 'bg-green-100/80 text-green-800'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <form action="sales-order-form.php<?php if($edit_mode) echo '?id='.$order_id; ?><?php if($is_invoice_mode && !$edit_mode) echo '?mode=invoice'; ?>" method="POST" id="sales-order-form">
        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_data['id'] ?? ''); ?>"><input type="hidden" name="is_invoice_mode" value="<?php echo $is_invoice_mode ? '1' : '0'; ?>">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div><label for="customer_id" class="block text-sm font-medium text-gray-700">Customer</label><select name="customer_id" id="customer_id" class="form-input mt-1 block w-full" required><option value="">Select a customer...</option><?php mysqli_data_seek($customers_result, 0); while($customer = $customers_result->fetch_assoc()): ?><option value="<?php echo $customer['id']; ?>" <?php if(isset($order_data['customer_id']) && $order_data['customer_id'] == $customer['id']) echo 'selected'; ?>><?php echo htmlspecialchars($customer['customer_name']); ?></option><?php endwhile; ?></select></div>
            <div><label for="contact_id" class="block text-sm font-medium text-gray-700">Contact Person</label><select name="contact_id" id="contact_id" class="form-input mt-1 block w-full"><option value="">Select a contact...</option></select></div>
            <div><label for="location_id" class="block text-sm font-medium text-gray-700">Sell From Location</label><select name="location_id" id="location_id" class="form-input mt-1 block w-full" required><option value="">Select a location...</option><?php while($location = $locations_result->fetch_assoc()): ?><option value="<?php echo $location['id']; ?>" <?php if(isset($order_data['location_id']) && $order_data['location_id'] == $location['id']) echo 'selected'; ?>><?php echo htmlspecialchars($location['location_name']); ?></option><?php endwhile; ?></select></div>
            <div><label for="order_date" class="block text-sm font-medium text-gray-700">Date</label><input type="date" name="order_date" id="order_date" value="<?php echo htmlspecialchars($order_data['order_date'] ?? date('Y-m-d')); ?>" class="form-input mt-1 block w-full" required></div>
            <div><label for="status" class="block text-sm font-medium text-gray-700">Status</label><select name="status" id="status" class="form-input mt-1 block w-full"><?php $statuses = ['Draft', 'Confirmed', 'Processing', 'Shipped', 'Completed', 'Cancelled']; foreach ($statuses as $status) { $selected = (isset($order_data['status']) && $order_data['status'] == $status) ? 'selected' : ''; echo "<option value='$status' $selected>$status</option>"; } ?></select></div>
        </div>
        <div class="border-t border-gray-200/50 my-6"></div><div id="line-items-section"><div class="flex justify-between items-center mb-4"><h3 class="text-lg font-semibold text-gray-800">Products</h3><button type="button" id="add-item-btn" class="px-4 py-2 bg-green-500 text-white text-sm font-semibold rounded-lg hover:bg-green-600">Add Product</button></div><div id="line-items-container" class="space-y-4"></div></div><div class="border-t border-gray-200/50 my-6"></div><div class="grid grid-cols-1 md:grid-cols-2 gap-8"><div><label for="notes" class="block text-sm font-medium text-gray-700">Internal Notes</label><textarea name="notes" id="notes" rows="5" class="form-input mt-1 block w-full"><?php echo htmlspecialchars($order_data['notes'] ?? ''); ?></textarea></div><div class="bg-gray-50/50 rounded-lg p-6"><h4 class="text-lg font-semibold text-gray-800 mb-4">Summary</h4><div class="space-y-3"><div class="flex justify-between items-center text-gray-700"><span>Subtotal</span><span id="subtotal" class="font-semibold">0.00</span></div><div class="flex justify-between items-center text-gray-700"><span>Tax (0%)</span><span id="tax-amount" class="font-semibold">0.00</span></div><div class="flex justify-between items-center text-xl font-bold text-gray-900 pt-3 border-t"><span>Grand Total</span><span id="grand-total">0.00</span></div></div></div></div>
        <div class="flex justify-end pt-8">
            <a href="<?php echo $is_invoice_mode ? 'invoices.php' : 'sales-orders.php'; ?>" class="bg-gray-200 py-2 px-5 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-300">Cancel</a>
            <button type="submit" class="ml-3 inline-flex justify-center py-2 px-5 rounded-md text-white bg-indigo-600 hover:bg-indigo-700"><?php echo $edit_mode ? 'Update Order' : ($is_invoice_mode ? 'Save & Generate Invoice' : 'Save Order'); ?></button>
        </div>
    </form>
</div>
<template id="line-item-template"><div class="line-item grid grid-cols-12 gap-4 items-center bg-white/50 p-3 rounded-lg"><div class="col-span-5"><select name="products[]" class="product-select form-input w-full" required><option value="">Select...</option><?php foreach($products as $p){ echo "<option value='{$p['id']}' data-price='{$p['selling_price']}'>".htmlspecialchars($p['product_name'])."</option>"; } ?></select></div><div class="col-span-2"><input type="number" name="quantities[]" class="quantity-input form-input w-full" value="1" min="1" required></div><div class="col-span-2"><input type="number" step="0.01" name="prices[]" class="price-input form-input w-full"></div><div class="col-span-2 text-right font-semibold"><span class="line-total">0.00</span></div><div class="col-span-1 text-right"><button type="button" class="remove-item-btn text-red-500 hover:text-red-700 p-2 font-bold text-lg">&times;</button></div></div></template>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isEditMode = <?php echo $edit_mode ? 'true' : 'false'; ?>;
    const existingLineItems = <?php echo $line_items_json; ?>;
    const existingContactId = <?php echo json_encode($order_data['contact_id'] ?? null); ?>;
    const customerDropdown = document.getElementById('customer_id');
    const contactDropdown = document.getElementById('contact_id');
    const addItemBtn = document.getElementById('add-item-btn');
    const lineItemsContainer = document.getElementById('line-items-container');
    const lineItemTemplate = document.getElementById('line-item-template');
    const subtotalEl = document.getElementById('subtotal');
    const taxAmountEl = document.getElementById('tax-amount');
    const grandTotalEl = document.getElementById('grand-total');
    function fetchContacts(customerId, selectedContactId = null) { if (!customerId) { contactDropdown.innerHTML = '<option value="">Select a contact...</option>'; return; } fetch(`../api/get_contacts.php?customer_id=${customerId}`).then(response => response.json()).then(data => { let options = '<option value="">None</option>'; if (data.success && data.contacts.length > 0) { data.contacts.forEach(c => { const selected = (c.id == selectedContactId) ? 'selected' : ''; options += `<option value="${c.id}" ${selected}>${c.contact_name}</option>`; }); } else { options = '<option value="">No contacts found</option>'; } contactDropdown.innerHTML = options; }); }
    customerDropdown.addEventListener('change', () => fetchContacts(customerDropdown.value));
    function addLineItem(itemData = null) { const templateContent = lineItemTemplate.content.cloneNode(true); const newRow = templateContent.querySelector('.line-item'); if (itemData) { newRow.querySelector('.product-select').value = itemData.product_id; newRow.querySelector('.quantity-input').value = itemData.quantity; newRow.querySelector('.price-input').value = parseFloat(itemData.unit_price).toFixed(2); } lineItemsContainer.appendChild(templateContent); updateLineItem(newRow, newRow.querySelector('.product-select')); }
    addItemBtn.addEventListener('click', () => addLineItem());
    function updateLineItem(row, triggerElement) { const productSelect = row.querySelector('.product-select'); const quantityInput = row.querySelector('.quantity-input'); const priceInput = row.querySelector('.price-input'); const lineTotalEl = row.querySelector('.line-total'); let price = 0; if (triggerElement.classList.contains('product-select')) { const selectedOption = productSelect.options[productSelect.selectedIndex]; price = parseFloat(selectedOption.dataset.price || 0); priceInput.value = price.toFixed(2); } else { price = parseFloat(priceInput.value || 0); } const quantity = parseInt(quantityInput.value || 0); const lineTotal = price * quantity; lineTotalEl.textContent = lineTotal.toFixed(2); updateGrandTotal(); }
    function updateGrandTotal() { let subtotal = 0; lineItemsContainer.querySelectorAll('.line-item').forEach(line => { subtotal += parseFloat(line.querySelector('.line-total').textContent || 0); }); const tax = 0; const grandTotal = subtotal + tax; subtotalEl.textContent = subtotal.toFixed(2); taxAmountEl.textContent = tax.toFixed(2); grandTotalEl.textContent = grandTotal.toFixed(2); }
    lineItemsContainer.addEventListener('input', e => { if (e.target.matches('.product-select, .quantity-input, .price-input')) { updateLineItem(e.target.closest('.line-item'), e.target); } });
    lineItemsContainer.addEventListener('click', e => { if (e.target.closest('.remove-item-btn')) { e.target.closest('.line-item').remove(); updateGrandTotal(); } });
    if (isEditMode) { if (customerDropdown.value) { fetchContacts(customerDropdown.value, existingContactId); } if (existingLineItems.length > 0) { existingLineItems.forEach(item => addLineItem(item)); } else { addLineItem(); } } else { addLineItem(); }
});
</script>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>