<?php
// crm/customers.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('CRM', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Customer Management - BizManager";

$message = '';
if (isset($_GET['success'])) {
    $message = htmlspecialchars($_GET['success']);
}

// DATA FETCHING for the list (using is_active to be safe, though delete is removed)
$customers_result = $conn->query("SELECT * FROM scs_customers WHERE is_active = 1 ORDER BY customer_name ASC");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Customer Database</h2>
        <p class="text-gray-600 mt-1">View and manage all active customers.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to CRM
        </a>
        <?php if (check_permission('CRM', 'create')): ?>
        <a href="customer-form.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add New Customer
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="glass-card p-6">
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md bg-green-100/80 text-green-800">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th scope="col" class="px-6 py-3">Customer Name</th>
                    <th scope="col" class="px-6 py-3">Contact Info</th>
                    <th scope="col" class="px-6 py-3">Type</th>
                    <th scope="col" class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $customers_result->fetch_assoc()): ?>
                <tr class="bg-white/50 border-b border-gray-200/50">
                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                    <td class="px-6 py-4">
                        <div class="font-semibold"><?php echo htmlspecialchars($row['phone'] ?? ''); ?></div>
                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['email'] ?? ''); ?></div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-semibold <?php echo ($row['customer_type'] == 'B2B') ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?> rounded-full">
                            <?php echo htmlspecialchars($row['customer_type']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <a href="customer-details.php?id=<?php echo $row['id']; ?>" class="font-medium text-green-600 hover:underline">View Profile</a>
                        <?php if (check_permission('CRM', 'edit')): ?>
                            <a href="customer-form.php?id=<?php echo $row['id']; ?>" class="font-medium text-indigo-600 hover:underline">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>