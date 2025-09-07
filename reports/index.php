<?php
// reports/index.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Reports', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Reports - BizManager";

// Function to check if a report sub-module is fully built
function is_module_built($module_path) {
    // A more robust check to see if the main file for the sub-module exists
    return file_exists(__DIR__ . '/' . $module_path);
}

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Reports & Analytics</h2>
        <p class="text-gray-600 mt-1">Select a category to view detailed reports.</p>
    </div>
    <a href="../dashboard.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Dashboard
    </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
    
    <?php 
        // All sales reports are built
        $sales_reports_built = true; 
    ?>
    <a href="sales_reports.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-purple-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" />
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Sales Reports</h3>
        <p class="text-xs text-gray-500 mt-1">Analyze sales performance and trends.</p>
        <span class="mt-2 text-xs font-semibold px-2 py-1 rounded-full <?php echo $sales_reports_built ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-800'; ?>">
            <?php echo $sales_reports_built ? 'Built' : 'To Do'; ?>
        </span>
    </a>

    <?php 
        // All inventory reports are built
        $inventory_reports_built = true; 
    ?>
    <a href="inventory_reports.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-blue-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 010 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Inventory Reports</h3>
        <p class="text-xs text-gray-500 mt-1">Track stock levels and valuation.</p>
        <span class="mt-2 text-xs font-semibold px-2 py-1 rounded-full <?php echo $inventory_reports_built ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-800'; ?>">
            <?php echo $inventory_reports_built ? 'Built' : 'To Do'; ?>
        </span>
    </a>

    <?php 
        // All financial reports are built
        $financial_reports_built = true; 
    ?>
    <a href="financial_reports.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-red-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6m-5 4h4m5 6H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Financial Reports</h3>
        <p class="text-xs text-gray-500 mt-1">View P&L, Balance Sheet, and more.</p>
        <span class="mt-2 text-xs font-semibold px-2 py-1 rounded-full <?php echo $financial_reports_built ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-800'; ?>">
            <?php echo $financial_reports_built ? 'Built' : 'To Do'; ?>
        </span>
    </a>

    <?php 
        // All procurement reports are built
        $procurement_reports_built = true; 
    ?>
    <a href="procurement_reports.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-green-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2-2h8a1 1 0 001-1z"/></svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Procurement Reports</h3>
        <p class="text-xs text-gray-500 mt-1">Analyze supplier performance.</p>
        <span class="mt-2 text-xs font-semibold px-2 py-1 rounded-full <?php echo $procurement_reports_built ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-800'; ?>">
            <?php echo $procurement_reports_built ? 'Built' : 'To Do'; ?>
        </span>
    </a>
    
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>