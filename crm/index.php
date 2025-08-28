<?php
// crm/index.php (Module Dashboard)

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('CRM', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "CRM - BizManager";
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Customer Relationship Management</h2>
        <p class="text-gray-600 mt-1">Manage your customers, leads, sales pipeline, and support interactions.</p>
    </div>
    <a href="../dashboard.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Dashboard
    </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
    
    <a href="leads.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-teal-100/50 rounded-full mb-4 backdrop-blur-sm">
             <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-teal-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Leads</h3>
        <p class="text-xs text-gray-500 mt-1">Track potential new customers.</p>
    </a>

    <a href="pipeline.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-purple-100/50 rounded-full mb-4 backdrop-blur-sm">
             <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Sales Pipeline</h3>
        <p class="text-xs text-gray-500 mt-1">Manage B2B deals and opportunities.</p>
    </a>

    <a href="customers.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-orange-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Customers</h3>
        <p class="text-xs text-gray-500 mt-1">View and manage your customer database.</p>
    </a>

    <a href="contacts.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-blue-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 100 4h4a2 2 0 100-4h-4z" />
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Contacts</h3>
        <p class="text-xs text-gray-500 mt-1">Manage contact persons for B2B clients.</p>
    </a>

    <a href="interactions.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-yellow-100/50 rounded-full mb-4 backdrop-blur-sm">
             <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Interaction Log</h3>
        <p class="text-xs text-gray-500 mt-1">Record calls, meetings, and emails.</p>
    </a>

    <a href="tickets.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-red-100/50 rounded-full mb-4 backdrop-blur-sm">
             <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 002 2h14a2 2 0 002-2V7a2 2 0 00-2-2H5z" />
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Support Tickets</h3>
        <p class="text-xs text-gray-500 mt-1">Track and resolve customer issues.</p>
    </a>
    
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>