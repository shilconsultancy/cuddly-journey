<?php
// reports/financial_reports.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Reports', 'view') || !check_permission('Accounts', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Financial Reports - BizManager";
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Financial Reports</h2>
        <p class="text-gray-600 mt-1">View key financial statements and reports.</p>
    </div>
    <a href="index.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Reports
    </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
    
    <a href="../accounts/profit_and_loss.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-teal-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-teal-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Profit & Loss</h3>
        <p class="text-xs text-gray-500 mt-1">View income statement.</p>
    </a>

    <a href="../accounts/balance_sheet.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-pink-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-pink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Balance Sheet</h3>
        <p class="text-xs text-gray-500 mt-1">View financial position.</p>
    </a>
    
    <a href="../accounts/trial_balance.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-indigo-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.002 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.002 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" /></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Trial Balance</h3>
        <p class="text-xs text-gray-500 mt-1">Verify account balances.</p>
    </a>

     <a href="../accounts/accounts_payable.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-red-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zM9 9l2-5 2 5-4 0z" transform="rotate(-45 12 12)"/></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">AP Aging</h3>
        <p class="text-xs text-gray-500 mt-1">Track money owed to suppliers.</p>
    </a>

    <a href="../accounts/accounts_receivable.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-yellow-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zM9 9l2-5 2 5-4 0z" transform="rotate(45 12 12)"/></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">AR Aging</h3>
        <p class="text-xs text-gray-500 mt-1">Track money owed by customers.</p>
    </a>

</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>