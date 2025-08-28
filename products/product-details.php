<?php
// products/product-details.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Products', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to view this page.</div>');
}

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id === 0) {
    die('<div class="glass-card p-8 text-center">Invalid Product ID provided.</div>');
}

// --- DATA FETCHING ---

// 1. Fetch the main product details
$product_stmt = $conn->prepare("SELECT * FROM scs_products WHERE id = ?");
$product_stmt->bind_param("i", $product_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();
$product = $product_result->fetch_assoc();
$product_stmt->close();

if (!$product) {
    die('<div class="glass-card p-8 text-center">Product not found.</div>');
}

// 2. Fetch the inventory breakdown by location for this product
// This query ensures ALL locations are listed, showing 0 for those without stock.
$inventory_stmt = $conn->prepare("
    SELECT 
        loc.location_name,
        COALESCE(inv.quantity, 0) AS stock_quantity
    FROM 
        scs_locations loc
    LEFT JOIN 
        scs_inventory inv ON loc.id = inv.location_id AND inv.product_id = ?
    ORDER BY 
        loc.location_name ASC
");
$inventory_stmt->bind_param("i", $product_id);
$inventory_stmt->execute();
$inventory_result = $inventory_stmt->get_result();


$page_title = "Product: " . htmlspecialchars($product['product_name']);

?>

<title><?php echo $page_title; ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800"><?php echo htmlspecialchars($product['product_name']); ?></h2>
        <p class="text-gray-600 mt-1">Detailed view and inventory breakdown.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to Product List
        </a>
        <?php if (check_permission('Products', 'edit')): ?>
        <a href="index.php?edit=<?php echo $product_id; ?>" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700">
            Edit Product
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Product Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-sm text-gray-500">SKU</p>
                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($product['sku']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Selling Price</p>
                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($product['selling_price'], 2)); ?></p>
                </div>
                 <div>
                    <p class="text-sm text-gray-500">Cost Price</p>
                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($product['cost_price'], 2)); ?></p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm text-gray-500">Description</p>
                    <p class="text-gray-700 mt-1"><?php echo !empty($product['description']) ? nl2br(htmlspecialchars($product['description'])) : 'N/A'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <div>
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Inventory by Location</h3>
            <ul class="space-y-3">
                <?php while($inventory = $inventory_result->fetch_assoc()): ?>
                <li class="flex justify-between items-center bg-white/50 p-3 rounded-md">
                    <span class="text-gray-700"><?php echo htmlspecialchars($inventory['location_name']); ?></span>
                    <?php
                        $stock = (int)$inventory['stock_quantity'];
                        $stock_color_class = 'text-gray-700';
                        if ($stock <= 0) {
                            $stock_color_class = 'text-red-600';
                        } elseif ($stock <= 10) {
                            $stock_color_class = 'text-yellow-600';
                        } else {
                            $stock_color_class = 'text-green-600';
                        }
                    ?>
                    <span class="font-bold text-lg <?php echo $stock_color_class; ?>"><?php echo $stock; ?></span>
                </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>