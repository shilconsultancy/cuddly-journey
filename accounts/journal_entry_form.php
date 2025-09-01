<?php
// accounts/journal_entry_form.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'create')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to create journal entries.</div>');
}

$page_title = "New Journal Entry - BizManager";

// --- DATA FETCHING ---
$accounts_result = $conn->query("SELECT id, account_name, account_code FROM scs_chart_of_accounts WHERE is_active = 1 ORDER BY account_code ASC");
$accounts = [];
while ($row = $accounts_result->fetch_assoc()) {
    $accounts[] = $row;
}
$accounts_json = json_encode($accounts);

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">New Journal Entry</h2>
    <a href="journal_entries.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Journal
    </a>
</div>

<div class="glass-card p-8 max-w-4xl mx-auto">
    <form action="journal_entry_form.php" method="POST" id="journal-entry-form" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label for="entry_date" class="block text-sm font-medium text-gray-700">Date</label>
                <input type="date" name="entry_date" id="entry_date" value="<?php echo date('Y-m-d'); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
            <div class="md:col-span-2">
                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                <input type="text" name="description" id="description" placeholder="e.g., Owner's capital investment" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
        </div>

        <div class="border-t border-gray-200/50 pt-6">
            <table class="w-full text-sm">
                <thead class="text-xs text-gray-800 uppercase">
                    <tr>
                        <th class="px-4 py-2 w-2/5">Account</th>
                        <th class="px-4 py-2">Description</th>
                        <th class="px-4 py-2 text-right">Debit</th>
                        <th class="px-4 py-2 text-right">Credit</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody id="journal-items-container">
                    </tbody>
                <tfoot class="font-semibold text-gray-800">
                    <tr>
                        <td colspan="2" class="text-right px-4 py-2">Totals:</td>
                        <td id="total-debit" class="px-4 py-2 text-right font-mono">0.00</td>
                        <td id="total-credit" class="px-4 py-2 text-right font-mono">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <button type="button" id="add-row-btn" class="mt-4 px-4 py-2 bg-green-500 text-white text-sm font-semibold rounded-lg hover:bg-green-600">Add Line</button>
        </div>

        <div class="flex justify-end pt-6 border-t border-gray-200/50">
            <button type="submit" class="inline-flex justify-center py-2 px-6 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                Save Entry
            </button>
        </div>
    </form>
</div>

<template id="journal-item-template">
    <tr class="journal-item-row border-b border-gray-200/50">
        <td class="p-2">
            <select name="items[account_id][]" class="form-input w-full account-select" required>
                <option value="">Select Account</option>
            </select>
        </td>
        <td class="p-2">
            <input type="text" name="items[description][]" class="form-input w-full">
        </td>
        <td class="p-2">
            <input type="number" step="0.01" name="items[debit][]" class="form-input w-full text-right debit-input" placeholder="0.00">
        </td>
        <td class="p-2">
            <input type="number" step="0.01" name="items[credit][]" class="form-input w-full text-right credit-input" placeholder="0.00">
        </td>
        <td class="p-2 text-center">
            <button type="button" class="remove-row-btn text-red-500 hover:text-red-700">&times;</button>
        </td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const accounts = <?php echo $accounts_json; ?>;
    const container = document.getElementById('journal-items-container');
    const template = document.getElementById('journal-item-template');
    const addRowBtn = document.getElementById('add-row-btn');

    function addRow() {
        const newRow = template.content.cloneNode(true);
        const select = newRow.querySelector('.account-select');
        accounts.forEach(acc => {
            const option = document.createElement('option');
            option.value = acc.id;
            option.textContent = `${acc.account_code} - ${acc.account_name}`;
            select.appendChild(option);
        });
        container.appendChild(newRow);
    }

    addRowBtn.addEventListener('click', addRow);

    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-row-btn')) {
            e.target.closest('tr').remove();
            updateTotals();
        }
    });

    container.addEventListener('input', function(e) {
        if (e.target.classList.contains('debit-input') || e.target.classList.contains('credit-input')) {
            const row = e.target.closest('tr');
            const debitInput = row.querySelector('.debit-input');
            const creditInput = row.querySelector('.credit-input');

            if (e.target.classList.contains('debit-input') && e.target.value) {
                creditInput.value = '';
            } else if (e.target.classList.contains('credit-input') && e.target.value) {
                debitInput.value = '';
            }
            updateTotals();
        }
    });

    function updateTotals() {
        let totalDebit = 0;
        let totalCredit = 0;
        container.querySelectorAll('.journal-item-row').forEach(row => {
            totalDebit += parseFloat(row.querySelector('.debit-input').value) || 0;
            totalCredit += parseFloat(row.querySelector('.credit-input').value) || 0;
        });
        document.getElementById('total-debit').textContent = totalDebit.toFixed(2);
        document.getElementById('total-credit').textContent = totalCredit.toFixed(2);
    }

    // Add initial rows
    addRow();
    addRow();
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>