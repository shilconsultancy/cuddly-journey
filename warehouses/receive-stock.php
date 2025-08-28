<?php
// warehouses/receive-stock.php

require_once __DIR__ . '/../templates/header.php';

// --- SECURITY CHECK ---
if (!check_permission('Warehouses', 'create')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Receive Stock - BizManager";

// Initialize variables
$message = '';
$message_type = '';

// --- FORM PROCESSING: RECEIVE STOCK ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['receive_stock'])) {
    $location_id = $_POST['location_id'];
    $reference_no = trim($_POST['reference_no']);
    $notes = trim($_POST['notes']);
    $products = $_POST['products'] ?? [];

    if (empty($location_id) || empty($products)) {
        $message = "Please select a location and add at least one product.";
        $message_type = 'error';
    } else {
        $conn->begin_transaction();
        try {
            $created_by = $_SESSION['user_id'];
            
            $stmt_inventory_update = $conn->prepare("
                INSERT INTO scs_inventory (product_id, location_id, quantity, last_updated_by) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity + ?, last_updated_by = ?
            ");

            $total_items = 0;
            foreach ($products as $product) {
                $product_id = $product['id'];
                $quantity = (int)$product['qty'];

                if ($quantity > 0) {
                    // Add stock to the location
                    $stmt_inventory_update->bind_param("iiiiii", $product_id, $location_id, $quantity, $created_by, $quantity, $created_by);
                    $stmt_inventory_update->execute();
                    $total_items += $quantity;
                }
            }
            
            // Log this entire event as a single activity
            $location_name_res = $conn->query("SELECT location_name FROM scs_locations WHERE id = $location_id")->fetch_assoc()['location_name'];
            log_activity('STOCK_RECEIVED', "Received " . $total_items . " total items into '" . htmlspecialchars($location_name_res) . "'. Reference: " . htmlspecialchars($reference_no), $conn);

            $conn->commit();
            $message = "Stock received successfully!";
            $message_type = 'success';

        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error receiving stock: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// --- DATA FETCHING for the page ---
$locations_result = $conn->query("SELECT id, location_name FROM scs_locations ORDER BY location_name ASC");
$products_result = $conn->query("SELECT id, product_name, sku FROM scs_products ORDER BY product_name ASC");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Page Header -->
<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Receive Stock</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Inventory
    </a>
</div>

<div class="glass-card p-8 max-w-4xl mx-auto">
    
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="receive-stock.php" method="POST" class="space-y-6">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="location_id" class="block text-sm font-medium text-gray-700">Receiving Location</label>
                <select name="location_id" id="location_id" class="form-input mt-1 block w-full rounded-md p-3" required>
                    <option value="">Select a location...</option>
                    <?php while($loc = $locations_result->fetch_assoc()): ?>
                        <option value="<?php echo $loc['id']; ?>"><?php echo htmlspecialchars($loc['location_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label for="reference_no" class="block text-sm font-medium text-gray-700">Reference # (Optional)</label>
                <input type="text" name="reference_no" id="reference_no" placeholder="e.g., Supplier Invoice or PO Number" class="form-input mt-1 block w-full rounded-md p-3">
            </div>
        </div>

        <div class="border-t border-gray-200/50 pt-6">
            <label for="product_search" class="block text-sm font-medium text-gray-700">Add Products to Receive</label>
            <select id="product_search" class="form-input mt-1 block w-full rounded-md p-3">
                <option value="">Search for a product to add...</option>
                <?php while($prod = $products_result->fetch_assoc()): ?>
                    <option value="<?php echo $prod['id']; ?>" data-name="<?php echo htmlspecialchars($prod['product_name']); ?>" data-sku="<?php echo htmlspecialchars($prod['sku']); ?>">
                        <?php echo htmlspecialchars($prod['product_name'] . ' (' . $prod['sku'] . ')'); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div id="receive-items-container" class="space-y-2 max-h-80 overflow-y-auto">
            <!-- Product items will be added here by JavaScript -->
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700">Notes (Optional)</label>
            <textarea name="notes" id="notes" rows="3" class="form-input mt-1 block w-full rounded-md p-3"></textarea>
        </div>

        <div class="flex justify-end pt-6 border-t border-gray-200/50">
            <button type="submit" name="receive_stock" class="w-full md:w-auto inline-flex justify-center py-3 px-6 rounded-md text-white bg-green-600 hover:bg-green-700">
                Add Stock to Inventory
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const productSearch = document.getElementById('product_search');
    const itemsContainer = document.getElementById('receive-items-container');

    productSearch.addEventListener('change', function() {
        if (!this.value) return;

        const selectedOption = this.options[this.selectedIndex];
        const productId = this.value;
        const productName = selectedOption.dataset.name;
        const productSku = selectedOption.dataset.sku;

        if (document.getElementById('product-row-' + productId)) {
            alert('This product is already in the list.');
            this.value = '';
            return;
        }

        const itemHtml = `
            <div id="product-row-${productId}" class="flex items-center space-x-2 bg-white/60 p-2 rounded-md">
                <input type="hidden" name="products[${productId}][id]" value="${productId}">
                <div class="flex-grow">
                    <p class="font-semibold text-sm">${productName}</p>
                    <p class="text-xs text-gray-500">SKU: ${productSku}</p>
                </div>
                <!-- THIS IS THE QUANTITY INPUT -->
                <input type="number" name="products[${productId}][qty]" placeholder="Qty" class="form-input w-24 p-2 rounded-md text-center" value="1" min="1" required>
                <button type="button" onclick="removeItem(${productId})" class="p-2 text-red-500 hover:bg-red-100 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        `;
        
        itemsContainer.insertAdjacentHTML('beforeend', itemHtml);
        this.value = ''; // Reset dropdown
    });
});

function removeItem(productId) {
    const itemRow = document.getElementById('product-row-' + productId);
    if (itemRow) {
        itemRow.remove();
    }
}
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>