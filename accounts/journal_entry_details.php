<?php
// accounts/journal_entry_details.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$entry_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($entry_id === 0) {
    die('<div class="glass-card p-8 text-center">Invalid Journal Entry ID.</div>');
}

$page_title = "Journal Entry Details - BizManager";

// --- DATA FETCHING ---
// Get main entry details
$entry_stmt = $conn->prepare("SELECT je.*, u.full_name as creator FROM scs_journal_entries je JOIN scs_users u ON je.created_by = u.id WHERE je.id = ?");
$entry_stmt->bind_param("i", $entry_id);
$entry_stmt->execute();
$entry = $entry_stmt->get_result()->fetch_assoc();
$entry_stmt->close();

if (!$entry) {
    die("Journal entry not found.");
}

// Get entry items
$items_stmt = $conn->prepare("
    SELECT jei.*, coa.account_code, coa.account_name 
    FROM scs_journal_entry_items jei
    JOIN scs_chart_of_accounts coa ON jei.account_id = coa.id
    WHERE jei.journal_entry_id = ?
    ORDER BY jei.debit_amount DESC, jei.credit_amount DESC
");
$items_stmt->bind_param("i", $entry_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Journal Entry #<?php echo $entry['id']; ?></h2>
        <p class="text-gray-600 mt-1">Details for the financial transaction.</p>
    </div>
    <div class="flex space-x-2">
        <a href="journal_entries.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to Journal
        </a>
        <?php if (check_permission('Accounts', 'edit') && !$entry['source_document']): ?>
        <a href="edit_journal_entry.php?id=<?php echo $entry['id']; ?>" class="px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700">
            Edit Entry
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="glass-card p-8">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 border-b border-gray-200/50 pb-6">
        <div><p class="text-sm text-gray-500">Entry Date</p><p class="font-semibold"><?php echo date($app_config['date_format'], strtotime($entry['entry_date'])); ?></p></div>
        <div><p class="text-sm text-gray-500">Reference #</p><p class="font-semibold"><?php echo htmlspecialchars($entry['reference_number'] ?: 'N/A'); ?></p></div>
        <div><p class="text-sm text-gray-500">Created By</p><p class="font-semibold"><?php echo htmlspecialchars($entry['creator']); ?></p></div>
        <div class="md:col-span-3"><p class="text-sm text-gray-500">Description</p><p class="font-semibold"><?php echo htmlspecialchars($entry['description']); ?></p></div>
        <?php if ($entry['source_document']): ?>
             <div class="md:col-span-3"><p class="text-sm text-gray-500">Source</p><p class="font-semibold text-green-600">Automatically generated from <?php echo htmlspecialchars($entry['source_document']); ?> #<?php echo htmlspecialchars($entry['source_document_id']); ?></p></div>
        <?php endif; ?>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th class="px-6 py-3">Account Code</th>
                    <th class="px-6 py-3">Account Name</th>
                    <th class="px-6 py-3 text-right">Debit</th>
                    <th class="px-6 py-3 text-right">Credit</th>
                </tr>
            </thead>
            <tbody>
                <?php while($item = $items_result->fetch_assoc()): ?>
                <tr class="bg-white/50 border-b border-gray-200/50">
                    <td class="px-6 py-4 font-mono"><?php echo htmlspecialchars($item['account_code']); ?></td>
                    <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($item['account_name']); ?></td>
                    <td class="px-6 py-4 text-right font-mono"><?php echo ($item['debit_amount'] > 0) ? number_format($item['debit_amount'], 2) : ''; ?></td>
                    <td class="px-6 py-4 text-right font-mono"><?php echo ($item['credit_amount'] > 0) ? number_format($item['credit_amount'], 2) : ''; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>