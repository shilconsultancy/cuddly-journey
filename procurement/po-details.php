<?php
// procurement/po-details.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Procurement', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Purchase Order Details - BizManager";
$po_id = $_GET['id'] ?? 0;

// Check for success message from redirect
$message = '';
$message_type = '';
if (isset($_GET['success']) && $_GET['success'] == 'received') {
    $message = "Stock received successfully and PO has been marked as Completed!";
    $message_type = 'success';
}


if (!$po_id) {
    die("No Purchase Order ID provided.");
}

// --- DATA FETCHING ---
$stmt_po = $conn->prepare("
    SELECT 
        po.*, 
        s.supplier_name, s.address as supplier_address, s.email as supplier_email, s.phone as supplier_phone,
        l.location_name as delivery_location_name, l.address as delivery_location_address
    FROM scs_purchase_orders po
    JOIN scs_suppliers s ON po.supplier_id = s.id
    JOIN scs_locations l ON po.location_id = l.id
    WHERE po.id = ?
");
$stmt_po->bind_param("i", $po_id);
$stmt_po->execute();
$po = $stmt_po->get_result()->fetch_assoc();
$stmt_po->close();

if (!$po) {
    die("Purchase Order not found.");
}

$items_stmt = $conn->prepare("
    SELECT poi.quantity, poi.unit_price, p.product_name, p.sku
    FROM scs_purchase_order_items poi
    JOIN scs_products p ON poi.product_id = p.id
    WHERE poi.po_id = ?
");
$items_stmt->bind_param("i", $po_id);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Page Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 no-print">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Purchase Order Details</h2>
        <p class="text-gray-600 mt-1">Viewing PO #<?php echo htmlspecialchars($po['po_number']); ?></p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <a href="purchase-orders.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to PO List
        </a>
        <a href="print-po.php?id=<?php echo $po_id; ?>" target="_blank" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Print PO
        </a>
    </div>
</div>

<!-- Success Message Display -->
<?php if (!empty($message)): ?>
    <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Order Actions Section -->
<div class="mb-6 p-4 glass-card no-print">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Order Actions</h3>
    <div class="flex items-center space-x-4">
        <?php if (check_permission('Procurement', 'edit')): ?>
            <?php if ($po['status'] == 'Draft'): ?>
                <form action="update-po-status.php" method="POST">
                    <input type="hidden" name="po_id" value="<?php echo $po_id; ?>">
                    <input type="hidden" name="new_status" value="Sent">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg shadow-sm hover:bg-blue-700">Mark as Sent</button>
                </form>
            <?php elseif ($po['status'] == 'Sent'): ?>
                <a href="receive-po.php?po_id=<?php echo $po_id; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg shadow-sm hover:bg-green-700">Receive Stock</a>
                <form action="update-po-status.php" method="POST">
                    <input type="hidden" name="po_id" value="<?php echo $po_id; ?>">
                    <input type="hidden" name="new_status" value="Draft">
                    <button type="submit" onclick="return confirm('Are you sure you want to revert this order to a draft?');" class="bg-yellow-500 text-white px-4 py-2 rounded-lg shadow-sm hover:bg-yellow-600">Revert to Draft</button>
                </form>
                <form action="update-po-status.php" method="POST">
                    <input type="hidden" name="po_id" value="<?php echo $po_id; ?>">
                    <input type="hidden" name="new_status" value="Cancelled">
                    <button type="submit" onclick="return confirm('Are you sure you want to cancel this order?');" class="bg-red-600 text-white px-4 py-2 rounded-lg shadow-sm hover:bg-red-700">Cancel Order</button>
                </form>
            <?php else: ?>
                <p class="text-gray-600">This order is <?php echo htmlspecialchars(strtolower($po['status'])); ?> and no further actions can be taken.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="print-container">
    <div class="glass-card p-8 md:p-12">
        <!-- PO Header and other details... (rest of the file is the same) -->
        <div class="flex justify-between items-start border-b border-gray-300/50 pb-8 mb-8">
            <div>
                <?php if (!empty($app_config['company_logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($app_config['company_logo_url']); ?>" alt="Company Logo" class="h-16 mb-4">
                <?php endif; ?>
                <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($app_config['company_name'] ?? ''); ?></h1>
                <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($app_config['company_address'] ?? '')); ?></p>
            </div>
            <div class="text-right">
                <h2 class="text-4xl font-bold text-gray-800">PURCHASE ORDER</h2>
                <p class="text-gray-500 mt-2">#<?php echo htmlspecialchars($po['po_number']); ?></p>
                <p class="text-gray-500">Status: <span class="font-semibold text-blue-600"><?php echo htmlspecialchars($po['status']); ?></span></p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <div class="space-y-1">
                <p class="text-sm text-gray-500">SUPPLIER:</p>
                <p class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($po['supplier_name']); ?></p>
                <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($po['supplier_address'] ?? '')); ?></p>
            </div>
            <div class="space-y-1">
                <p class="text-sm text-gray-500">DELIVER TO:</p>
                <p class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($po['delivery_location_name']); ?></p>
                <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($po['delivery_location_address'] ?? '')); ?></p>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 border-t border-gray-200/50 pt-4">
             <div>
                <p class="text-sm text-gray-500">Order Date</p>
                <p class="font-semibold text-gray-800"><?php echo date($app_config['date_format'], strtotime($po['order_date'])); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Expected Delivery Date</p>
                <p class="font-semibold text-gray-800"><?php echo $po['expected_delivery_date'] ? date($app_config['date_format'], strtotime($po['expected_delivery_date'])) : 'N/A'; ?></p>
            </div>
        </div>

        <h3 class="text-lg font-semibold text-gray-800 mb-4">Order Items</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="text-xs text-gray-800 uppercase bg-gray-100">
                    <tr>
                        <th scope="col" class="px-6 py-3">Product</th>
                        <th scope="col" class="px-6 py-3">SKU</th>
                        <th scope="col" class="px-6 py-3 text-center">Quantity</th>
                        <th scope="col" class="px-6 py-3 text-right">Unit Price</th>
                        <th scope="col" class="px-6 py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $item): ?>
                    <tr class="bg-white border-b">
                        <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($item['sku']); ?></td>
                        <td class="px-6 py-4 text-center"><?php echo htmlspecialchars($item['quantity']); ?></td>
                        <td class="px-6 py-4 text-right"><?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="px-6 py-4 text-right font-semibold"><?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="font-bold">
                    <tr>
                        <td colspan="4" class="px-6 py-3 text-right text-lg">TOTAL</td>
                        <td class="px-6 py-3 text-right text-lg"><?php echo htmlspecialchars($app_config['currency_symbol'] . ' ' . number_format($po['total_amount'], 2)); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="mt-8">
            <h4 class="text-md font-semibold text-gray-800">Notes:</h4>
            <p class="text-gray-600 mt-2"><?php echo htmlspecialchars($po['notes'] ?: 'No additional notes.'); ?></p>
        </div>
        
        <div class="text-center text-xs text-gray-500 mt-12">
            <?php echo htmlspecialchars($app_config['document_footer_text'] ?? ''); ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>