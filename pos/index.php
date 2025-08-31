<?php
// pos/index.php (Module Dashboard)

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('POS', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Point of Sale - BizManager";
$user_role_id = $_SESSION['user_role_id'] ?? 0;

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Point of Sale</h2>
        <p class="text-gray-600 mt-1">Start a new sale or view session history.</p>
    </div>
    <a href="../dashboard.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Dashboard
    </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
    
    <a href="sale.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-green-100/50 rounded-full mb-4 backdrop-blur-sm">
             <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">New Sale / Open Register</h3>
        <p class="text-xs text-gray-500 mt-1">Start a new sales session.</p>
    </a>

    <?php if (in_array($user_role_id, [1, 2, 6])): // Super Admin, Admin, Shop Manager ?>
    <a href="session-history.php" class="app-card glass-card flex flex-col items-center justify-center p-6 rounded-xl text-center hover:bg-white/50">
        <div class="p-4 bg-blue-100/50 rounded-full mb-4 backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <h3 class="text-md font-semibold text-gray-800">Session History</h3>
        <p class="text-xs text-gray-500 mt-1">Review past sales sessions.</p>
    </a>
    <?php endif; ?>
    
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>