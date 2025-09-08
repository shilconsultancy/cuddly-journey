<?php
// hr/payroll.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('HR', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Payroll - BizManager";

// --- DATA FETCHING ---
$payslips_result = $conn->query("
    SELECT 
        p.id, p.pay_period_start, p.pay_period_end, p.net_pay, p.status,
        u.full_name as employee_name
    FROM scs_payslips p
    JOIN scs_users u ON p.user_id = u.id
    ORDER BY p.pay_period_end DESC, u.full_name ASC
");

$status_colors = [
    'Draft' => 'bg-gray-200 text-gray-800',
    'Paid' => 'bg-green-100 text-green-800',
];

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Payroll Management</h2>
        <p class="text-gray-600 mt-1">Generate and manage employee payslips.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to HR
        </a>
        <?php if (check_permission('HR', 'create')): ?>
        <a href="payroll_generate.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 transition-colors">
            Generate Payslips
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th scope="col" class="px-6 py-3">Employee</th>
                    <th scope="col" class="px-6 py-3">Pay Period</th>
                    <th scope="col" class="px-6 py-3 text-right">Net Pay</th>
                    <th scope="col" class="px-6 py-3">Status</th>
                    <th scope="col" class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($payslips_result->num_rows > 0): ?>
                    <?php while($payslip = $payslips_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($payslip['employee_name']); ?></td>
                        <td class="px-6 py-4">
                            <?php echo date('M d, Y', strtotime($payslip['pay_period_start'])); ?> - <?php echo date('M d, Y', strtotime($payslip['pay_period_end'])); ?>
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-semibold"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($payslip['net_pay'], 2)); ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_colors[$payslip['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                <?php echo htmlspecialchars($payslip['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                             <a href="payslip_details.php?id=<?php echo $payslip['id']; ?>" class="font-medium text-indigo-600 hover:underline">View</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No payslips have been generated yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>