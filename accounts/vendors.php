<?php
// accounts/vendors.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Vendors (Accounts Payable) - BizManager";

// --- DATA FETCHING ---
// This query calculates the total business, total paid, and current balance for each supplier.
$vendors_result = $conn->query("
    SELECT 
        s.id,
        s.supplier_name,
        s.contact_person,
        s.email,
        s.phone,
        COALESCE(SUM(b.total_amount), 0) as total_business,
        COALESCE(SUM(b.amount_paid), 0) as total_paid,
        (COALESCE(SUM(b.total_amount), 0) - COALESCE(SUM(b.amount_paid), 0)) as balance_due
    FROM 
        scs_suppliers s
    LEFT JOIN 
        scs_supplier_bills b ON s.id = b.supplier_id
    GROUP BY
        s.id
    ORDER BY 
        s.supplier_name ASC
");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Vendors & Payables</h2>
        <p class="text-gray-600 mt-1">An overview of all suppliers and their outstanding balances.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to Accounts
        </a>
        <a href="../procurement/suppliers.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 transition-colors">
            Manage Suppliers
        </a>
    </div>
</div>

<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th scope="col" class="px-6 py-3">Supplier</th>
                    <th scope="col" class="px-6 py-3">Contact</th>
                    <th scope="col" class="px-6 py-3 text-right">Total Business</th>
                    <th scope="col" class="px-6 py-3 text-right">Balance Due</th>
                    <th scope="col" class="px-6 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($vendors_result->num_rows > 0): ?>
                    <?php while($vendor = $vendors_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50 hover:bg-gray-50/50">
                        <td class="px-6 py-4 font-semibold text-gray-800"><?php echo htmlspecialchars($vendor['supplier_name']); ?></td>
                        <td class="px-6 py-4">
                            <div class="font-medium"><?php echo htmlspecialchars($vendor['contact_person']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($vendor['email']); ?></div>
                        </td>
                        <td class="px-6 py-4 text-right font-mono">
                            <?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($vendor['total_business'], 2)); ?>
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-bold <?php echo ($vendor['balance_due'] > 0) ? 'text-red-600' : 'text-green-600'; ?>">
                            <?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($vendor['balance_due'], 2)); ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <a href="accounts_payable.php?supplier_id=<?php echo $vendor['id']; ?>" class="font-medium text-indigo-600 hover:underline">View Bills</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No suppliers found. Please add suppliers in the Procurement module first.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>