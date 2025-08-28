<?php
// warehouses/transfer-details.php

require_once __DIR__ . '/../templates/header.php';

$page_title = "Transfer Details - BizManager";
$transfer_id = $_GET['id'] ?? 0;

if (!$transfer_id) {
    die("No transfer ID provided.");
}

// --- DATA FETCHING ---
// Fetch main transfer details
$stmt = $conn->prepare("
    SELECT t.*, from_loc.location_name as from_name, to_loc.location_name as to_name, u.full_name as creator_name
    FROM scs_stock_transfers t
    JOIN scs_locations from_loc ON t.from_location_id = from_loc.id
    JOIN scs_locations to_loc ON t.to_location_id = to_loc.id
    LEFT JOIN scs_users u ON t.created_by = u.id
    WHERE t.id = ?
");
$stmt->bind_param("i", $transfer_id);
$stmt->execute();
$transfer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transfer) {
    die("Transfer not found.");
}

// Fetch items in this transfer
$items_result = $conn->prepare("
    SELECT ti.quantity, p.product_name, p.sku
    FROM scs_stock_transfer_items ti
    JOIN scs_products p ON ti.product_id = p.id
    WHERE ti.transfer_id = ?
");
$items_result->bind_param("i", $transfer_id);
$items_result->execute();
$items = $items_result->get_result();
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Page Header -->
<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Transfer Details: <?php echo htmlspecialchars($transfer['transfer_no']); ?></h2>
    <a href="transfers.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to All Transfers
    </a>
</div>

<div class="glass-card p-8">
    <!-- Transfer Info -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 border-b border-gray-200/50 pb-6">
        <div>
            <p class="text-sm text-gray-500">From Location</p>
            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($transfer['from_name']); ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500">To Location</p>
            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($transfer['to_name']); ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500">Date Created</p>
            <p class="font-semibold text-gray-800"><?php echo date($app_config['date_format'], strtotime($transfer['created_at'])); ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500">Created By</p>
            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($transfer['creator_name']); ?></p>
        </div>
        <div class="md:col-span-2">
            <p class="text-sm text-gray-500">Notes</p>
            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($transfer['notes'] ?: 'N/A'); ?></p>
        </div>
    </div>

    <!-- Items List -->
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Items Transferred</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th scope="col" class="px-6 py-3">Product</th>
                    <th scope="col" class="px-6 py-3">SKU</th>
                    <th scope="col" class="px-6 py-3 text-right">Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php while($item = $items->fetch_assoc()): ?>
                <tr class="bg-white/50 border-b border-gray-200/50">
                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td class="px-6 py-4"><?php echo htmlspecialchars($item['sku']); ?></td>
                    <td class="px-6 py-4 text-right font-semibold"><?php echo htmlspecialchars($item['quantity']); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>