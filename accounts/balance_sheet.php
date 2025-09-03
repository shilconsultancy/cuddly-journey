<?php
// accounts/balance_sheet.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Balance Sheet - BizManager";

$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');

function get_balances_by_type($conn, $account_type, $as_of_date) {
    $sql = "
        SELECT 
            coa.account_name,
            (SELECT COALESCE(SUM(jei.debit_amount - jei.credit_amount), 0) 
             FROM scs_journal_entry_items jei
             JOIN scs_journal_entries je ON jei.journal_entry_id = je.id
             WHERE jei.account_id = coa.id AND je.entry_date <= ?) as balance
        FROM 
            scs_chart_of_accounts coa
        WHERE 
            coa.account_type = ? AND coa.is_active = 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $as_of_date, $account_type);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$assets = get_balances_by_type($conn, 'Asset', $as_of_date);
$liabilities = get_balances_by_type($conn, 'Liability', $as_of_date);
$equity = get_balances_by_type($conn, 'Equity', $as_of_date);

$total_assets = array_sum(array_column($assets, 'balance'));
$total_liabilities = abs(array_sum(array_column($liabilities, 'balance')));
$total_equity = abs(array_sum(array_column($equity, 'balance')));
$total_liabilities_and_equity = $total_liabilities + $total_equity;

?>

<title><?php echo htmlspecialchars($page_title); ?></title>
<style>
    @media print {
        body * { visibility: hidden; }
        .print-container, .print-container * { visibility: visible; }
        .print-container { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none; }
        .glass-card { box-shadow: none; border: 1px solid #ccc; }
    }
</style>

<div class="flex justify-between items-center mb-6 no-print">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Balance Sheet</h2>
        <p class="text-gray-600 mt-1">As of <?php echo date($app_config['date_format'], strtotime($as_of_date)); ?></p>
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

<div class="glass-card p-4 mb-6 no-print">
    <form action="balance_sheet.php" method="GET" class="flex items-end space-x-4">
        <div>
             <label for="as_of_date" class="block text-sm font-medium text-gray-700">As of Date</label>
             <input type="date" name="as_of_date" id="as_of_date" value="<?php echo htmlspecialchars($as_of_date); ?>" class="form-input mt-1 block w-full">
        </div>
        <div>
            <button type="submit" class="inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">View Report</button>
        </div>
    </form>
</div>

<div class="glass-card p-8 max-w-4xl mx-auto print-container">
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($app_config['company_name']); ?></h1>
        <h2 class="text-xl font-semibold text-gray-700">Balance Sheet</h2>
        <p class="text-gray-500">As of <?php echo date($app_config['date_format'], strtotime($as_of_date)); ?></p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12">
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-800 border-b-2 border-gray-300/50 pb-2 mb-2">Assets</h3>
            <?php foreach ($assets as $asset): if(abs($asset['balance']) < 0.01) continue; ?>
            <div class="flex justify-between py-1">
                <span><?php echo htmlspecialchars($asset['account_name']); ?></span>
                <span class="font-mono"><?php echo number_format($asset['balance'], 2); ?></span>
            </div>
            <?php endforeach; ?>
            <div class="flex justify-between font-bold border-t-2 border-gray-800 pt-2 mt-4 text-lg">
                <span>Total Assets</span>
                <span class="font-mono"><?php echo number_format($total_assets, 2); ?></span>
            </div>
        </div>

        <div class="space-y-6 mt-8 md:mt-0">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 border-b-2 border-gray-300/50 pb-2 mb-2">Liabilities</h3>
                <?php foreach ($liabilities as $liability): if(abs($liability['balance']) < 0.01) continue; ?>
                <div class="flex justify-between py-1">
                    <span><?php echo htmlspecialchars($liability['account_name']); ?></span>
                    <span class="font-mono"><?php echo number_format(abs($liability['balance']), 2); ?></span>
                </div>
                <?php endforeach; ?>
                <div class="flex justify-between font-bold border-t border-gray-300/50 pt-2 mt-2">
                    <span>Total Liabilities</span>
                    <span class="font-mono"><?php echo number_format($total_liabilities, 2); ?></span>
                </div>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-800 border-b-2 border-gray-300/50 pb-2 mb-2">Equity</h3>
                <?php foreach ($equity as $eq): if(abs($eq['balance']) < 0.01) continue; ?>
                <div class="flex justify-between py-1">
                    <span><?php echo htmlspecialchars($eq['account_name']); ?></span>
                    <span class="font-mono"><?php echo number_format(abs($eq['balance']), 2); ?></span>
                </div>
                <?php endforeach; ?>
                <div class="flex justify-between font-bold border-t border-gray-300/50 pt-2 mt-2">
                    <span>Total Equity</span>
                    <span class="font-mono"><?php echo number_format($total_equity, 2); ?></span>
                </div>
            </div>
            <div class="flex justify-between font-bold border-t-2 border-gray-800 pt-2 mt-4 text-lg">
                <span>Total Liabilities & Equity</span>
                <span class="font-mono <?php echo (abs($total_assets - $total_liabilities_and_equity) > 0.01) ? 'text-red-500' : ''; ?>">
                    <?php echo number_format($total_liabilities_and_equity, 2); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>