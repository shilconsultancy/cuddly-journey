<?php
// procurement/add-po.php

require_once __DIR__ . '/../templates/header.php';

// --- SECURITY CHECK ---
if (!check_permission('Procurement', 'create')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "New Purchase Order - BizManager";

// Initialize variables
$message = '';
$message_type = '';

// Data scope variables for filtering dropdowns
$has_global_scope = ($_SESSION['data_scope'] ?? 'Local') === 'Global';
$user_location_id = $_SESSION['location_id'] ?? null;

// --- FORM PROCESSING: CREATE PURCHASE ORDER ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_po'])) {
    $supplier_id = $_POST['supplier_id'];
    $location_id = $_POST['location_id'];
    $order_date = $_POST['order_date'];
    $expected_delivery_date = !empty($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : NULL;
    $notes = trim($_POST['notes']);
    $products = $_POST['products'] ?? [];

    if (empty($supplier_id) || empty($location_id) || empty($order_date) || empty($products)) {
        $message = "Please select a supplier, location, order date, and add at least one product.";
        $message_type = 'error';
    } else {
        $conn->begin_transaction();
        try {
            // 1. Generate a unique PO Number
            $current_year = date('Y');
            $stmt_count = $conn->prepare("SELECT COUNT(id) as year_count FROM scs_purchase_orders WHERE YEAR(order_date) = ?");
            $stmt_count->bind_param("s", $current_year);
            $stmt_count->execute();
            $result_count = $stmt_count->get_result();
            $po_count_this_year = $result_count->fetch_assoc()['year_count'];
            $sequence_number = str_pad($po_count_this_year + 1, 4, '0', STR_PAD_LEFT);
            $po_number = "PO-" . $current_year . "-" . $sequence_number;

            // 2. Create the main PO record
            $created_by = $_SESSION['user_id'];
            $total_amount = 0;
            foreach ($products as $product) {
                $total_amount += (float)$product['qty'] * (float)$product['price'];
            }

            $stmt_po = $conn->prepare("INSERT INTO scs_purchase_orders (po_number, supplier_id, location_id, total_amount, notes, created_by, order_date, expected_delivery_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_po->bind_param("siidssss", $po_number, $supplier_id, $location_id, $total_amount, $notes, $created_by, $order_date, $expected_delivery_date);
            $stmt_po->execute();
            $po_id = $conn->insert_id;

            // 3. Loop through products and create PO item records
            $stmt_items = $conn->prepare("INSERT INTO scs_purchase_order_items (po_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
            foreach ($products as $product) {
                $product_id = $product['id'];
                $quantity = (int)$product['qty'];
                $unit_price = (float)$product['price'];

                if ($quantity > 0) {
                    $stmt_items->bind_param("iiid", $po_id, $product_id, $quantity, $unit_price);
                    $stmt_items->execute();
                }
            }
            
            $conn->commit();
            header("Location: purchase-orders.php?success=created");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error creating Purchase Order: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// --- DATA FETCHING for the page ---
$suppliers_result = $conn->query("SELECT id, supplier_name FROM scs_suppliers ORDER BY supplier_name ASC");
$products_result = $conn->query("SELECT id, product_name, sku, cost_price FROM scs_products ORDER BY product_name ASC");

$location_sql = "SELECT id, location_name FROM scs_locations";
if (!$has_global_scope && $user_location_id) {
    $location_sql .= " WHERE id = " . (int)$user_location_id;
}
$location_sql .= " ORDER BY location_name ASC";
$locations_result = $conn->query($location_sql);
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">New Purchase Order</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Procurement
    </a>
</div>

<div class="glass-card p-8 max-w-6xl mx-auto">
    
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="add-po.php" method="POST" class="space-y-6">
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <label for="supplier_id" class="block text-sm font-medium text-gray-700">Supplier</label>
                <select name="supplier_id" id="supplier_id" class="form-input mt-1 block w-full rounded-md p-3" required>
                    <option value="">Select a supplier...</option>
                    <?php while($supplier = $suppliers_result->fetch_assoc()): ?>
                        <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['supplier_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label for="location_id" class="block text-sm font-medium text-gray-700">Deliver To</label>
                <select name="location_id" id="location_id" class="form-input mt-1 block w-full rounded-md p-3" required>
                    <option value="">Select a location...</option>
                    <?php while($loc = $locations_result->fetch_assoc()): ?>
                        <option value="<?php echo $loc['id']; ?>"><?php echo htmlspecialchars($loc['location_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label for="order_date" class="block text-sm font-medium text-gray-700">Order Date</label>
                <input type="date" name="order_date" id="order_date" value="<?php echo date('Y-m-d'); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
            <div>
                <label for="expected_delivery_date" class="block text-sm font-medium text-gray-700">Expected Delivery</label>
                <input type="date" name="expected_delivery_date" id="expected_delivery_date" class="form-input mt-1 block w-full rounded-md p-3">
            </div>
        </div>

        <div class="border-t border-gray-200/50 pt-6">
            <label for="product_search" class="block text-sm font-medium text-gray-700">Add Products to Order</label>
            <select id="product_search" class="form-input mt-1 block w-full rounded-md p-3">
                <option value="">Search for a product to add...</option>
                <?php while($prod = $products_result->fetch_assoc()): ?>
                    <option value="<?php echo $prod['id']; ?>" data-name="<?php echo htmlspecialchars($prod['product_name']); ?>" data-sku="<?php echo htmlspecialchars($prod['sku']); ?>" data-price="<?php echo htmlspecialchars($prod['cost_price']); ?>">
                        <?php echo htmlspecialchars($prod['product_name'] . ' (' . $prod['sku'] . ')'); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs text-gray-800 uppercase">
                    <tr>
                        <th class="px-4 py-2">Product</th>
                        <th class="px-4 py-2 w-24">Quantity</th>
                        <th class="px-4 py-2 w-32">Unit Price</th>
                        <th class="px-4 py-2 w-32">Subtotal</th>
                        <th class="px-4 py-2 w-12"></th>
                    </tr>
                </thead>
                <tbody id="po-items-container">
                    </tbody>
                <tfoot class="text-gray-800 font-semibold">
                    <tr>
                        <td colspan="3" class="text-right px-4 py-2">Total Amount:</td>
                        <td id="total-amount" class="px-4 py-2 text-lg">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700">Notes (Optional)</label>
            <textarea name="notes" id="notes" rows="3" class="form-input mt-1 block w-full rounded-md p-3"></textarea>
        </div>

        <div class="flex justify-end pt-6 border-t border-gray-200/50">
            <button type="submit" name="create_po" class="w-full md:w-auto inline-flex justify-center py-3 px-6 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                Create Purchase Order
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const productSearch = document.getElementById('product_search');
    const itemsContainer = document.getElementById('po-items-container');
    const totalAmountEl = document.getElementById('total-amount');

    productSearch.addEventListener('change', function() {
        if (!this.value) return;

        const selectedOption = this.options[this.selectedIndex];
        const productId = this.value;
        const productName = selectedOption.dataset.name;
        const productSku = selectedOption.dataset.sku;
        const productPrice = parseFloat(selectedOption.dataset.price).toFixed(2);

        if (document.getElementById('product-row-' + productId)) {
            alert('This product is already in the order list.');
            this.value = '';
            return;
        }

        const itemHtml = `
            <tr id="product-row-${productId}" class="border-b border-gray-200/50">
                <td class="px-4 py-2">
                    <input type="hidden" name="products[${productId}][id]" value="${productId}">
                    <p class="font-semibold text-sm">${productName}</p>
                    <p class="text-xs text-gray-500">SKU: ${productSku}</p>
                </td>
                <td class="px-4 py-2">
                    <input type="number" name="products[${productId}][qty]" class="form-input w-full p-2 rounded-md text-center" value="1" min="1" oninput="updateTotals()" required>
                </td>
                <td class="px-4 py-2">
                    <input type="number" step="0.01" name="products[${productId}][price]" class="form-input w-full p-2 rounded-md text-center" value="${productPrice}" oninput="updateTotals()" required>
                </td>
                <td class="px-4 py-2 text-right font-semibold subtotal">
                    ${productPrice}
                </td>
                <td class="px-4 py-2 text-center">
                    <button type="button" onclick="removeItem(${productId})" class="p-1 text-red-500 hover:bg-red-100 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </td>
            </tr>
        `;
        
        itemsContainer.insertAdjacentHTML('beforeend', itemHtml);
        this.value = ''; // Reset dropdown
        updateTotals();
    });
});

function removeItem(productId) {
    const itemRow = document.getElementById('product-row-' + productId);
    if (itemRow) {
        itemRow.remove();
        updateTotals();
    }
}

function updateTotals() {
    let total = 0;
    const itemRows = document.querySelectorAll('#po-items-container tr');
    itemRows.forEach(row => {
        const qty = parseFloat(row.querySelector('input[name*="[qty]"]').value) || 0;
        const price = parseFloat(row.querySelector('input[name*="[price]"]').value) || 0;
        const subtotal = qty * price;
        row.querySelector('.subtotal').textContent = subtotal.toFixed(2);
        total += subtotal;
    });
    document.getElementById('total-amount').textContent = total.toFixed(2);
}
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>