<?php
// sales/quotation-details.php
require_once __DIR__ . '/../templates/header.php';
if (!check_permission('Sales', 'view')) { die('<div class="glass-card p-8 text-center">You do not have permission to view this page.</div>'); }
$quote_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($quote_id === 0) { die('<div class="glass-card p-8 text-center">Invalid Quotation ID provided.</div>'); }

// --- DATA FETCHING ---
$quote_stmt = $conn->prepare("
    SELECT q.*, c.customer_name, ct.contact_name, u.full_name as created_by_name
    FROM scs_quotations q
    JOIN scs_customers c ON q.customer_id = c.id
    LEFT JOIN scs_contacts ct ON q.contact_id = ct.id
    LEFT JOIN scs_users u ON q.created_by = u.id
    WHERE q.id = ?
");
$quote_stmt->bind_param("i", $quote_id);
$quote_stmt->execute();
$quote = $quote_stmt->get_result()->fetch_assoc();
$quote_stmt->close();
if (!$quote) { die('<div class="glass-card p-8 text-center">Quotation not found.</div>'); }
$items_stmt = $conn->prepare("
    SELECT qi.*, p.product_name, p.sku FROM scs_quotation_items qi
    JOIN scs_products p ON qi.product_id = p.id WHERE qi.quotation_id = ?
");
$items_stmt->bind_param("i", $quote_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$page_title = "Quotation Details: " . htmlspecialchars($quote['quote_number']);
$status_colors = [
    'Draft' => 'bg-gray-200 text-gray-800', 'Sent' => 'bg-blue-100 text-blue-800',
    'Accepted' => 'bg-green-100 text-green-800', 'Rejected' => 'bg-red-100 text-red-800'
];
?>
<title><?php echo $page_title; ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Quotation <span class="text-indigo-600"><?php echo htmlspecialchars($quote['quote_number']); ?></span></h2>
        <p class="text-gray-600 mt-1">Details for quotation created on <?php echo date($app_config['date_format'], strtotime($quote['quote_date'])); ?>.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <a href="quotations.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to Quotation List
        </a>
        
        <?php if ($quote['status'] == 'Accepted' && empty($quote['converted_to_order_id']) && check_permission('Sales', 'create')): ?>
             <a href="convert-quote.php?id=<?php echo $quote_id; ?>" class="inline-flex items-center px-4 py-2 bg-green-600 text-white font-semibold rounded-lg shadow-sm hover:bg-green-700" onclick="return confirm('This will create a new Sales Order from this quotation. Are you sure?')">
                Convert to Sales Order
            </a>
        <?php elseif (!empty($quote['converted_to_order_id'])): ?>
            <a href="sales-order-details.php?id=<?php echo $quote['converted_to_order_id']; ?>" class="inline-flex items-center px-4 py-2 bg-yellow-500 text-white font-semibold rounded-lg shadow-sm hover:bg-yellow-600">
                View Related Order
            </a>
        <?php endif; ?>

        <?php if (check_permission('Sales', 'edit')): ?>
        <a href="quotation-form.php?id=<?php echo $quote_id; ?>" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700">
            Edit Quotation
        </a>
        <?php endif; ?>
        <a href="print-quotation.php?id=<?php echo $quote_id; ?>" target="_blank" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white font-semibold rounded-lg shadow-sm hover:bg-gray-700">
            Print
        </a>
    </div>
</div>

<div class="glass-card p-6 lg:p-8">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 pb-6 border-b border-gray-200/50">
        <div><p class="text-sm text-gray-500">Customer</p><p class="font-semibold text-gray-800"><?php echo htmlspecialchars($quote['customer_name']); ?></p></div>
        <div><p class="text-sm text-gray-500">Contact Person</p><p class="font-semibold text-gray-800"><?php echo htmlspecialchars($quote['contact_name'] ?? 'N/A'); ?></p></div>
        <div><p class="text-sm text-gray-500">Status</p><p><span class="px-3 py-1 text-sm font-semibold rounded-full <?php echo $status_colors[$quote['status']] ?? 'bg-gray-100 text-gray-800'; ?>"><?php echo htmlspecialchars($quote['status']); ?></span></p></div>
        <div><p class="text-sm text-gray-500">Quote Date</p><p class="font-semibold text-gray-800"><?php echo date($app_config['date_format'], strtotime($quote['quote_date'])); ?></p></div>
        <div><p class="text-sm text-gray-500">Expiry Date</p><p class="font-semibold text-gray-800"><?php echo $quote['expiry_date'] ? date($app_config['date_format'], strtotime($quote['expiry_date'])) : 'N/A'; ?></p></div>
        <div><p class="text-sm text-gray-500">Created By</p><p class="font-semibold text-gray-800"><?php echo htmlspecialchars($quote['created_by_name']); ?></p></div>
    </div>
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Quotation Items</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700"><thead class="text-xs text-gray-800 uppercase bg-gray-50/50"><tr><th scope="col" class="px-6 py-3">#</th><th scope="col" class="px-6 py-3">Product Name</th><th scope="col" class="px-6 py-3">SKU</th><th scope="col" class="px-6 py-3 text-center">Quantity</th><th scope="col" class="px-6 py-3 text-right">Unit Price</th><th scope="col" class="px-6 py-3 text-right">Line Total</th></tr></thead>
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
                    <div class="flex justify-between items-center text-gray-700"><span>Subtotal</span><span class="font-semibold"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($quote['subtotal'], 2)); ?></span></div>
                    <div class="flex justify-between items-center text-gray-700"><span>Tax (0%)</span><span class="font-semibold"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($quote['tax_amount'], 2)); ?></span></div>
                    <div class="flex justify-between items-center text-xl font-bold text-gray-900 pt-3 border-t border-gray-300/50"><span>Grand Total</span><span><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($quote['total_amount'], 2)); ?></span></div>
                </div>
            </div>
        </div>
    </div>
    <?php if(!empty($quote['notes'])): ?>
    <div class="mt-8">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Notes / Terms</h3>
        <div class="bg-gray-50/50 rounded-lg p-4 text-gray-600 text-sm"><?php echo nl2br(htmlspecialchars($quote['notes'])); ?></div>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>