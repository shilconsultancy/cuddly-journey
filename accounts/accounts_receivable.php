<?php
// accounts/accounts_receivable.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Accounts Receivable Aging - BizManager";

// --- DATA FETCHING ---
$today = date('Y-m-d');

$sql = "
    SELECT 
        i.id,
        i.invoice_number,
        i.invoice_date,
        i.due_date,
        i.total_amount,
        i.amount_paid,
        (i.total_amount - i.amount_paid) as balance_due,
        c.customer_name,
        DATEDIFF('$today', i.due_date) as days_overdue
    FROM scs_invoices i
    JOIN scs_customers c ON i.customer_id = c.id
    WHERE i.status IN ('Sent', 'Partially Paid', 'Overdue') AND (i.total_amount - i.amount_paid) > 0.01
    ORDER BY c.customer_name, i.due_date ASC
";

$invoices_result = $conn->query($sql);

// Initialize aging buckets
$aging = [
    'current' => ['total' => 0, 'invoices' => []],
    '1-30' => ['total' => 0, 'invoices' => []],
    '31-60' => ['total' => 0, 'invoices' => []],
    '61-90' => ['total' => 0, 'invoices' => []],
    '90+' => ['total' => 0, 'invoices' => []]
];
$total_receivables = 0;

if ($invoices_result) {
    while ($invoice = $invoices_result->fetch_assoc()) {
        $total_receivables += $invoice['balance_due'];
        $days_overdue = (int)$invoice['days_overdue'];

        if ($days_overdue <= 0) {
            $aging['current']['invoices'][] = $invoice;
            $aging['current']['total'] += $invoice['balance_due'];
        } elseif ($days_overdue >= 1 && $days_overdue <= 30) {
            $aging['1-30']['invoices'][] = $invoice;
            $aging['1-30']['total'] += $invoice['balance_due'];
        } elseif ($days_overdue >= 31 && $days_overdue <= 60) {
            $aging['31-60']['invoices'][] = $invoice;
            $aging['31-60']['total'] += $invoice['balance_due'];
        } elseif ($days_overdue >= 61 && $days_overdue <= 90) {
            $aging['61-90']['invoices'][] = $invoice;
            $aging['61-90']['total'] += $invoice['balance_due'];
        } else {
            $aging['90+']['invoices'][] = $invoice;
            $aging['90+']['total'] += $invoice['balance_due'];
        }
    }
}

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Accounts Receivable Aging</h2>
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
                    <td class="px-4 py-3 text-lg font-bold text-indigo-600"><?php echo number_format($total_receivables, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h3 class="text-lg font-semibold text-gray-800 mt-8 mb-4">Details</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th class="px-6 py-3">Customer</th>
                    <th class="px-6 py-3">Invoice #</th>
                    <th class="px-6 py-3">Due Date</th>
                    <th class="px-6 py-3 text-center">Days Overdue</th>
                    <th class="px-6 py-3 text-right">Balance Due</th>
                </tr>
            </thead>
            <tbody>
                <?php mysqli_data_seek($invoices_result, 0); ?>
                <?php if ($invoices_result->num_rows > 0): ?>
                    <?php while($invoice = $invoices_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                        <td class="px-6 py-4">
                            <a href="../sales/invoice-details.php?id=<?php echo $invoice['id']; ?>" class="font-medium text-indigo-600 hover:underline">
                                <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                            </a>
                        </td>
                        <td class="px-6 py-4"><?php echo date($app_config['date_format'], strtotime($invoice['due_date'])); ?></td>
                        <td class="px-6 py-4 text-center font-bold <?php echo ($invoice['days_overdue'] > 0) ? 'text-red-500' : 'text-green-600'; ?>">
                            <?php echo max(0, $invoice['days_overdue']); ?>
                        </td>
                        <td class="px-6 py-4 text-right font-semibold font-mono"><?php echo number_format($invoice['balance_due'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No outstanding invoices.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>