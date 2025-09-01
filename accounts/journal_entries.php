<?php
// accounts/journal_entries.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Journal Entries - BizManager";

// --- DATA FETCHING ---
$journal_entries_result = $conn->query("
    SELECT 
        je.id,
        je.entry_date,
        je.description,
        je.reference_number,
        u.full_name as created_by_name,
        (SELECT SUM(jei.debit_amount) FROM scs_journal_entry_items jei WHERE jei.journal_entry_id = je.id) as total_amount
    FROM scs_journal_entries je
    JOIN scs_users u ON je.created_by = u.id
    ORDER BY je.entry_date DESC, je.id DESC
");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">General Journal</h2>
        <p class="text-gray-600 mt-1">A log of all manual financial transactions.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to Accounts
        </a>
        <?php if (check_permission('Accounts', 'create')): ?>
        <a href="journal_entry_form.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            New Journal Entry
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th scope="col" class="px-6 py-3">Date</th>
                    <th scope="col" class="px-6 py-3">Description</th>
                    <th scope="col" class="px-6 py-3">Reference</th>
                    <th scope="col" class="px-6 py-3 text-right">Amount</th>
                    <th scope="col" class="px-6 py-3">Created By</th>
                </tr>
            </thead>
            <tbody>
                <?php while($entry = $journal_entries_result->fetch_assoc()): ?>
                <tr class="bg-white/50 border-b border-gray-200/50">
                    <td class="px-6 py-4"><?php echo date($app_config['date_format'], strtotime($entry['entry_date'])); ?></td>
                    <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($entry['description']); ?></td>
                    <td class="px-6 py-4"><?php echo htmlspecialchars($entry['reference_number']); ?></td>
                    <td class="px-6 py-4 text-right font-mono"><?php echo number_format($entry['total_amount'], 2); ?></td>
                    <td class="px-6 py-4"><?php echo htmlspecialchars($entry['created_by_name']); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>