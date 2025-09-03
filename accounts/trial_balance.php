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
    HAVING 
        total_debits != total_credits
    ORDER BY 
        coa.account_code ASC
";

$accounts_result = $conn->query($sql);

$total_debit_balance = 0;
$total_credit_balance = 0;

?>

<title><?php echo htmlspecialchars($page_title); ?></title>
<style>
    @media print {
        body * {
            visibility: hidden;
        }
        .print-container, .print-container * {
            visibility: visible;
        }
        .print-container {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
        .no-print {
            display: none;
        }
        .glass-card {
            box-shadow: none;
            border: 1px solid #ccc;
        }
    }
</style>

<div class="flex justify-between items-center mb-6 no-print">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Trial Balance</h2>
        <p class="text-gray-600 mt-1">As of <?php echo date($app_config['date_format']); ?></p>
    </div>
    <div class="flex space-x-2">
        <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to Accounts
        </a>
        <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700">
            Print Report
        </button>
    </div>
</div>

<div class="glass-card p-6 print-container">
    <div class="text-center mb-8 hidden print:block">
        <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($app_config['company_name']); ?></h1>
        <h2 class="text-xl font-semibold text-gray-700">Trial Balance</h2>
        <p class="text-gray-500">As of <?php echo date($app_config['date_format']); ?></p>
    </div>
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

                    if (in_array($account['account_type'], ['Asset', 'Expense'])) {
                        $debit_amount = $balance > 0 ? $balance : 0;
                        $credit_amount = $balance < 0 ? abs($balance) : 0;
                    } 
                    else {
                        $credit_amount = $balance < 0 ? abs($balance) : 0;
                        $debit_amount = $balance > 0 ? $balance : 0;
                    }

                    $total_debit_balance += $debit_amount;
                    $total_credit_balance += $credit_amount;
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
                <tr class="bg-gray-100/50 border-t-2 border-gray-300">
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