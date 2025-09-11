<?php
// hr/contracts.php

require_once __DIR__ . '/../templates/header.php';

$page_title = "Job Contracts";

// --- SECURITY CHECK ---
if (!check_permission('HR', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

// Handle success/error messages
$message = '';
$message_type = '';
if (isset($_GET['success'])) {
    $message = 'Contract saved successfully!';
    $message_type = 'success';
}
if (isset($_GET['deleted'])) {
    $message = 'Contract deleted successfully!';
    $message_type = 'success';
}

// Fetch all contracts with employee names
$contracts_result = $conn->query("
    SELECT c.id, c.contract_title, c.start_date, c.salary, u.full_name, u.company_id
    FROM scs_job_contracts c
    JOIN scs_users u ON c.user_id = u.id
    ORDER BY c.start_date DESC
");

?>
<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Job Contracts Management</h2>
    <div class="flex items-center space-x-2">
        <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to HR Dashboard
        </a>
        <?php if (check_permission('HR', 'create')): ?>
        <a href="contract_form.php" class="px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700">
            + Create New Contract
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th scope="col" class="px-6 py-3">Employee</th>
                    <th scope="col" class="px-6 py-3">Contract Title</th>
                    <th scope="col" class="px-6 py-3">Start Date</th>
                    <th scope="col" class="px-6 py-3">Salary</th>
                    <th scope="col" class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($contracts_result->num_rows > 0): ?>
                    <?php while($contract = $contracts_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($contract['full_name'] . ' (' . $contract['company_id'] . ')'); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($contract['contract_title']); ?></td>
                        <td class="px-6 py-4"><?php echo date($app_config['date_format'], strtotime($contract['start_date'])); ?></td>
                        <td class="px-6 py-4"><?php echo $app_config['currency_symbol'] . number_format($contract['salary'], 2); ?></td>
                        <td class="px-6 py-4 text-right">
                            <a href="print_contract.php?id=<?php echo $contract['id']; ?>" class="font-medium text-green-600 hover:underline mr-4" target="_blank">Print</a>
                            <?php if (check_permission('HR', 'edit')): ?>
                            <a href="contract_form.php?id=<?php echo $contract['id']; ?>" class="font-medium text-indigo-600 hover:underline">Edit</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No job contracts found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>