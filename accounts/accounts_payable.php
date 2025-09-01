<?php
// accounts/accounts_payable.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Accounts Payable Aging - BizManager";

// --- DATA FETCHING ---
$today = date('Y-m-d');

$sql = "
    SELECT 
        b.id,
        b.bill_number,
        b.bill_date,
        b.due_date,
        b.total_amount,
        b.amount_paid,
        (b.total_amount - b.amount_paid) as balance_due,
        s.supplier_name,
        DATEDIFF('$today', b.due_date) as days_overdue
    FROM scs_supplier_bills b
    JOIN scs_suppliers s ON b.supplier_id = s.id
    WHERE b.status IN ('Unpaid', 'Partially Paid') AND (b.total_amount - b.amount_paid) > 0.01
    ORDER BY s.supplier_name, b.due_date ASC
";

$bills_result = $conn->query($sql);

// Initialize aging buckets
$aging = [
    'current' => ['total' => 0],
    '1-30' => ['total' => 0],
    '31-60' => ['total' => 0],
    '61-90' => ['total' => 0],
    '90+' => ['total' => 0]
];
$total_payables = 0;

if ($bills_result) {
    while ($bill = $bills_result->fetch_assoc()) {
        $total_payables += $bill['balance_due'];
        $days_overdue = (int)$bill['days_overdue'];

        if ($days_overdue <= 0) {
            $aging['current']['total'] += $bill['balance_due'];
        } elseif ($days_overdue >= 1 && $days_overdue <= 30) {
            $aging['1-30']['total'] += $bill['balance_due'];
        } elseif ($days_overdue >= 31 && $days_overdue <= 60) {
            $aging['31-60']['total'] += $bill['balance_due'];
        } elseif ($days_overdue >= 61 && $days_overdue <= 90) {
            $aging['61-90']['total'] += $bill['balance_due'];
        } else {
            $aging['90+']['total'] += $bill['balance_due'];
        }
    }
}

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Accounts Payable Aging</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Accounts
    </a>
</div>

<div class="glass-card p-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Summary</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-center text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th class="px-4 py-3">Current</th>
                    <th class="px-4 py-3">1-30 Days Overdue</th>
                    <th class="px-4 py-3">31-60 Days Overdue</th>
                    <th class="px-4 py-3">61-90 Days Overdue</th>
                    <th class="px-4 py-3">90+ Days Overdue</th>
                    <th class="px-4 py-3">Total Due</th>
                </tr>
            </thead>
            <tbody class="font-semibold">
                <tr class="bg-white/50">
                    <td class="px-4 py-3"><?php echo number_format($aging['current']['total'], 2); ?></td>
                    <td class="px-4 py-3"><?php echo number_format($aging['1-30']['total'], 2); ?></td>
                    <td class="px-4 py-3"><?php echo number_format($aging['31-60']['total'], 2); ?></td>
                    <td class="px-4 py-3"><?php echo number_format($aging['61-90']['total'], 2); ?></td>
                    <td class="px-4 py-3"><?php echo number_format($aging['90+']['total'], 2); ?></td>
                    <td class="px-4 py-3 text-lg font-bold text-red-600"><?php echo number_format($total_payables, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3 class="text-lg font-semibold text-gray-800 mt-8 mb-4">Details</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th class="px-6 py-3">Supplier</th>
                    <th class="px-6 py-3">Bill #</th>
                    <th class="px-6 py-3">Due Date</th>
                    <th class="px-6 py-3 text-center">Days Overdue</th>
                    <th class="px-6 py-3 text-right">Balance Due</th>
                    <th class="px-6 py-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php mysqli_data_seek($bills_result, 0); ?>
                <?php if ($bills_result->num_rows > 0): ?>
                    <?php while($bill = $bills_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($bill['supplier_name']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($bill['bill_number']); ?></td>
                        <td class="px-6 py-4"><?php echo date($app_config['date_format'], strtotime($bill['due_date'])); ?></td>
                        <td class="px-6 py-4 text-center font-bold <?php echo ($bill['days_overdue'] > 0) ? 'text-red-500' : 'text-green-600'; ?>">
                            <?php echo max(0, $bill['days_overdue']); ?>
                        </td>
                        <td class="px-6 py-4 text-right font-semibold font-mono"><?php echo number_format($bill['balance_due'], 2); ?></td>
                        <td class="px-6 py-4 text-center">
                            <a href="pay_bill.php?id=<?php echo $bill['id']; ?>" class="font-medium text-indigo-600 hover:underline">
                                Pay Bill
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No outstanding bills.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>