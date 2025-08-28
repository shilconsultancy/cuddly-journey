<?php
// sales/invoice-details.php
require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Sales', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to view this page.</div>');
}

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($invoice_id === 0) {
    die('<div class="glass-card p-8 text-center">Invalid Invoice ID provided.</div>');
}

$message = '';
if (isset($_GET['success']) && $_GET['success'] === 'payment_recorded') {
    $message = 'Payment recorded successfully!';
}

// --- DATA FETCHING ---
$invoice_stmt = $conn->prepare("
    SELECT i.*, c.customer_name, so.order_number, u.full_name as created_by_name
    FROM scs_invoices i
    JOIN scs_customers c ON i.customer_id = c.id
    JOIN scs_sales_orders so ON i.sales_order_id = so.id
    LEFT JOIN scs_users u ON i.created_by = u.id
    WHERE i.id = ?
");
$invoice_stmt->bind_param("i", $invoice_id);
$invoice_stmt->execute();
$invoice = $invoice_stmt->get_result()->fetch_assoc();
$invoice_stmt->close();

if (!$invoice) {
    die('<div class="glass-card p-8 text-center">Invoice not found.</div>');
}

$items_stmt = $conn->prepare("
    SELECT ii.*, p.product_name, p.sku
    FROM scs_invoice_items ii
    JOIN scs_products p ON ii.product_id = p.id
    WHERE ii.invoice_id = ?
");
$items_stmt->bind_param("i", $invoice_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

$payments_stmt = $conn->prepare("
    SELECT ip.*, u.full_name as recorded_by_name
    FROM scs_invoice_payments ip
    LEFT JOIN scs_users u ON ip.recorded_by = u.id
    WHERE ip.invoice_id = ?
    ORDER BY ip.payment_date DESC
");
$payments_stmt->bind_param("i", $invoice_id);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();

$page_title = "Invoice Details: " . htmlspecialchars($invoice['invoice_number']);

$status_colors = [
    'Draft' => 'bg-gray-200 text-gray-800', 'Sent' => 'bg-blue-100 text-blue-800',
    'Partially Paid' => 'bg-yellow-100 text-yellow-800', 'Paid' => 'bg-green-100 text-green-800',
    'Overdue' => 'bg-red-100 text-red-800', 'Void' => 'bg-gray-500 text-white'
];
?>
<title><?php echo $page_title; ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Invoice <span class="text-indigo-600"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span></h2>
        <p class="text-gray-600 mt-1">Generated from Sales Order <?php echo htmlspecialchars($invoice['order_number']); ?>.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <a href="invoices.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">&larr; Back to Invoice List</a>
        <?php if ($invoice['status'] !== 'Paid' && $invoice['status'] !== 'Void' && check_permission('Sales', 'create')): ?>
            <a href="record-payment.php?id=<?php echo $invoice_id; ?>" class="inline-flex items-center px-4 py-2 bg-green-600 text-white font-semibold rounded-lg shadow-sm hover:bg-green-700">Record Payment</a>
        <?php endif; ?>
        <a href="print-invoice.php?id=<?php echo $invoice_id; ?>" target="_blank" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white font-semibold rounded-lg shadow-sm hover:bg-gray-700">Print</a>
    </div>
</div>

<div class="glass-card p-6 lg:p-8">
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md bg-green-100/80 text-green-800">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 pb-6 border-b border-gray-200/50">
        <div><p class="text-sm text-gray-500">Customer</p><p class="font-semibold text-gray-800"><?php echo htmlspecialchars($invoice['customer_name']); ?></p></div>
        <div><p class="text-sm text-gray-500">Invoice Date</p><p class="font-semibold text-gray-800"><?php echo date($app_config['date_format'], strtotime($invoice['invoice_date'])); ?></p></div>
        <div><p class="text-sm text-gray-500">Due Date</p><p class="font-semibold text-gray-800"><?php echo date($app_config['date_format'], strtotime($invoice['due_date'])); ?></p></div>
        <div><p class="text-sm text-gray-500">Created By</p><p class="font-semibold text-gray-800"><?php echo htmlspecialchars($invoice['created_by_name']); ?></p></div>
        <div class="md:col-span-2"><p class="text-sm text-gray-500">Status</p><p><span class="px-3 py-1 text-sm font-semibold rounded-full <?php echo $status_colors[$invoice['status']] ?? 'bg-gray-100 text-gray-800'; ?>"><?php echo htmlspecialchars($invoice['status']); ?></span></p></div>
    </div>

    <h3 class="text-lg font-semibold text-gray-800 mb-4">Invoice Items</h3>
    <div class="overflow-x-auto mb-8">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr><th scope="col" class="px-6 py-3">#</th><th scope="col" class="px-6 py-3">Product</th><th scope="col" class="px-6 py-3 text-center">Qty</th><th scope="col" class="px-6 py-3 text-right">Unit Price</th><th scope="col" class="px-6 py-3 text-right">Total</th></tr>
            </thead>
            <tbody>
                <?php $item_number = 1; while($item = $items_result->fetch_assoc()): ?>
                <tr class="bg-white/50 border-b border-gray-200/50">
                    <td class="px-6 py-4"><?php echo $item_number++; ?></td>
                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td class="px-6 py-4 text-center"><?php echo $item['quantity']; ?></td>
                    <td class="px-6 py-4 text-right"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($item['unit_price'], 2)); ?></td>
                    <td class="px-6 py-4 text-right font-semibold"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($item['line_total'], 2)); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="flex justify-end">
        <div class="w-full md:w-1/3">
            <div class="space-y-3">
                <div class="flex justify-between items-center text-gray-700"><span class="font-semibold">Total Amount</span><span><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($invoice['total_amount'], 2)); ?></span></div>
                <div class="flex justify-between items-center text-green-600"><span class="font-semibold">Amount Paid</span><span>- <?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($invoice['amount_paid'], 2)); ?></span></div>
                <div class="flex justify-between items-center text-xl font-bold text-gray-900 pt-3 border-t border-gray-300/50"><span>Balance Due</span><span><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($invoice['total_amount'] - $invoice['amount_paid'], 2)); ?></span></div>
            </div>
        </div>
    </div>
    
    <div class="mt-10 pt-6 border-t border-gray-200/50">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment History</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                    <tr><th class="px-6 py-3">Date</th><th class="px-6 py-3">Method</th><th class="px-6 py-3 text-right">Amount</th><th class="px-6 py-3">Recorded By</th></tr>
                </thead>
                <tbody>
                    <?php if ($payments_result->num_rows > 0): ?>
                        <?php while($payment = $payments_result->fetch_assoc()): ?>
                        <tr class="bg-white/50 border-b border-gray-200/50">
                            <td class="px-6 py-4"><?php echo date($app_config['date_format'], strtotime($payment['payment_date'])); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                            <td class="px-6 py-4 text-right font-semibold"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($payment['amount'], 2)); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($payment['recorded_by_name']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No payments have been recorded for this invoice.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>