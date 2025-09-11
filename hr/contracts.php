<?php
// hr/contracts.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('HR', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Job Contracts - BizManager";

// --- DATA FETCHING ---
$contracts_result = $conn->query("
    SELECT 
        c.id, c.start_date, c.end_date, c.job_type, c.salary,
        u.full_name as employee_name,
        ed.job_title
    FROM scs_job_contracts c
    JOIN scs_users u ON c.user_id = u.id
    LEFT JOIN scs_employee_details ed ON u.id = ed.user_id
    ORDER BY u.full_name ASC
");
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Job Contracts</h2>
        <p class="text-gray-600 mt-1">Manage all employee employment contracts.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to HR
        </a>
        <?php if (check_permission('HR', 'create')): ?>
        <a href="contract_form.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 transition-colors">
            Create New Contract
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
                    <th scope="col" class="px-6 py-3">Job Title</th>
                    <th scope="col" class="px-6 py-3">Type</th>
                    <th scope="col" class="px-6 py-3">Start Date</th>
                    <th scope="col" class="px-6 py-3 text-right">Salary</th>
                    <th scope="col" class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($contracts_result->num_rows > 0): ?>
                    <?php while($contract = $contracts_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($contract['employee_name']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($contract['job_title'] ?? 'N/A'); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($contract['job_type']); ?></td>
                        <td class="px-6 py-4"><?php echo date($app_config['date_format'], strtotime($contract['start_date'])); ?></td>
                        <td class="px-6 py-4 text-right font-mono"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($contract['salary'], 2)); ?></td>
                        <td class="px-6 py-4 text-right">
                             <a href="contract_form.php?id=<?php echo $contract['id']; ?>" class="font-medium text-indigo-600 hover:underline">View/Edit</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No contracts have been created yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>