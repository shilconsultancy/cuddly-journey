<?php
// accounts/edit_journal_entry.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'edit')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to edit journal entries.</div>');
}

$page_title = "Edit Journal Entry - BizManager";
$entry_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($entry_id === 0) {
    header("Location: journal_entries.php");
    exit();
}

$message = '';
$message_type = '';

// --- FORM PROCESSING FOR UPDATING AN ENTRY ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    try {
        $entry_id_post = (int)$_POST['entry_id'];
        $entry_date = $_POST['entry_date'];
        $description = trim($_POST['description']);
        $reference_number = trim($_POST['reference_number']);
        $items = $_POST['items'];

        // Update the main entry
        $stmt_update = $conn->prepare("UPDATE scs_journal_entries SET entry_date = ?, description = ?, reference_number = ? WHERE id = ? AND source_document IS NULL");
        $stmt_update->bind_param("sssi", $entry_date, $description, $reference_number, $entry_id_post);
        $stmt_update->execute();

        // Delete old items
        $stmt_delete_items = $conn->prepare("DELETE FROM scs_journal_entry_items WHERE journal_entry_id = ?");
        $stmt_delete_items->bind_param("i", $entry_id_post);
        $stmt_delete_items->execute();

        // Re-insert new items
        $debits = [];
        $credits = [];
        for ($i = 0; $i < count($items['account_id']); $i++) {
            $account_id = $items['account_id'][$i];
            $debit = !empty($items['debit'][$i]) ? (float)$items['debit'][$i] : 0;
            $credit = !empty($items['credit'][$i]) ? (float)$items['credit'][$i] : 0;
            if ($debit > 0) $debits[] = ['account_id' => $account_id, 'amount' => $debit];
            if ($credit > 0) $credits[] = ['account_id' => $account_id, 'amount' => $credit];
        }

        $stmt_items_debit = $conn->prepare("INSERT INTO scs_journal_entry_items (journal_entry_id, account_id, debit_amount) VALUES (?, ?, ?)");
        foreach ($debits as $debit) {
            $stmt_items_debit->bind_param("iid", $entry_id_post, $debit['account_id'], $debit['amount']);
            $stmt_items_debit->execute();
        }
        $stmt_items_debit->close();

        $stmt_items_credit = $conn->prepare("INSERT INTO scs_journal_entry_items (journal_entry_id, account_id, credit_amount) VALUES (?, ?, ?)");
        foreach ($credits as $credit) {
            $stmt_items_credit->bind_param("iid", $entry_id_post, $credit['account_id'], $credit['amount']);
            $stmt_items_credit->execute();
        }
        $stmt_items_credit->close();

        $conn->commit();
        log_activity('JOURNAL_UPDATED', "Updated manual journal entry: " . $description, $conn);
        header("Location: journal_entry_details.php?id=" . $entry_id_post . "&success=updated");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}


// --- DATA FETCHING for the form ---
$entry_stmt = $conn->prepare("SELECT * FROM scs_journal_entries WHERE id = ?");
$entry_stmt->bind_param("i", $entry_id);
$entry_stmt->execute();
$entry = $entry_stmt->get_result()->fetch_assoc();
$entry_stmt->close();

if (!$entry) {
    die("Journal entry not found.");
}
if ($entry['source_document']) {
    die('<div class="glass-card p-8 text-center">This journal entry was automatically generated and cannot be manually edited.</div>');
}

