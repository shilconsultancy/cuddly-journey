<?php
// accounts/edit_journal_entry.php

// STEP 1: All PHP logic goes BEFORE any HTML output.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$page_title = "Edit Journal Entry - BizManager";
$message = '';
$message_type = '';
$entry_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($entry_id === 0) {
    header("Location: journal_entries.php");
    exit();
}

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Permission check inside the POST block
    if (!check_permission('Accounts', 'edit')) {
        die('You do not have permission to perform this action.');
    }

    $conn->begin_transaction();
    try {
        $entry_date = $_POST['entry_date'];
        $description = trim($_POST['description']);
        $reference_number = trim($_POST['reference_number']);
        $items = $_POST['items'];

        $debits = [];
        $credits = [];
        $total_debit = 0;
        $total_credit = 0;

        if (isset($items['account_id'])) {
            for ($i = 0; $i < count($items['account_id']); $i++) {
                $account_id = $items['account_id'][$i];
                $debit = !empty($items['debit'][$i]) ? (float)$items['debit'][$i] : 0;
                $credit = !empty($items['credit'][$i]) ? (float)$items['credit'][$i] : 0;
                if ($account_id && ($debit > 0 || $credit > 0)) {
                    if ($debit > 0) { $debits[] = ['account_id' => $account_id, 'amount' => $debit]; $total_debit += $debit; }
                    if ($credit > 0) { $credits[] = ['account_id' => $account_id, 'amount' => $credit]; $total_credit += $credit; }
                }
            }
        }

        if (abs($total_debit - $total_credit) > 0.001) {
            throw new Exception("Journal entry must be balanced.");
        }

        // Update main entry
        $stmt_update = $conn->prepare("UPDATE scs_journal_entries SET entry_date = ?, description = ?, reference_number = ? WHERE id = ?");
        $stmt_update->bind_param("sssi", $entry_date, $description, $reference_number, $entry_id);
        $stmt_update->execute();
        $stmt_update->close();

        // Delete old items
        $delete_items_stmt = $conn->prepare("DELETE FROM scs_journal_entry_items WHERE journal_entry_id = ?");
        $delete_items_stmt->bind_param("i", $entry_id);
        $delete_items_stmt->execute();
        $delete_items_stmt->close();

        // Insert new items
        $stmt_items = $conn->prepare("INSERT INTO scs_journal_entry_items (journal_entry_id, account_id, debit_amount, credit_amount) VALUES (?, ?, ?, ?)");
        foreach($debits as $d) { $c = 0.00; $stmt_items->bind_param("iidd", $entry_id, $d['account_id'], $d['amount'], $c); $stmt_items->execute(); }
        foreach($credits as $c) { $d = 0.00; $stmt_items->bind_param("iidd", $entry_id, $c['account_id'], $d, $c['amount']); $stmt_items->execute(); }
        $stmt_items->close();

        $conn->commit();
        log_activity('JOURNAL_UPDATED', "Updated manual journal entry ID: " . $entry_id, $conn);
        header("Location: journal_entry_details.php?id=" . $entry_id);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// STEP 2: Now that all logic is done, we can safely start the HTML output.
require_once __DIR__ . '/../templates/header.php';

// Final permission check for viewing the page
if (!check_permission('Accounts', 'edit')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to edit journal entries.</div>');
}

// --- DATA FETCHING for form display ---
$entry_stmt = $conn->prepare("SELECT * FROM scs_journal_entries WHERE id = ? AND (source_document IS NULL OR source_document = 'Manual Entry')");
$entry_stmt->bind_param("i", $entry_id);
$entry_stmt->execute();
$entry = $entry_stmt->get_result()->fetch_assoc();
$entry_stmt->close();

if (!$entry) { die('<div class="glass-card p-8 text-center">Manual journal entry not found or cannot be edited.</div>'); }

$items_stmt = $conn->prepare("SELECT * FROM scs_journal_entry_items WHERE journal_entry_id = ? ORDER BY id ASC");
$items_stmt->bind_param("i", $entry_id);
$items_stmt->execute();
$entry_items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

$accounts_result = $conn->query("SELECT id, account_name, account_code FROM scs_chart_of_accounts WHERE is_active = 1 ORDER BY account_code ASC");
$accounts = $accounts_result->fetch_all(MYSQLI_ASSOC);
$accounts_json = json_encode($accounts);

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Edit Journal Entry #<?php echo $entry['id']; ?></h2>
    <a href="journal_entries.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Journal
    </a>
</div>

<div class="glass-card p-8 max-w-4xl mx-auto">
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'error' ? 'bg-red-100/80 text-red-800' : 'bg-green-100/80 text-green-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="edit_journal_entry.php?id=<?php echo $entry_id; ?>" method="POST" id="journal-entry-form" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label for="entry_date" class="block text-sm font-medium text-gray-700">Date</label>
                <input type="date" name="entry_date" id="entry_date" value="<?php echo htmlspecialchars($entry['entry_date']); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
            <div>
                <label for="reference_number" class="block text-sm font-medium text-gray-700">Reference # (Optional)</label>
                <input type="text" name="reference_number" id="reference_number" value="<?php echo htmlspecialchars($entry['reference_number']); ?>" class="form-input mt-1 block w-full rounded-md p-3">
            </div>
            <div class="md:col-span-3">
                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                <input type="text" name="description" id="description" value="<?php echo htmlspecialchars($entry['description']); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
        </div>

        <div class="border-t border-gray-200/50 pt-6">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-800 uppercase">
                        <tr>
                            <th class="px-4 py-2 w-2/5 text-left">Account</th>
                            <th class="px-4 py-2 text-right">Debit</th>
                            <th class="px-4 py-2 text-right">Credit</th>
                            <th class="px-4 py-2 w-12"></th>
                        </tr>
                    </thead>
                    <tbody id="journal-items-container"></tbody>
                    <tfoot class="font-semibold text-gray-800">
                        <tr class="border-t-2 border-gray-300/50">
                            <td class="text-right px-4 py-3">Totals:</td>
                            <td id="total-debit" class="px-4 py-3 text-right font-mono">0.00</td>
                            <td id="total-credit" class="px-4 py-3 text-right font-mono">0.00</td>
                            <td></td>
                        </tr>
                         <tr id="imbalance-row" class="hidden">
                            <td colspan="4" class="text-center p-2 text-red-600 font-bold bg-red-100/50">
                                Totals do not balance!
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <button type="button" id="add-row-btn" class="mt-4 px-4 py-2 bg-green-500 text-white text-sm font-semibold rounded-lg hover:bg-green-600">Add Line</button>
        </div>

        <div class="flex justify-end pt-6 border-t border-gray-200/50">
            <button type="submit" class="inline-flex justify-center py-2 px-6 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Update Entry</button>
        </div>
    </form>
</div>

<template id="journal-item-template">
    <tr class="journal-item-row border-b border-gray-200/50">
        <td class="p-2"><select name="items[account_id][]" class="form-input w-full account-select" required></select></td>
        <td class="p-2"><input type="number" step="0.01" min="0" name="items[debit][]" class="form-input w-full text-right debit-input" placeholder="0.00"></td>
        <td class="p-2"><input type="number" step="0.01" min="0" name="items[credit][]" class="form-input w-full text-right credit-input" placeholder="0.00"></td>
        <td class="p-2 text-center"><button type="button" class="remove-row-btn p-1 text-red-500 hover:text-red-700 rounded-full">&times;</button></td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const accounts = <?php echo $accounts_json; ?>;
    const existingItems = <?php echo json_encode($entry_items); ?>;
    const container = document.getElementById('journal-items-container');
    const template = document.getElementById('journal-item-template');
    const addRowBtn = document.getElementById('add-row-btn');
    const form = document.getElementById('journal-entry-form');

    function addRow(itemData = null) {
        const newRow = template.content.cloneNode(true);
        const select = newRow.querySelector('.account-select');
        let optionsHtml = '<option value="">Select Account</option>';
        accounts.forEach(acc => {
            const selected = (itemData && itemData.account_id == acc.id) ? 'selected' : '';
            optionsHtml += `<option value="${acc.id}" ${selected}>${acc.account_code} - ${acc.account_name}</option>`;
        });
        select.innerHTML = optionsHtml;

        if (itemData) {
            if (parseFloat(itemData.debit_amount) > 0) newRow.querySelector('.debit-input').value = parseFloat(itemData.debit_amount).toFixed(2);
            if (parseFloat(itemData.credit_amount) > 0) newRow.querySelector('.credit-input').value = parseFloat(itemData.credit_amount).toFixed(2);
        }
        container.appendChild(newRow);
    }
    
    addRowBtn.addEventListener('click', () => addRow());
    container.addEventListener('click', e => { if (e.target.closest('.remove-row-btn')) { e.target.closest('tr').remove(); updateTotals(); } });
    container.addEventListener('input', e => {
        if (e.target.matches('.debit-input, .credit-input')) {
            const row = e.target.closest('tr');
            if (e.target.matches('.debit-input') && e.target.value) row.querySelector('.credit-input').value = '';
            else if (e.target.matches('.credit-input') && e.target.value) row.querySelector('.debit-input').value = '';
            updateTotals();
        }
    });

    function updateTotals() {
        let totalDebit = 0, totalCredit = 0;
        container.querySelectorAll('.journal-item-row').forEach(row => {
            totalDebit += parseFloat(row.querySelector('.debit-input').value) || 0;
            totalCredit += parseFloat(row.querySelector('.credit-input').value) || 0;
        });
        document.getElementById('total-debit').textContent = totalDebit.toFixed(2);
        document.getElementById('total-credit').textContent = totalCredit.toFixed(2);
        document.getElementById('imbalance-row').classList.toggle('hidden', Math.abs(totalDebit - totalCredit) < 0.001 && (totalDebit > 0 || totalCredit > 0));
    }
    
    form.addEventListener('submit', function(e) {
        const totalDebit = parseFloat(document.getElementById('total-debit').textContent);
        const totalCredit = parseFloat(document.getElementById('total-credit').textContent);
        if (Math.abs(totalDebit - totalCredit) > 0.001 || (totalDebit === 0 && totalCredit === 0)) {
            e.preventDefault();
            alert('Cannot save an empty or unbalanced entry. Total debits must equal total credits.');
        }
    });

    // Populate with existing items
    if (existingItems.length > 0) {
        existingItems.forEach(item => addRow(item));
    } else {
        addRow(); addRow();
    }
    updateTotals();
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>