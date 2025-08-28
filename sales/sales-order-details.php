<?php
// sales/sales-order-details.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Sales', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to view this page.</div>');
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id === 0) {
    die('<div class="glass-card p-8 text-center">Invalid Order ID provided.</div>');
}

// --- DATA FETCHING ---

// Fetch the main order details
$order_stmt = $conn->prepare("
    SELECT 
        so.*,
        c.customer_name,
        c.address as customer_address,
        ct.contact_name,
        l.location_name,
        u.full_name as created_by_name
    FROM scs_sales_orders so
    JOIN scs_customers c ON so.customer_id = c.id
    LEFT JOIN scs_contacts ct ON so.contact_id = ct.id
    LEFT JOIN scs_locations l ON so.location_id = l.id
    LEFT JOIN scs_users u ON so.created_by = u.id
    WHERE so.id = ?
");
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
$order = $order_result->fetch_assoc();
$order_stmt->close();

if (!$order) {
    die('<div class="glass-card p-8 text-center">Sales order not found.</div>');
}

// Fetch the line items for the order
$items_stmt = $conn->prepare("
    SELECT soi.*, p.product_name, p.sku
    FROM scs_sales_order_items soi
    JOIN scs_products p ON soi.product_id = p.id
    WHERE soi.sales_order_id = ?
");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

// --- NEW: Check if an invoice already exists for this order ---
$invoice_check_stmt = $conn->prepare("SELECT id FROM scs_invoices WHERE sales_order_id = ?");
$invoice_check_stmt->bind_param("i", $order_id);
$invoice_check_stmt->execute();
$existing_invoice = $invoice_check_stmt->get_result()->fetch_assoc();
$invoice_check_stmt->close();

$page_title = "Order Details: " . htmlspecialchars($order['order_number']);

$status_colors = [
    'Draft' => 'bg-gray-200 text-gray-800', 'Confirmed' => 'bg-blue-100 text-blue-800',
    'Processing' => 'bg-yellow-100 text-yellow-800', 'Shipped' => 'bg-indigo-100 text-indigo-800',
    'Completed' => 'bg-green-100 text-green-800', 'Cancelled' => 'bg-red-100 text-red-800'
];
?>

<title><?php echo $page_title; ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Sales Order <span class="text-indigo-600"><?php echo htmlspecialchars($order['order_number']); ?></span></h2>
        <p class="text-gray-600 mt-1">Details for sales order created on <?php echo date($app_config['date_format'], strtotime($order['order_date'])); ?>.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <a href="sales-orders.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to Order List
        </a>
        
        <?php if ($existing_invoice): ?>
            <a href="../coming-soon.php?id=<?php echo $existing_invoice['id']; ?>" class="inline-flex items-center px-4 py-2 bg-yellow-500 text-white font-semibold rounded-lg shadow-sm hover:bg-yellow-600">
                View Invoice
            </a>
        <?php elseif ($order['status'] !== 'Draft' && $order['status'] !== 'Cancelled' && check_permission('Sales', 'create')): ?>
            <a href="generate-invoice.php?id=<?php echo $order_id; ?>" class="inline-flex items-center px-4 py-2 bg-green-600 text-white font-semibold rounded-lg shadow-sm hover:bg-green-700" onclick="return confirm('This will generate a new invoice for this sales order. Are you sure?')">
                Generate Invoice
            </a>
        <?php endif; ?>

        <?php if (check_permission('Sales', 'edit')): ?>
        <a href="sales-order-form.php?id=<?php echo $order_id; ?>" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700">
            Edit Order
        </a>
        <?php endif; ?>
        <a href="print-sales-order.php?id=<?php echo $order_id; ?>" target="_blank" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white font-semibold rounded-lg shadow-sm hover:bg-gray-700">
            Print
        </a>
    </div>
</div>

<div class="glass-card p-6 lg:p-8">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 pb-6 border-b border-gray-200/50">
        <div><p class="text-sm text-gray-500">Customer</p><p class="font-semibold text-gray-800"><?php echo htmlspecialchars($order['customer_name']); ?></p></div>
        <div><p class="text-sm text-gray-500">Contact Person</p><p class="font-semibold text-gray-800"><?php echo htmlspecialchars($order['contact_name'] ?? 'N/A'); ?></p></div>
        <div><p class="text-sm text-gray-500">Status</p><p><span class="px-3 py-1 text-sm font-semibold rounded-full <?php echo $status_colors[$order['status']] ?? 'bg-gray-100 text-gray-800'; ?>"><?php echo htmlspecialchars($order['status']); ?></span></p></div>
        <div><p class="text-sm text-gray-500">Order Date</p><p class="font-semibold text-gray-800"><?php echo date($app_config['date_format'], strtotime($order['order_date'])); ?></p></div>
        <div><p class="text-sm text-gray-500">Fulfilled From</p><p class="font-semibold text-gray-800"><?php echo htmlspecialchars($order['location_name'] ?? 'N/A'); ?></p></div>
        <div><p class="text-sm text-gray-500">Created By</p><p class="font-semibold text-gray-800"><?php echo htmlspecialchars($order['created_by_name']); ?></p></div>
    </div>
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Order Items</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr><th scope="col" class="px-6 py-3">#</th><th scope="col" class="px-6 py-3">Product Name</th><th scope="col" class="px-6 py-3">SKU</th><th scope="col" class="px-6 py-3 text-center">Quantity</th><th scope="col" class="px-6 py-3 text-right">Unit Price</th><th scope="col" class="px-6 py-3 text-right">Line Total</th></tr>
            </thead>
            <tbody>
                <?php $item_number = 1; while($item = $items_result->fetch_assoc()): ?>
                <tr class="bg-white/50 border-b border-gray-200/50">
                    <td class="px-6 py-4"><?php echo $item_number++; ?></td>
                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td class="px-6 py-4"><?php echo htmlspecialchars($item['sku']); ?></td>
                    <td class="px-6 py-4 text-center"><?php echo $item['quantity']; ?></td>
                    <td class="px-6 py-4 text-right"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($item['unit_price'], 2)); ?></td>
                    <td class="px-6 py-4 text-right font-semibold"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($item['line_total'], 2)); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 mt-8">
        <div class="md:col-start-2">
            <div class="bg-gray-50/50 rounded-lg p-6">
                <div class="space-y-3">
                    <div class="flex justify-between items-center text-gray-700"><span>Subtotal</span><span class="font-semibold"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($order['subtotal'], 2)); ?></span></div>
                    <div class="flex justify-between items-center text-gray-700"><span>Tax (0%)</span><span class="font-semibold"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($order['tax_amount'], 2)); ?></span></div>
                    <div class="flex justify-between items-center text-xl font-bold text-gray-900 pt-3 border-t border-gray-300/50"><span>Grand Total</span><span><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($order['total_amount'], 2)); ?></span></div>
                </div>
            </div>
        </div>
    </div>
    <?php if(!empty($order['notes'])): ?>
    <div class="mt-8">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Notes</h3>
        <div class="bg-gray-50/50 rounded-lg p-4 text-gray-600 text-sm"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></div>
    </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>