<?php
// procurement/bills.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Procurement', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Supplier Bills - BizManager";

// --- MESSAGE HANDLING ---
$message = '';
$message_type = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'created') {
        $message = "Supplier bill created successfully!";
        $message_type = 'success';
    }
}

// --- DATA FETCHING ---
$bills_result = $conn->query("
    SELECT 
        b.id, b.bill_number, b.bill_date, b.due_date, b.total_amount, b.status, b.amount_paid,
        s.supplier_name,
        po.po_number
    FROM scs_supplier_bills b
    JOIN scs_suppliers s ON b.supplier_id = s.id
    LEFT JOIN scs_purchase_orders po ON b.po_id = po.id
    ORDER BY b.bill_date DESC, b.id DESC
");

$status_colors = [
    'Unpaid' => 'bg-red-100 text-red-800',
    'Partially Paid' => 'bg-yellow-100 text-yellow-800',
    'Paid' => 'bg-green-100 text-green-800'
];

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Supplier Bills</h2>
        <p class="text-gray-600 mt-1">Track and manage all bills received from suppliers.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to Procurement
        </a>
        <?php if (check_permission('Procurement', 'create')): ?>
        <a href="bill-form.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Enter New Bill
        </a>
        <?php endif; ?>
    </div>
</div>

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
                    <th scope="col" class="px-6 py-3">Bill #</th>
                    <th scope="col" class="px-6 py-3">Supplier</th>
                    <th scope="col" class="px-6 py-3">Bill Date</th>
                    <th scope="col" class="px-6 py-3">Due Date</th>
                    <th scope="col" class="px-6 py-3 text-right">Amount</th>
                    <th scope="col" class="px-6 py-3">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while($bill = $bills_result->fetch_assoc()): ?>
                <tr class="bg-white/50 border-b border-gray-200/50 hover:bg-gray-50/50">
                    <td class="px-6 py-4 font-semibold text-gray-800">
                        <?php echo htmlspecialchars($bill['bill_number']); ?>
                        <?php if ($bill['po_number']): ?>
                            <div class="text-xs text-gray-500">PO: <?php echo htmlspecialchars($bill['po_number']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4"><?php echo htmlspecialchars($bill['supplier_name']); ?></td>
                    <td class="px-6 py-4"><?php echo date($app_config['date_format'], strtotime($bill['bill_date'])); ?></td>
                    <td class="px-6 py-4"><?php echo date($app_config['date_format'], strtotime($bill['due_date'])); ?></td>
                    <td class="px-6 py-4 text-right font-semibold font-mono"><?php echo htmlspecialchars($app_config['currency_symbol'] . ' ' . number_format($bill['total_amount'], 2)); ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_colors[$bill['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                            <?php echo htmlspecialchars($bill['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>