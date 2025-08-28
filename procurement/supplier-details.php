<?php
// procurement/supplier-details.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Procurement', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Supplier Details - BizManager";
$supplier_id = $_GET['id'] ?? 0;

if (!$supplier_id) {
    die("No supplier ID provided.");
}

// --- DATA FETCHING ---
$stmt_supplier = $conn->prepare("SELECT * FROM scs_suppliers WHERE id = ?");
$stmt_supplier->bind_param("i", $supplier_id);
$stmt_supplier->execute();
$supplier = $stmt_supplier->get_result()->fetch_assoc();
$stmt_supplier->close();

if (!$supplier) {
    die("Supplier not found.");
}

$stmt_pos = $conn->prepare("SELECT id, po_number, order_date, total_amount, status FROM scs_purchase_orders WHERE supplier_id = ? ORDER BY order_date DESC");
$stmt_pos->bind_param("i", $supplier_id);
$stmt_pos->execute();
$purchase_orders = $stmt_pos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_pos->close();

// --- CALCULATE FINANCIAL SUMMARY ---
$total_business = 0;
foreach ($purchase_orders as $po) {
    $total_business += $po['total_amount'];
}
$total_paid = 0.00; // Placeholder
$balance_due = $total_business - $total_paid;

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Page Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Supplier Statement</h2>
        <p class="text-gray-600 mt-1">Viewing history for: <?php echo htmlspecialchars($supplier['supplier_name']); ?></p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <a href="suppliers.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to Suppliers
        </a>
        <!-- UPDATED PRINT BUTTON -->
        <a href="print-statement.php?id=<?php echo $supplier_id; ?>" target="_blank" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Print Statement
        </a>
    </div>
</div>

<div class="glass-card p-8 md:p-12">
    <!-- Statement Header -->
    <div class="flex justify-between items-start border-b border-gray-300/50 pb-8 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($app_config['company_name']); ?></h1>
            <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($app_config['company_address'])); ?></p>
        </div>
        <div class="text-right">
            <h2 class="text-2xl font-semibold text-gray-700">Supplier Statement</h2>
            <p class="text-gray-500">Date: <?php echo date($app_config['date_format']); ?></p>
        </div>
    </div>

    <!-- Supplier Info -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div>
            <p class="text-sm text-gray-500">STATEMENT FOR:</p>
            <p class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($supplier['supplier_name']); ?></p>
            <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($supplier['address'])); ?></p>
            <p class="text-gray-600"><?php echo htmlspecialchars($supplier['email']); ?></p>
            <p class="text-gray-600"><?php echo htmlspecialchars($supplier['phone']); ?></p>
        </div>
        <div class="glass-card p-6 rounded-xl text-right bg-indigo-50/50">
            <p class="text-sm text-gray-500">Total Business</p>
            <p class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($app_config['currency_symbol'] . ' ' . number_format($total_business, 2)); ?></p>
            <hr class="my-2">
            <p class="text-sm text-gray-500">Total Paid</p>
            <p class="text-xl font-semibold text-green-600"><?php echo htmlspecialchars($app_config['currency_symbol'] . ' ' . number_format($total_paid, 2)); ?></p>
            <p class="text-sm text-gray-500 mt-2">Balance Due</p>
            <p class="text-xl font-semibold text-red-600"><?php echo htmlspecialchars($app_config['currency_symbol'] . ' ' . number_format($balance_due, 2)); ?></p>
        </div>
    </div>

    <!-- Transaction List -->
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Transaction History</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th scope="col" class="px-6 py-3">Date</th>
                    <th scope="col" class="px-6 py-3">Transaction Details</th>
                    <th scope="col" class="px-6 py-3">Type</th>
                    <th scope="col" class="px-6 py-3 text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($purchase_orders)): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">No transactions found for this supplier.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach($purchase_orders as $po): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4"><?php echo date($app_config['date_format'], strtotime($po['order_date'])); ?></td>
                        <td class="px-6 py-4 font-medium">Purchase Order #<?php echo htmlspecialchars($po['po_number']); ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs font-semibold text-blue-800 bg-blue-100 rounded-full">
                                Purchase
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right font-semibold"><?php echo htmlspecialchars($app_config['currency_symbol'] . ' ' . number_format($po['total_amount'], 2)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- Placeholder for payment rows -->
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td colspan="4" class="px-6 py-4 text-center text-gray-400 italic">Payment history will appear here once the Accounts module is built.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>