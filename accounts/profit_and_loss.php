<?php
// accounts/profit_and_loss.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Profit & Loss Statement - BizManager";

// --- Date Filtering ---
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Function to get account balances within a date range
function get_account_balance_by_type($conn, $account_type, $start_date, $end_date) {
    $sql = "
        SELECT 
            coa.account_name,
            (SELECT COALESCE(SUM(jei.credit_amount - jei.debit_amount), 0) 
             FROM scs_journal_entry_items jei
             JOIN scs_journal_entries je ON jei.journal_entry_id = je.id
             WHERE jei.account_id = coa.id AND je.entry_date BETWEEN ? AND ?) as balance
        FROM 
            scs_chart_of_accounts coa
        WHERE 
            coa.account_type = ? AND coa.is_active = 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $start_date, $end_date, $account_type);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$revenues = get_account_balance_by_type($conn, 'Revenue', $start_date, $end_date);
$expenses = get_account_balance_by_type($conn, 'Expense', $start_date, $end_date);

$total_revenue = array_sum(array_column($revenues, 'balance'));
$total_expense = array_sum(array_column($expenses, 'balance')); // Note: expense balances will be negative from the query
$net_profit = $total_revenue + $total_expense; // Adding because expenses are negative credits

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Profit & Loss Statement</h2>
        <p class="text-gray-600 mt-1">For the period of <?php echo date($app_config['date_format'], strtotime($start_date)); ?> to <?php echo date($app_config['date_format'], strtotime($end_date)); ?></p>
    </div>
    <a href="index.php" class="mt-4 md:mt-0 px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Accounts
    </a>
</div>

<div class="glass-card p-6 mb-6">
    <form action="profit_and_loss.php" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div>
             <label for="start_date" class="block text-sm font-medium text-gray-700">From</label>
             <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="form-input mt-1 block w-full">
        </div>
        <div>
            <label for="end_date" class="block text-sm font-medium text-gray-700">To</label>
            <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="form-input mt-1 block w-full">
        </div>
        <div class="flex space-x-2">
            <button type="submit" class="w-full inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Filter</button>
        </div>
    </form>
</div>

<div class="glass-card p-8 max-w-4xl mx-auto">
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($app_config['company_name']); ?></h1>
        <h2 class="text-xl font-semibold text-gray-700">Profit and Loss Statement</h2>
        <p class="text-gray-500"><?php echo date($app_config['date_format'], strtotime($start_date)); ?> - <?php echo date($app_config['date_format'], strtotime($end_date)); ?></p>
    </div>

    <div class="space-y-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-800 border-b-2 border-gray-300/50 pb-2 mb-2">Revenue</h3>
            <?php foreach ($revenues as $revenue): ?>
            <div class="flex justify-between py-1">
                <span><?php echo htmlspecialchars($revenue['account_name']); ?></span>
                <span class="font-mono"><?php echo number_format($revenue['balance'], 2); ?></span>
            </div>
            <?php endforeach; ?>
            <div class="flex justify-between font-bold border-t border-gray-300/50 pt-2 mt-2">
                <span>Total Revenue</span>
                <span class="font-mono"><?php echo number_format($total_revenue, 2); ?></span>
            </div>
        </div>

        <div>
            <h3 class="text-lg font-semibold text-gray-800 border-b-2 border-gray-300/50 pb-2 mb-2">Expenses</h3>
            <?php foreach ($expenses as $expense): ?>
            <div class="flex justify-between py-1">
                <span><?php echo htmlspecialchars($expense['account_name']); ?></span>
                <span class="font-mono"><?php echo number_format(abs($expense['balance']), 2); ?></span>
            </div>
            <?php endforeach; ?>
            <div class="flex justify-between font-bold border-t border-gray-300/50 pt-2 mt-2">
                <span>Total Expenses</span>
                <span class="font-mono">(<?php echo number_format(abs($total_expense), 2); ?>)</span>
            </div>
        </div>
        
        <div class="border-t-2 border-gray-800 pt-4 mt-4">
            <div class="flex justify-between font-bold text-xl <?php echo ($net_profit >= 0) ? 'text-green-600' : 'text-red-600'; ?>">
                <span>Net Profit</span>
                <span class="font-mono"><?php echo number_format($net_profit, 2); ?></span>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>