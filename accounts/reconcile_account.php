<?php
// accounts/reconcile_account.php
require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'edit')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to perform this action.</div>');
}

$page_title = "Reconcile Account - BizManager";

// This page will be primarily driven by JavaScript.
// We will load the initial data here.
$account_id = $_GET['account_id'] ?? 0;
$statement_date = $_GET['statement_date'] ?? date('Y-m-d');
$statement_balance = $_GET['statement_balance'] ?? 0.00;

if ($account_id === 0) {
    die("No account selected.");
}

// Fetch account details
$account_stmt = $conn->prepare("SELECT account_name, account_code FROM scs_chart_of_accounts WHERE id = ?");
$account_stmt->bind_param("i", $account_id);
$account_stmt->execute();
$account = $account_stmt->get_result()->fetch_assoc();
$account_stmt->close();

?>
<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-4">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Reconcile: <?php echo htmlspecialchars($account['account_name']); ?></h2>
        <p class="text-gray-600 mt-1">Statement Date: <?php echo date($app_config['date_format'], strtotime($statement_date)); ?></p>
    </div>
    <a href="bank_reconciliation.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Reconciliations
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="glass-card p-4 text-center"><h4 class="text-sm text-gray-600">Statement Balance</h4><p class="text-2xl font-bold"><?php echo number_format($statement_balance, 2); ?></p></div>
    <div class="glass-card p-4 text-center"><h4 class="text-sm text-gray-600">Cleared Balance</h4><p class="text-2xl font-bold text-green-600">0.00</p></div>
    <div class="glass-card p-4 text-center"><h4 class="text-sm text-gray-600">Difference</h4><p class="text-2xl font-bold text-red-600"><?php echo number_format($statement_balance, 2); ?></p></div>
    <div class="p-4 flex items-center justify-center">
        <button class="w-full py-3 px-6 rounded-md text-white bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-400" disabled>Finish Now</button>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="glass-card p-4">
        <h3 class="text-lg font-semibold mb-4 text-center">System Transactions (Debits)</h3>
        <div class="overflow-y-auto h-[60vh]">
            <table class="w-full text-sm">
                </table>
        </div>
    </div>
    
    <div class="glass-card p-4">
        <h3 class="text-lg font-semibold mb-4 text-center">Bank Statement (Credits)</h3>
        <div class="overflow-y-auto h-[60vh]">
             <div class="text-center p-8 border-2 border-dashed border-gray-300/50 rounded-lg">
                <p class="font-semibold">Import Bank Statement</p>
                <p class="text-xs text-gray-500 mb-4">Upload a CSV file with columns: Date, Description, Amount</p>
                <input type="file" id="csv-upload" class="text-sm">
            </div>
            <table class="w-full text-sm mt-4">
                </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>