$items_stmt = $conn->prepare("SELECT * FROM scs_journal_entry_items WHERE journal_entry_id = ?");
$items_stmt->bind_param("i", $entry_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$line_items_data = [];
while ($row = $items_result->fetch_assoc()) {
    $line_items_data[] = $row;
}
$items_stmt->close();

$accounts_result = $conn->query("SELECT id, account_name, account_code FROM scs_chart_of_accounts WHERE is_active = 1 ORDER BY account_code ASC");
$accounts = [];
while ($row = $accounts_result->fetch_assoc()) {
    $accounts[] = $row;
}
$accounts_json = json_encode($accounts);
$line_items_json = json_encode($line_items_data);

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Edit Journal Entry #<?php echo $entry_id; ?></h2>
    <a href="journal_entry_details.php?id=<?php echo $entry_id; ?>" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Details
    </a>
</div>

<div class="glass-card p-8 max-w-4xl mx-auto">
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'error' ? 'bg-red-100/80 text-red-800' : 'bg-green-100/80 text-green-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="edit_journal_entry.php?id=<?php echo $entry_id; ?>" method="POST" id="journal-entry-form" class="space-y-6">
        <input type="hidden" name="entry_id" value="<?php echo $entry_id; ?>">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label for="entry_date" class="block text-sm font-medium text-gray-700">Date</label>
                <input type="date" name="entry_date" id="entry_date" value="<?php echo htmlspecialchars($entry['entry_date']); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
             <div>
                <label for="reference_number" class="block text-sm font-medium text-gray-700">Reference # (Optional)</label>
                <input type="text" name="reference_number" id="reference_number" value="<?php echo htmlspecialchars($entry['reference_number']); ?>" class="form-input mt-1 block w-full rounded-md p-3">
            </div>
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                <input type="text" name="description" id="description" value="<?php echo htmlspecialchars($entry['description']); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
        </div>

        <div class="border-t border-gray-200/50 pt-6">
            <table class="w-full text-sm">
                <thead class="text-xs text-gray-800 uppercase">
                    <tr>
                        <th class="px-4 py-2 w-2/5">Account</th>
                        <th class="px-4 py-2 text-right">Debit</th>
                        <th class="px-4 py-2 text-right">Credit</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody id="journal-items-container">
                    </tbody>
                <tfoot class="font-semibold text-gray-800">
                    <tr>
                        <td class="text-right px-4 py-2">Totals:</td>
                        <td id="total-debit" class="px-4 py-2 text-right font-mono">0.00</td>
                        <td id="total-credit" class="px-4 py-2 text-right font-mono">0.00</td>
                        <td></td>
                    </tr>
                     <tr id="imbalance-row" class="hidden">
                        <td colspan="4" class="text-center p-2 text-red-600 font-bold bg-red-100/50">
                            Totals do not balance!
                        </td>
                    </tr>
                </tfoot>
            </table>
            <button type="button" id="add-row-btn" class="mt-4 px-4 py-2 bg-green-500 text-white text-sm font-semibold rounded-lg hover:bg-green-600">Add Line</button>
        </div>

        <div class="flex justify-between items-center pt-6 border-t border-gray-200/50">
             <a href="delete_journal_entry.php?id=<?php echo $entry_id; ?>" 
               onclick="return confirm('Are you sure you want to permanently delete this journal entry? This action cannot be undone.');"
               class="inline-flex justify-center py-2 px-4 rounded-md text-white bg-red-600 hover:bg-red-700">
                Delete Entry
            </a>
            <button type="submit" class="inline-flex justify-center py-2 px-6 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                Update Entry
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
            <input type="number" step="0.01" name="items[debit][]" class="form-input w-full text-right debit-input" placeholder="0.00">
        </td>
        <td class="p-2">
            <input type="number" step="0.01" name="items[credit][]" class="form-input w-full text-right credit-input" placeholder="0.00">
        </td>
        <td class="p-2 text-center">
            <button type="button" class="remove-row-btn text-red-500 hover:text-red-700 font-bold text-lg">&times;</button>
        </td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const accounts = <?php echo $accounts_json; ?>;
    const existingItems = <?php echo $line_items_json; ?>;
    const container = document.getElementById('journal-items-container');
    const template = document.getElementById('journal-item-template');
    const addRowBtn = document.getElementById('add-row-btn');
    const form = document.getElementById('journal-entry-form');

    function addRow(itemData = null) {
        const newRow = template.content.cloneNode(true);
        const select = newRow.querySelector('.account-select');
        accounts.forEach(acc => {
            const option = document.createElement('option');
            option.value = acc.id;
            option.textContent = `${acc.account_code} - ${acc.account_name}`;
            select.appendChild(option);
        });

        if (itemData) {
            select.value = itemData.account_id;
            if (itemData.debit_amount > 0) {
                newRow.querySelector('.debit-input').value = parseFloat(itemData.debit_amount).toFixed(2);
            }
            if (itemData.credit_amount > 0) {
                newRow.querySelector('.credit-input').value = parseFloat(itemData.credit_amount).toFixed(2);
            }
        }
        container.appendChild(newRow);
    }

    addRowBtn.addEventListener('click', addRow);
    container.addEventListener('click', e => { if (e.target.matches('.remove-row-btn')) { e.target.closest('tr').remove(); updateTotals(); } });
    container.addEventListener('input', e => {
        if (e.target.matches('.debit-input, .credit-input')) {
            const row = e.target.closest('tr');
            const debitInput = row.querySelector('.debit-input');
            const creditInput = row.querySelector('.credit-input');
            if (e.target.matches('.debit-input') && e.target.value) creditInput.value = '';
            else if (e.target.matches('.credit-input') && e.target.value) debitInput.value = '';
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

        if (Math.abs(totalDebit - totalCredit) > 0.001) {
            document.getElementById('imbalance-row').classList.remove('hidden');
        } else {
            document.getElementById('imbalance-row').classList.add('hidden');
        }
    }
    
    form.addEventListener('submit', function(e) {
        const totalDebit = parseFloat(document.getElementById('total-debit').textContent);
        const totalCredit = parseFloat(document.getElementById('total-credit').textContent);
        if (Math.abs(totalDebit - totalCredit) > 0.001) {
            e.preventDefault();
            alert('Cannot save unbalanced entry. Total debits must equal total credits.');
        }
    });

    // Populate with existing data for editing
    if(existingItems.length > 0) {
        existingItems.forEach(item => addRow(item));
    } else {
        addRow(); addRow(); // Start with two blank rows for new entries
    }
    updateTotals();
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>