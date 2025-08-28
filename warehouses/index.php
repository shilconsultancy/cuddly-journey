<?php
// warehouses/index.php

require_once __DIR__ . '/../templates/header.php';

// --- SECURITY CHECK ---
if (!check_permission('Warehouses', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Inventory Management - BizManager";

// Determine if the user is an admin who can see all locations
$is_admin = ($_SESSION['user_role_id'] == 1 || $_SESSION['user_role_id'] == 2);
$user_location_id = $_SESSION['location_id'] ?? null;

// --- DATA FETCHING for the list ---
$inventory_sql = "
    SELECT 
        i.quantity,
        p.product_name,
        p.sku,
        l.location_name
    FROM scs_inventory i
    JOIN scs_products p ON i.product_id = p.id
    JOIN scs_locations l ON i.location_id = l.id
";

if (!$is_admin && $user_location_id) {
    // If user is not an admin and has a location, filter by their location_id
    $inventory_sql .= " WHERE i.location_id = " . (int)$user_location_id;
}
$inventory_sql .= " ORDER BY l.location_name, p.product_name ASC";
$inventory_result = $conn->query($inventory_sql);

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Page Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Inventory Overview</h2>
        <p class="text-gray-600 mt-1">View current stock levels across all authorized locations.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <!-- Back to Dashboard Button -->
        <a href="../dashboard.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Dashboard
        </a>
        <?php if (check_permission('Warehouses', 'create')): ?>
        <a href="receive-stock.php" class="inline-flex items-center px-4 py-2 bg-green-600 text-white font-semibold rounded-lg shadow-sm hover:bg-green-700 transition-colors">
            Receive Stock
        </a>
        <a href="transfers.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 transition-colors">
            Manage Transfers
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th scope="col" class="px-6 py-3">Product</th>
                    <th scope="col" class="px-6 py-3">Location</th>
                    <th scope="col" class="px-6 py-3 text-right">Quantity on Hand</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($inventory_result && $inventory_result->num_rows > 0): ?>
                    <?php while($row = $inventory_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4 font-medium">
                            <div class="font-semibold"><?php echo htmlspecialchars($row['product_name']); ?></div>
                            <div class="text-xs text-gray-500">SKU: <?php echo htmlspecialchars($row['sku']); ?></div>
                        </td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($row['location_name']); ?></td>
                        <td class="px-6 py-4 text-right font-bold text-lg <?php echo ($row['quantity'] <= 0) ? 'text-red-500' : 'text-gray-800'; ?>">
                            <?php echo htmlspecialchars($row['quantity']); ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                     <tr class="bg-white/50 border-b border-gray-200/50">
                        <td colspan="3" class="px-6 py-4 text-center text-gray-500">No inventory records found. Use "Receive Stock" or "Manage Transfers" to add inventory.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>