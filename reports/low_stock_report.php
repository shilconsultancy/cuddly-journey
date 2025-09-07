<?php
// reports/low_stock_report.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Reports', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Low Stock Report - BizManager";

// --- DATA FETCHING ---
$reorder_level = 10; // Define the low stock threshold

$sql = "
    SELECT 
        p.id as product_id,
        p.product_name,
        p.sku,
        l.location_name,
        i.quantity
    FROM scs_inventory i
    JOIN scs_products p ON i.product_id = p.id
    JOIN scs_locations l ON i.location_id = l.id
    WHERE i.quantity <= ?
    ORDER BY i.quantity ASC, p.product_name ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $reorder_level);
$stmt->execute();
$low_stock_result = $stmt->get_result();

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Low Stock Report</h2>
        <p class="text-gray-600 mt-1">Products at or below the reorder level of <?php echo $reorder_level; ?> units.</p>
    </div>
    <a href="inventory_reports.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Inventory Reports
    </a>
</div>

<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th class="px-6 py-3">Product</th>
                    <th class="px-6 py-3">SKU</th>
                    <th class="px-6 py-3">Location</th>
                    <th class="px-6 py-3 text-center">Quantity on Hand</th>
                    <th class="px-6 py-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($low_stock_result->num_rows > 0): ?>
                    <?php while($row = $low_stock_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($row['product_name']); ?></td>
                        <td class="px-6 py-4 font-mono"><?php echo htmlspecialchars($row['sku']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($row['location_name']); ?></td>
                        <td class="px-6 py-4 text-center font-bold text-lg text-red-600"><?php echo $row['quantity']; ?></td>
                        <td class="px-6 py-4 text-center">
                            <a href="../procurement/add-po.php?product_id=<?php echo $row['product_id']; ?>" class="font-medium text-indigo-600 hover:underline">
                                Create PO
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No products are currently low on stock.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<?php
require_once __DIR__ . '/../templates/footer.php';
?>