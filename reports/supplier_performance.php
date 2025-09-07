<?php
// reports/supplier_performance.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Reports', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Supplier Performance Report - BizManager";

// --- DATA FETCHING ---
$sql = "
    SELECT 
        s.id as supplier_id,
        s.supplier_name,
        s.contact_person,
        s.email,
        COUNT(po.id) as total_pos,
        COALESCE(SUM(po.total_amount), 0) as total_spend,
        (COALESCE(SUM(b.total_amount), 0) - COALESCE(SUM(b.amount_paid), 0)) as outstanding_balance
    FROM scs_suppliers s
    LEFT JOIN scs_purchase_orders po ON s.id = po.supplier_id
    LEFT JOIN scs_supplier_bills b ON s.id = b.supplier_id
    GROUP BY s.id
    ORDER BY total_spend DESC, s.supplier_name ASC
";

$report_result = $conn->query($sql);

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Supplier Performance Report</h2>
        <p class="text-gray-600 mt-1">Analyze spending and outstanding balances by supplier.</p>
    </div>
    <a href="procurement_reports.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Procurement Reports
    </a>
</div>

<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th class="px-6 py-3">Supplier</th>
                    <th class="px-6 py-3 text-center">Total POs</th>
                    <th class="px-6 py-3 text-right">Total Spend</th>
                    <th class="px-6 py-3 text-right">Outstanding Balance</th>
                    <th class="px-6 py-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($report_result->num_rows > 0): ?>
                    <?php while($row = $report_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4">
                            <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($row['supplier_name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['contact_person']); ?></div>
                        </td>
                        <td class="px-6 py-4 text-center font-bold text-lg"><?php echo $row['total_pos']; ?></td>
                        <td class="px-6 py-4 text-right font-mono"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($row['total_spend'], 2)); ?></td>
                        <td class="px-6 py-4 text-right font-mono font-semibold <?php echo ($row['outstanding_balance'] > 0) ? 'text-red-600' : 'text-green-600'; ?>">
                            <?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($row['outstanding_balance'], 2)); ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <a href="../procurement/supplier-details.php?id=<?php echo $row['supplier_id']; ?>" class="font-medium text-indigo-600 hover:underline">
                                View Details
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No supplier data found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<?php
require_once __DIR__ . '/../templates/footer.php';
?>