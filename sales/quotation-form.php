<?php
// sales/quotation-form.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// --- INITIALIZE VARIABLES ---
$edit_mode = false;
$quote_data = [];
$line_items_data = [];
$page_title = "Create New Quotation";
$message = '';
$message_type = '';

// --- DETECT EDIT MODE & CHECK PERMISSIONS ---
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    if (!check_permission('Sales', 'edit')) { die('Permission denied.'); }
    $edit_mode = true;
    $quote_id = (int)$_GET['id'];
    $page_title = "Edit Quotation";
} else {
    if (!check_permission('Sales', 'create')) { die('Permission denied.'); }
}

// --- START: FORM PROCESSING LOGIC (CREATE OR UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    try {
        $customer_id = $_POST['customer_id'];
        $contact_id = !empty($_POST['contact_id']) ? $_POST['contact_id'] : NULL;
        $quote_date = $_POST['quote_date'];
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL;
        $status = $_POST['status'];
        $notes = $_POST['notes'];
        $created_by = $_SESSION['user_id'];
        $product_ids = $_POST['products'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $prices = $_POST['prices'] ?? [];
        $quote_id_post = !empty($_POST['quote_id']) ? (int)$_POST['quote_id'] : 0;

        // --- Server-side recalculation and data preparation ---
        $subtotal = 0;
        $recalculated_line_items = [];
        if(!empty($product_ids)){
            for ($i = 0; $i < count($product_ids); $i++) {
                $product_id = (int)$product_ids[$i];
                $quantity = (int)$quantities[$i];
                $unit_price = (float)$prices[$i];
                if ($product_id > 0 && $quantity > 0) {
                    $line_total = $unit_price * $quantity;
                    $subtotal += $line_total;
                    $recalculated_line_items[] = ['product_id' => $product_id, 'quantity' => $quantity, 'unit_price' => $unit_price, 'line_total' => $line_total];
                }
            }
        }
        $tax_amount = 0.00;
        $total_amount = $subtotal + $tax_amount;
        
        usort($recalculated_line_items, function($a, $b) {
            return $a['product_id'] <=> $b['product_id'];
        });
        $items_hash = md5(json_encode($recalculated_line_items));

        if ($quote_id_post > 0) {
            // --- UPDATE LOGIC ---
            $quote_id_to_update = $quote_id_post;
            $stmt_quote = $conn->prepare("UPDATE scs_quotations SET customer_id=?, contact_id=?, quote_date=?, expiry_date=?, status=?, subtotal=?, tax_amount=?, total_amount=?, items_hash=?, notes=? WHERE id=?");
            
            // FIX: Corrected the bind_param type string to include the type for contact_id (11 characters for 11 variables)
            $stmt_quote->bind_param("iisssddsssi", $customer_id, $contact_id, $quote_date, $expiry_date, $status, $subtotal, $tax_amount, $total_amount, $items_hash, $notes, $quote_id_to_update);
            $stmt_quote->execute();

            $conn->query("DELETE FROM scs_quotation_items WHERE quotation_id = $quote_id_to_update");
            $stmt_items = $conn->prepare("INSERT INTO scs_quotation_items (quotation_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
            foreach ($recalculated_line_items as $item) {
                $stmt_items->bind_param("iiidd", $quote_id_to_update, $item['product_id'], $item['quantity'], $item['unit_price'], $item['line_total']);
                $stmt_items->execute();
            }
            $stmt_items->close();
            log_activity('QUOTATION_UPDATED', "Updated quotation ID: " . $quote_id_to_update, $conn);
            $success_message = "updated";
        } else {
            // --- CREATE LOGIC ---
            $dupe_check_stmt = $conn->prepare("SELECT quote_number FROM scs_quotations WHERE customer_id = ? AND items_hash = ? AND status IN ('Draft', 'Sent', 'Accepted')");
            $dupe_check_stmt->bind_param("is", $customer_id, $items_hash);
            $dupe_check_stmt->execute();
            $dupe_result = $dupe_check_stmt->get_result();
            if ($dupe_result->num_rows > 0) {
                $existing_quote = $dupe_result->fetch_assoc();
                throw new Exception("A duplicate quotation ({$existing_quote['quote_number']}) already exists with these exact items for this customer.");
            }

            $placeholder_quote_number = "TEMP-" . time();
            $stmt_quote = $conn->prepare("INSERT INTO scs_quotations (quote_number, customer_id, contact_id, quote_date, expiry_date, status, subtotal, tax_amount, total_amount, items_hash, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_quote->bind_param("siisssddsssi", $placeholder_quote_number, $customer_id, $contact_id, $quote_date, $expiry_date, $status, $subtotal, $tax_amount, $total_amount, $items_hash, $notes, $created_by);
            $stmt_quote->execute();
            $new_quote_id = $conn->insert_id;
            $stmt_quote->close();

            $quote_number = 'QT-' . date('Y') . '-' . str_pad($new_quote_id, 5, '0', STR_PAD_LEFT);
            $conn->query("UPDATE scs_quotations SET quote_number = '$quote_number' WHERE id = $new_quote_id");

            $stmt_items = $conn->prepare("INSERT INTO scs_quotation_items (quotation_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
            foreach ($recalculated_line_items as $item) {
                $stmt_items->bind_param("iiidd", $new_quote_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['line_total']);
                $stmt_items->execute();
            }
            $stmt_items->close();
            log_activity('QUOTATION_CREATED', "Created new quotation: " . $quote_number, $conn);
            $success_message = "created";
        }

        $conn->commit();
        header("Location: quotations.php?success=" . $success_message);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $message = $e->getMessage();
        $message_type = 'error';
    }
}


// --- DATA FETCHING for form in Edit Mode ---
if ($edit_mode) {
    $quote_stmt = $conn->prepare("SELECT * FROM scs_quotations WHERE id = ?");
    $quote_stmt->bind_param("i", $quote_id);
    $quote_stmt->execute();
    $quote_data = $quote_stmt->get_result()->fetch_assoc();
    $quote_stmt->close();
    if (!$quote_data) { die("Quotation not found."); }
    $items_stmt = $conn->prepare("SELECT * FROM scs_quotation_items WHERE quotation_id = ?");
    $items_stmt->bind_param("i", $quote_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    while ($row = $items_result->fetch_assoc()) { $line_items_data[] = $row; }
    $items_stmt->close();
}
// --- DATA FETCHING for dropdowns ---
$customers_result = $conn->query("SELECT id, customer_name FROM scs_customers WHERE is_active = 1 ORDER BY customer_name ASC");
$products_result = $conn->query("SELECT id, product_name, selling_price FROM scs_products ORDER BY product_name ASC");
$products = [];
while ($row = $products_result->fetch_assoc()) { $products[] = $row; }
$line_items_json = json_encode($line_items_data);

require_once __DIR__ . '/../templates/header.php';
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800"><?php echo htmlspecialchars($page_title); ?></h2>
        <p class="text-gray-600 mt-1"><?php if($edit_mode) { echo 'Editing quotation ' . htmlspecialchars($quote_data['quote_number']); } else { echo 'Fill in the details to create a new quotation.'; } ?></p>
    </div>
    <a href="quotations.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Quotations
    </a>
</div>

<div class="glass-card p-6 lg:p-8">
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'error' ? 'bg-red-100/80 text-red-800' : 'bg-green-100/80 text-green-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="quotation-form.php<?php if($edit_mode) echo '?id='.$quote_id; ?>" method="POST" id="quotation-form">
        <input type="hidden" name="quote_id" value="<?php echo htmlspecialchars($quote_data['id'] ?? ''); ?>">

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="md:col-span-2">
                <label for="customer_id" class="block text-sm font-medium text-gray-700">Customer</label>
                <select name="customer_id" id="customer_id" class="form-input mt-1 block w-full" required>
                    <option value="">Select a customer...</option>
                    <?php mysqli_data_seek($customers_result, 0); while($customer = $customers_result->fetch_assoc()): ?>
                        <option value="<?php echo $customer['id']; ?>" <?php if(isset($quote_data['customer_id']) && $quote_data['customer_id'] == $customer['id']) echo 'selected'; ?>><?php echo htmlspecialchars($customer['customer_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="contact_id" class="block text-sm font-medium text-gray-700">Contact Person</label>
                <select name="contact_id" id="contact_id" class="form-input mt-1 block w-full">
                    <option value="">Select a contact...</option>
                </select>
            </div>
            <div>
                <label for="quote_date" class="block text-sm font-medium text-gray-700">Quotation Date</label>
                <input type="date" name="quote_date" id="quote_date" value="<?php echo htmlspecialchars($quote_data['quote_date'] ?? date('Y-m-d')); ?>" class="form-input mt-1 block w-full" required>
            </div>
            <div>
                <label for="expiry_date" class="block text-sm font-medium text-gray-700">Expiry Date</label>
                <input type="date" name="expiry_date" id="expiry_date" value="<?php echo htmlspecialchars($quote_data['expiry_date'] ?? ''); ?>" class="form-input mt-1 block w-full">
            </div>
            <div class="md:col-span-2">
                <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                <select name="status" id="status" class="form-input mt-1 block w-full">
                    <?php 
                    $statuses = ['Draft', 'Sent', 'Accepted', 'Rejected'];
                    foreach ($statuses as $status) {
                        $selected = (isset($quote_data['status']) && $quote_data['status'] == $status) ? 'selected' : '';
                        echo "<option value='$status' $selected>$status</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="border-t border-gray-200/50 my-6"></div>
        <div id="line-items-section">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Products</h3>
                <button type="button" id="add-item-btn" class="px-4 py-2 bg-green-500 text-white text-sm font-semibold rounded-lg hover:bg-green-600">Add Product</button>
            </div>
            <div id="line-items-container" class="space-y-4"></div>
        </div>
        <div class="border-t border-gray-200/50 my-6"></div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700">Notes / Terms</label>
                <textarea name="notes" id="notes" rows="5" class="form-input mt-1 block w-full"><?php echo htmlspecialchars($quote_data['notes'] ?? ''); ?></textarea>
            </div>
            <div class="bg-gray-50/50 rounded-lg p-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-4">Summary</h4>
                <div class="space-y-3">
                    <div class="flex justify-between items-center text-gray-700"><span>Subtotal</span><span id="subtotal" class="font-semibold">0.00</span></div>
                    <div class="flex justify-between items-center text-gray-700"><span>Tax (0%)</span><span id="tax-amount" class="font-semibold">0.00</span></div>
                    <div class="flex justify-between items-center text-xl font-bold text-gray-900 pt-3 border-t"><span>Grand Total</span><span id="grand-total">0.00</span></div>
                </div>
            </div>
        </div>
        <div class="flex justify-end pt-8">
            <a href="quotations.php" class="bg-gray-200 py-2 px-5 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-300">Cancel</a>
            <button type="submit" class="ml-3 inline-flex justify-center py-2 px-5 rounded-md text-white bg-indigo-600 hover:bg-indigo-700"><?php echo $edit_mode ? 'Update Quotation' : 'Save Quotation'; ?></button>
        </div>
    </form>
</div>

<template id="line-item-template">
    <div class="line-item grid grid-cols-12 gap-4 items-center bg-white/50 p-3 rounded-lg">
        <div class="col-span-5"><select name="products[]" class="product-select form-input w-full" required><option value="">Select...</option><?php foreach($products as $p){ echo "<option value='{$p['id']}' data-price='{$p['selling_price']}'>".htmlspecialchars($p['product_name'])."</option>"; } ?></select></div>
        <div class="col-span-2"><input type="number" name="quantities[]" class="quantity-input form-input w-full" value="1" min="1" required></div>
        <div class="col-span-2"><input type="number" step="0.01" name="prices[]" class="price-input form-input w-full"></div>
        <div class="col-span-2 text-right font-semibold"><span class="line-total">0.00</span></div>
        <div class="col-span-1 text-right"><button type="button" class="remove-item-btn text-red-500 hover:text-red-700 p-2 font-bold text-lg">&times;</button></div>
    </div>
</template>

<script>
    // The JavaScript remains the same
document.addEventListener('DOMContentLoaded', function() {
    const isEditMode = <?php echo $edit_mode ? 'true' : 'false'; ?>;
    const existingLineItems = <?php echo $line_items_json; ?>;
    const existingContactId = <?php echo json_encode($quote_data['contact_id'] ?? null); ?>;
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