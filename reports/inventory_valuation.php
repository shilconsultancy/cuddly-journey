<?php
// reports/inventory_valuation.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Reports', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Inventory Valuation Report - BizManager";

// --- DATA FETCHING ---
$location_filter = isset($_GET['location_id']) ? (int)$_GET['location_id'] : 0;

$sql = "
    SELECT 
        p.product_name,
        p.sku,
        p.cost_price,
        l.location_name,
        i.quantity,
        (i.quantity * p.cost_price) as total_value
    FROM scs_inventory i
    JOIN scs_products p ON i.product_id = p.id
    JOIN scs_locations l ON i.location_id = l.id
    WHERE i.quantity > 0
";

if ($location_filter > 0) {
    $sql .= " AND i.location_id = " . $location_filter;
}

$sql .= " ORDER BY l.location_name, p.product_name ASC";

$valuation_result = $conn->query($sql);
$locations_result = $conn->query("SELECT id, location_name FROM scs_locations ORDER BY location_name ASC");

$grand_total_value = 0;
// Calculate grand total separately to avoid issues with database pointers
if ($valuation_result && $valuation_result->num_rows > 0) {
    $data = $valuation_result->fetch_all(MYSQLI_ASSOC);
    foreach ($data as $row) {
        $grand_total_value += $row['total_value'];
    }
    // Reset the pointer by re-assigning the fetched data
    $valuation_result->data_seek(0);
}


?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Inventory Valuation Report</h2>
        <p class="text-gray-600 mt-1">Calculates the total cost value of your current stock.</p>
    </div>
    <a href="inventory_reports.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Inventory Reports
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="glass-card p-4">
        <form action="inventory_valuation.php" method="GET" class="flex items-end space-x-4">
            <div>
                 <label for="location_id" class="block text-sm font-medium text-gray-700">Filter by Location</label>
                 <select name="location_id" id="location_id" class="form-input mt-1 block w-full">
                    <option value="0">All Locations</option>
                    <?php while($loc = $locations_result->fetch_assoc()): ?>
                        <option value="<?php echo $loc['id']; ?>" <?php if($location_filter == $loc['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($loc['location_name']); ?>
                        </option>
                    <?php endwhile; ?>
                 </select>
            </div>
            <div>
                <button type="submit" class="inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Filter</button>
            </div>
        </form>
    </div>
    <div class="glass-card p-4 flex flex-col justify-center text-center">
        <h4 class="text-sm font-medium text-gray-600">Total Inventory Value</h4>
        <p class="text-3xl font-bold text-green-600 mt-1"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($grand_total_value, 2)); ?></p>
    </div>
</div>


<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th class="px-6 py-3">Product</th>
                    <th class="px-6 py-3">SKU</th>
                    <th class="px-6 py-3">Location</th>
                    <th class="px-6 py-3 text-center">Quantity</th>
                    <th class="px-6 py-3 text-right">Cost Price (WAC)</th>
                    <th class="px-6 py-3 text-right">Total Value</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($valuation_result->num_rows > 0): ?>
                    <?php mysqli_data_seek($valuation_result, 0); // Reset pointer again for looping ?>
                    <?php while($row = $valuation_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($row['product_name']); ?></td>
                        <td class="px-6 py-4 font-mono"><?php echo htmlspecialchars($row['sku']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($row['location_name']); ?></td>
                        <td class="px-6 py-4 text-center font-bold text-lg"><?php echo $row['quantity']; ?></td>
                        <td class="px-6 py-4 text-right font-mono"><?php echo number_format($row['cost_price'], 2); ?></td>
                        <td class="px-6 py-4 text-right font-mono font-semibold text-green-700"><?php echo number_format($row['total_value'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No stock found for the selected criteria.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
             <tfoot class="font-bold text-gray-800 bg-gray-100/50">
                <tr>
                    <td colspan="5" class="px-6 py-3 text-right text-lg">Grand Total:</td>
                    <td class="px-6 py-3 text-right text-lg font-mono text-green-600"><?php echo number_format($grand_total_value, 2); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>


<?php
require_once __DIR__ . '/../templates/footer.php';
?>