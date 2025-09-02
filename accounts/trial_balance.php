<?php
// accounts/trial_balance.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Trial Balance - BizManager";

// --- DATA FETCHING ---
$sql = "
    SELECT 
        coa.id,
        coa.account_code,
        coa.account_name,
        coa.account_type,
        (SELECT COALESCE(SUM(jei.debit_amount), 0) FROM scs_journal_entry_items jei WHERE jei.account_id = coa.id) as total_debits,
        (SELECT COALESCE(SUM(jei.credit_amount), 0) FROM scs_journal_entry_items jei WHERE jei.account_id = coa.id) as total_credits
    FROM 
        scs_chart_of_accounts coa
    WHERE 
        coa.is_active = 1
    ORDER BY 
        coa.account_code ASC
";

$accounts_result = $conn->query($sql);

$total_debit_balance = 0;
$total_credit_balance = 0;

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Trial Balance</h2>
        <p class="text-gray-600 mt-1">As of <?php echo date($app_config['date_format']); ?></p>
    </div>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Accounts
    </a>
</div>

<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th class="px-6 py-3">Code</th>
                    <th class="px-6 py-3">Account</th>
                    <th class="px-6 py-3 text-right">Debit</th>
                    <th class="px-6 py-3 text-right">Credit</th>
                </tr>
            </thead>
            <tbody>
                <?php while($account = $accounts_result->fetch_assoc()): 
                    $balance = $account['total_debits'] - $account['total_credits'];
                    $debit_amount = 0;
                    $credit_amount = 0;

                    // Assets and Expenses normally have debit balances
                    if (in_array($account['account_type'], ['Asset', 'Expense'])) {
                        if ($balance >= 0) {
                            $debit_amount = $balance;
                        } else {
                            $credit_amount = abs($balance);
                        }
                    } 
                    // Liabilities, Equity, and Revenue normally have credit balances
                    else {
                        if ($balance <= 0) {
                            $credit_amount = abs($balance);
                        } else {
                            $debit_amount = $balance;
                        }
                    }

                    $total_debit_balance += $debit_amount;
                    $total_credit_balance += $credit_amount;

                    // Don't show rows with zero balance
                    if ($debit_amount == 0 && $credit_amount == 0) continue;
                ?>
                <tr class="bg-white/50 border-b border-gray-200/50">
                    <td class="px-6 py-4 font-mono"><?php echo htmlspecialchars($account['account_code']); ?></td>
                    <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($account['account_name']); ?></td>
                    <td class="px-6 py-4 text-right font-mono"><?php echo ($debit_amount > 0) ? number_format($debit_amount, 2) : ''; ?></td>
                    <td class="px-6 py-4 text-right font-mono"><?php echo ($credit_amount > 0) ? number_format($credit_amount, 2) : ''; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot class="font-bold text-gray-800">
                <tr class="bg-gray-100/50">
                    <td colspan="2" class="px-6 py-3 text-right text-lg">Totals:</td>
                    <td class="px-6 py-3 text-right text-lg font-mono <?php echo (abs($total_debit_balance - $total_credit_balance) > 0.001) ? 'text-red-500' : ''; ?>">
                        <?php echo number_format($total_debit_balance, 2); ?>
                    </td>
                    <td class="px-6 py-3 text-right text-lg font-mono <?php echo (abs($total_debit_balance - $total_credit_balance) > 0.001) ? 'text-red-500' : ''; ?>">
                        <?php echo number_format($total_credit_balance, 2); ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>