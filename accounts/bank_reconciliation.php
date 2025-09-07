<?php
// accounts/bank_reconciliation.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Bank Reconciliation - BizManager";

// --- DATA FETCHING ---
// Fetch bank and cash accounts for the dropdown
$bank_accounts_result = $conn->query("SELECT id, account_name, account_code FROM scs_chart_of_accounts WHERE account_type = 'Asset' AND (account_name LIKE '%Bank%' OR account_name LIKE '%Cash%') AND is_active = 1 ORDER BY account_name ASC");

// Fetch past reconciliations
$reconciliations_result = $conn->query("
    SELECT r.*, coa.account_name, u.full_name as creator_name
    FROM scs_bank_reconciliations r
    JOIN scs_chart_of_accounts coa ON r.account_id = coa.id
    JOIN scs_users u ON r.created_by = u.id
    ORDER BY r.statement_date DESC
");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Bank Reconciliations</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Accounts
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-1">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Start New Reconciliation</h3>
            <form action="reconcile_account.php" method="GET" class="space-y-4">
                <div>
                    <label for="account_id" class="block text-sm font-medium text-gray-700">Bank/Cash Account</label>
                    <select name="account_id" id="account_id" class="form-input mt-1 block w-full p-2" required>
                        <option value="">Select an account...</option>
                        <?php while($account = $bank_accounts_result->fetch_assoc()): ?>
                            <option value="<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['account_name'] . ' (' . $account['account_code'] . ')'); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="statement_start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                        <input type="date" name="statement_start_date" id="statement_start_date" value="<?php echo date('Y-m-01'); ?>" class="form-input mt-1 block w-full p-2" required>
                    </div>
                    <div>
                        <label for="statement_date" class="block text-sm font-medium text-gray-700">End Date</label>
                        <input type="date" name="statement_date" id="statement_date" value="<?php echo date('Y-m-t'); ?>" class="form-input mt-1 block w-full p-2" required>
                    </div>
                </div>
                 <div>
                    <label for="statement_balance" class="block text-sm font-medium text-gray-700">Ending Balance from Statement</label>
                    <input type="number" step="0.01" name="statement_balance" id="statement_balance" placeholder="0.00" class="form-input mt-1 block w-full p-2" required>
                </div>
                <div class="pt-2">
                    <button type="submit" class="w-full inline-flex justify-center py-3 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Start Reconciling
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div class="lg:col-span-2">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Reconciliation History</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-3">Statement Date</th>
                            <th class="px-6 py-3">Account</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reconciliations_result->num_rows > 0): ?>
                            <?php while($rec = $reconciliations_result->fetch_assoc()): ?>
                            <tr class="bg-white/50 border-b border-gray-200/50">
                                <td class="px-6 py-4 font-semibold"><?php echo date($app_config['date_format'], strtotime($rec['statement_date'])); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($rec['account_name']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $rec['status'] == 'Completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo htmlspecialchars($rec['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="reconcile_account.php?id=<?php echo $rec['id']; ?>" class="font-medium text-indigo-600 hover:underline">View</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                             <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No reconciliations have been started.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>