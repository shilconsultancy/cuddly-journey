<?php
// products/index.php

require_once __DIR__ . '/../templates/header.php';

// --- SECURITY CHECK ---
if (!check_permission('Products', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Product Management - BizManager";

// Initialize variables
$message = '';
$message_type = '';
$edit_mode = false;
$product_to_edit = ['id' => '', 'product_name' => '', 'sku' => '', 'description' => '', 'cost_price' => '', 'selling_price' => ''];

// --- FORM PROCESSING: ADD or UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && check_permission('Products', 'create')) {
    $product_name = trim($_POST['product_name']);
    $sku = trim($_POST['sku']);
    $description = trim($_POST['description']);
    $cost_price = $_POST['cost_price'];
    $selling_price = $_POST['selling_price'];
    $product_id = $_POST['product_id'];

    if (empty($product_name) || empty($sku) || empty($selling_price)) {
        $message = "Product Name, SKU, and Selling Price are required.";
        $message_type = 'error';
    } else {
        if (!empty($product_id) && check_permission('Products', 'edit')) {
            // --- UPDATE existing product ---
            $stmt = $conn->prepare("UPDATE scs_products SET product_name = ?, sku = ?, description = ?, cost_price = ?, selling_price = ? WHERE id = ?");
            $stmt->bind_param("sssddi", $product_name, $sku, $description, $cost_price, $selling_price, $product_id);
            if ($stmt->execute()) {
                log_activity('PRODUCT_UPDATED', "Updated product: " . $product_name, $conn);
                $message = "Product updated successfully!";
                $message_type = 'success';
            } else {
                $message = "Error updating product: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            // --- ADD new product ---
            $created_by = $_SESSION['user_id'];
            $stmt = $conn->prepare("INSERT INTO scs_products (product_name, sku, description, cost_price, selling_price, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssddi", $product_name, $sku, $description, $cost_price, $selling_price, $created_by);
            if ($stmt->execute()) {
                log_activity('PRODUCT_CREATED', "Created new product: " . $product_name, $conn);
                $message = "Product added successfully!";
                $message_type = 'success';
            } else {
                $message = "Error adding product: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete']) && check_permission('Products', 'delete')) {
    $product_id_to_delete = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM scs_products WHERE id = ?");
    $stmt->bind_param("i", $product_id_to_delete);
    if ($stmt->execute()) {
        log_activity('PRODUCT_DELETED', "Deleted product with ID: " . $product_id_to_delete, $conn);
        $message = "Product deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting product: " . $stmt->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// --- HANDLE EDIT ---
if (isset($_GET['edit']) && check_permission('Products', 'edit')) {
    $edit_mode = true;
    $product_id_to_edit = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM scs_products WHERE id = ?");
    $stmt->bind_param("i", $product_id_to_edit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $product_to_edit = $result->fetch_assoc();
    }
    $stmt->close();
}


// --- DATA FETCHING for the list (UPDATED QUERY) ---
$products_result = $conn->query("
    SELECT 
        p.*,
        COALESCE(SUM(inv.quantity), 0) AS total_stock
    FROM 
        scs_products p
    LEFT JOIN 
        scs_inventory inv ON p.id = inv.product_id
    GROUP BY
        p.id
    ORDER BY
        p.product_name ASC
");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Product Catalog</h2>
    <a href="../dashboard.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Dashboard
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <?php if (check_permission('Products', 'create') || check_permission('Products', 'edit')): ?>
    <div class="lg:col-span-1">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4"><?php echo $edit_mode ? 'Edit Product' : 'Add New Product'; ?></h3>
            <form action="index.php" method="POST" class="space-y-4">
                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_to_edit['id']); ?>">
                <div>
                    <label for="product_name" class="block text-sm font-medium text-gray-700">Product Name</label>
                    <input type="text" name="product_name" id="product_name" value="<?php echo htmlspecialchars($product_to_edit['product_name']); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
                </div>
                <div>
                    <label for="sku" class="block text-sm font-medium text-gray-700">SKU (Stock Keeping Unit)</label>
                    <input type="text" name="sku" id="sku" value="<?php echo htmlspecialchars($product_to_edit['sku']); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
                </div>
                 <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" id="description" rows="3" class="form-input mt-1 block w-full rounded-md p-3"><?php echo htmlspecialchars($product_to_edit['description']); ?></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="cost_price" class="block text-sm font-medium text-gray-700">Cost Price</label>
                        <input type="number" step="0.01" name="cost_price" id="cost_price" value="<?php echo htmlspecialchars($product_to_edit['cost_price']); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                    </div>
                    <div>
                        <label for="selling_price" class="block text-sm font-medium text-gray-700">Selling Price</label>
                        <input type="number" step="0.01" name="selling_price" id="selling_price" value="<?php echo htmlspecialchars($product_to_edit['selling_price']); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
                    </div>
                </div>
                <div class="flex justify-end pt-2">
                    <?php if ($edit_mode): ?>
                        <a href="index.php" class="bg-gray-200 py-2 px-4 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-300">Cancel</a>
                    <?php endif; ?>
                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <?php echo $edit_mode ? 'Update Product' : 'Add Product'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?php echo (check_permission('Products', 'create') || check_permission('Products', 'edit')) ? 'lg:col-span-2' : 'lg:col-span-3'; ?>">
        <div class="glass-card p-6">
            <?php if (!empty($message)): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Product</th>
                            <th scope="col" class="px-6 py-3">SKU</th>
                            <th scope="col" class="px-6 py-3 text-center">Total Stock</th>
                            <th scope="col" class="px-6 py-3">Selling Price</th>
                            <th scope="col" class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $products_result->fetch_assoc()): ?>
                        <tr class="bg-white/50 border-b border-gray-200/50">
                            <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($row['product_name']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($row['sku']); ?></td>
                            <td class="px-6 py-4 text-center">
                                <?php
                                    $stock = (int)$row['total_stock'];
                                    $stock_color_class = 'text-gray-700';
                                    if ($stock <= 0) {
                                        $stock_color_class = 'text-red-600';
                                    } elseif ($stock <= 10) {
                                        $stock_color_class = 'text-yellow-600';
                                    } else {
                                        $stock_color_class = 'text-green-600';
                                    }
                                    echo "<span class='font-bold text-lg $stock_color_class'>$stock</span>";
                                ?>
                            </td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($app_config['currency_symbol'] . ' ' . number_format($row['selling_price'], 2)); ?></td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="product-details.php?id=<?php echo $row['id']; ?>" class="font-medium text-green-600 hover:underline">View</a>
                                <?php if (check_permission('Products', 'edit')): ?>
                                <a href="index.php?edit=<?php echo $row['id']; ?>" class="font-medium text-indigo-600 hover:underline">Edit</a>
                                <?php endif; ?>
                                <?php if (check_permission('Products', 'delete')): ?>
                                <a href="index.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.');" class="font-medium text-red-600 hover:underline">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>