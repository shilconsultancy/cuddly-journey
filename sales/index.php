coming-soon.php<?php
// sales/index.php (Module Dashboard)

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Sales', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Sales - BizManager";
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Page Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Sales Management</h2>
        <p class="text-gray-600 mt-1">Manage quotations, sales orders, invoicing, and view reports.</p>
    </div>
    <a href="../dashboard.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Dashboard
    </a>
</div>

<!-- Sales Options Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
    
    <a href="sales-orders.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-green-100/50 rounded-full mb-4 backdrop-blur-sm">
             <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Sales Orders</h3>
        <p class="text-xs text-gray-500 mt-1">Manage and track all customer orders.</p>
    </a>

    <a href="quotations.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-blue-100/50 rounded-full mb-4 backdrop-blur-sm">
             <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Quotations</h3>
        <p class="text-xs text-gray-500 mt-1">Create and send quotes to clients.</p>
    </a>

    <a href="invoices.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-yellow-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Invoicing</h3>
        <p class="text-xs text-gray-500 mt-1">Generate and manage customer invoices.</p>
    </a>

    <a href="sales-dashboard.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-purple-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" />
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Sales Dashboard</h3>
        <p class="text-xs text-gray-500 mt-1">View real-time sales performance.</p>
    </a>
    
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>