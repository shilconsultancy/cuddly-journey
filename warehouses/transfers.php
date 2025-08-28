<?php
// warehouses/transfers.php

require_once __DIR__ . '/../templates/header.php';

// --- SECURITY CHECK ---
if (!check_permission('Warehouses', 'create')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Stock Transfers - BizManager";

// Initialize variables
$message = '';
$message_type = '';

// --- FORM PROCESSING: CREATE STOCK TRANSFER ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_transfer'])) {
    $from_location_id = $_POST['from_location_id'];
    $to_location_id = $_POST['to_location_id'];
    $notes = trim($_POST['notes']);
    $products = $_POST['products'] ?? []; // This will be an array

    if (empty($from_location_id) || empty($to_location_id) || empty($products)) {
        $message = "Please select 'From' and 'To' locations and add at least one product.";
        $message_type = 'error';
    } elseif ($from_location_id == $to_location_id) {
        $message = "'From' and 'To' locations cannot be the same.";
        $message_type = 'error';
    } else {
        $conn->begin_transaction();
        try {
            // 1. Create the main transfer record
            $transfer_no = 'TRN-' . date('Ymd') . '-' . strtoupper(uniqid());
            $created_by = $_SESSION['user_id'];
            $stmt_transfer = $conn->prepare("INSERT INTO scs_stock_transfers (transfer_no, from_location_id, to_location_id, notes, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt_transfer->bind_param("siisi", $transfer_no, $from_location_id, $to_location_id, $notes, $created_by);
            $stmt_transfer->execute();
            $transfer_id = $conn->insert_id;

            // 2. Loop through products, update inventory, and create transfer item records
            $stmt_items = $conn->prepare("INSERT INTO scs_stock_transfer_items (transfer_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt_inventory_update = $conn->prepare("
                INSERT INTO scs_inventory (product_id, location_id, quantity, last_updated_by) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity + ?, last_updated_by = ?
            ");

            foreach ($products as $product) {
                $product_id = $product['id'];
                $quantity = (int)$product['qty'];

                if ($quantity > 0) {
                    // Add item to transfer record
                    $stmt_items->bind_param("iii", $transfer_id, $product_id, $quantity);
                    $stmt_items->execute();

                    // Subtract stock from 'From' location
                    $neg_quantity = -$quantity;
                    $stmt_inventory_update->bind_param("iiiiii", $product_id, $from_location_id, $neg_quantity, $created_by, $neg_quantity, $created_by);
                    $stmt_inventory_update->execute();

                    // Add stock to 'To' location
                    $stmt_inventory_update->bind_param("iiiiii", $product_id, $to_location_id, $quantity, $created_by, $quantity, $created_by);
                    $stmt_inventory_update->execute();
                }
            }
            
            $conn->commit();
            $message = "Stock transfer " . htmlspecialchars($transfer_no) . " created successfully!";
            $message_type = 'success';
            log_activity('STOCK_TRANSFER_CREATED', "Created transfer " . $transfer_no, $conn);

        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error creating transfer: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// --- DATA FETCHING for the page ---
$locations_result = $conn->query("SELECT id, location_name FROM scs_locations ORDER BY location_name ASC");
$products_result = $conn->query("SELECT id, product_name, sku FROM scs_products ORDER BY product_name ASC");
$transfers_result = $conn->query("
    SELECT t.*, from_loc.location_name as from_name, to_loc.location_name as to_name, u.full_name as creator_name,
           (SELECT COUNT(*) FROM scs_stock_transfer_items ti WHERE ti.transfer_id = t.id) as item_count
    FROM scs_stock_transfers t
    JOIN scs_locations from_loc ON t.from_location_id = from_loc.id
    JOIN scs_locations to_loc ON t.to_location_id = to_loc.id
    LEFT JOIN scs_users u ON t.created_by = u.id
    ORDER BY t.created_at DESC LIMIT 50
");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Page Header -->
<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Stock Transfers</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Inventory
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Create Transfer Form -->
    <div class="lg:col-span-1">
        <form action="transfers.php" method="POST">
            <div class="glass-card p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-800">Create New Transfer</h3>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="from_location_id" class="block text-sm font-medium text-gray-700">From</label>
                        <select name="from_location_id" id="from_location_id" class="form-input mt-1 block w-full rounded-md p-3" required>
                            <option value="">Select source</option>
                            <?php mysqli_data_seek($locations_result, 0); while($loc = $locations_result->fetch_assoc()): ?>
                                <option value="<?php echo $loc['id']; ?>"><?php echo htmlspecialchars($loc['location_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                     <div>
                        <label for="to_location_id" class="block text-sm font-medium text-gray-700">To</label>
                        <select name="to_location_id" id="to_location_id" class="form-input mt-1 block w-full rounded-md p-3" required>
                            <option value="">Select destination</option>
                            <?php mysqli_data_seek($locations_result, 0); while($loc = $locations_result->fetch_assoc()): ?>
                                <option value="<?php echo $loc['id']; ?>"><?php echo htmlspecialchars($loc['location_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="product_search" class="block text-sm font-medium text-gray-700">Add Products</label>
                    <select id="product_search" class="form-input mt-1 block w-full rounded-md p-3">
                        <option value="">Search for a product to add...</option>
                        <?php while($prod = $products_result->fetch_assoc()): ?>
                            <option value="<?php echo $prod['id']; ?>" data-name="<?php echo htmlspecialchars($prod['product_name']); ?>" data-sku="<?php echo htmlspecialchars($prod['sku']); ?>">
                                <?php echo htmlspecialchars($prod['product_name'] . ' (' . $prod['sku'] . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div id="transfer-items-container" class="space-y-2 max-h-60 overflow-y-auto">
                    <!-- Product items will be added here by JavaScript -->
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes (Optional)</label>
                    <textarea name="notes" id="notes" rows="3" class="form-input mt-1 block w-full rounded-md p-3"></textarea>
                </div>

                <div class="pt-2">
                    <button type="submit" name="create_transfer" class="w-full inline-flex justify-center py-3 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Complete Transfer
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Recent Transfers List -->
    <div class="lg:col-span-2">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Transfers</h3>
            <?php if (!empty($message)): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Details</th>
                            <th scope="col" class="px-6 py-3">From</th>
                            <th scope="col" class="px-6 py-3">To</th>
                            <th scope="col" class="px-6 py-3">Items</th>
                            <th scope="col" class="px-6 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $transfers_result->fetch_assoc()): ?>
                        <tr class="bg-white/50 border-b border-gray-200/50">
                            <td class="px-6 py-4 font-medium">
                                <div class="font-semibold"><?php echo htmlspecialchars($row['transfer_no']); ?></div>
                                <div class="text-xs text-gray-500">By: <?php echo htmlspecialchars($row['creator_name']); ?> on <?php echo date($app_config['date_format'], strtotime($row['created_at'])); ?></div>
                            </td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($row['from_name']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($row['to_name']); ?></td>
                            <td class="px-6 py-4 text-center"><?php echo htmlspecialchars($row['item_count']); ?></td>
                            <td class="px-6 py-4">
                                <a href="transfer-details.php?id=<?php echo $row['id']; ?>" class="font-medium text-indigo-600 hover:underline">View Details</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const productSearch = document.getElementById('product_search');
    const itemsContainer = document.getElementById('transfer-items-container');
    const fromLocationDropdown = document.getElementById('from_location_id');

    productSearch.addEventListener('change', function() {
        if (!this.value) return;

        const selectedOption = this.options[this.selectedIndex];
        const productId = this.value;
        const productName = selectedOption.dataset.name;
        const productSku = selectedOption.dataset.sku;
        const fromLocationId = fromLocationDropdown.value;

        if (!fromLocationId) {
            alert('Please select a "From" location first.');
            this.value = '';
            return;
        }

        if (document.getElementById('product-row-' + productId)) {
            alert('This product is already in the transfer list.');
            this.value = '';
            return;
        }

        // Fetch current stock via API
        fetch(`../api/get_stock.php?product_id=${productId}&location_id=${fromLocationId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const availableStock = data.quantity;
                    const itemHtml = `
                        <div id="product-row-${productId}" class="flex items-center space-x-2 bg-white/60 p-2 rounded-md">
                            <input type="hidden" name="products[${productId}][id]" value="${productId}">
                            <div class="flex-grow">
                                <p class="font-semibold text-sm">${productName}</p>
                                <p class="text-xs text-gray-500">SKU: ${productSku} | <span class="text-blue-600">Avail: ${availableStock}</span></p>
                            </div>
                            <input type="number" name="products[${productId}][qty]" class="form-input w-24 p-2 rounded-md text-center" value="1" min="1" max="${availableStock}" required>
                            <button type="button" onclick="removeItem(${productId})" class="p-2 text-red-500 hover:bg-red-100 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    `;
                    itemsContainer.insertAdjacentHTML('beforeend', itemHtml);
                }
            });

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