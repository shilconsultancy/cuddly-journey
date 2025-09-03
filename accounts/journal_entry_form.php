<?php
// accounts/journal_entry_form.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'create')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to create journal entries.</div>');
}

$page_title = "New Journal Entry - BizManager";
$message = '';
$message_type = '';

// --- FORM PROCESSING FOR CREATING A NEW ENTRY ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

        if (empty($description)) {
            throw new Exception("Description is a required field.");
        }

        if (isset($items['account_id'])) {
            for ($i = 0; $i < count($items['account_id']); $i++) {
                $account_id = $items['account_id'][$i];
                $debit = !empty($items['debit'][$i]) ? (float)$items['debit'][$i] : 0;
                $credit = !empty($items['credit'][$i]) ? (float)$items['credit'][$i] : 0;

                if ($account_id && ($debit > 0 || $credit > 0)) {
                    if ($debit > 0) {
                        $debits[] = ['account_id' => $account_id, 'amount' => $debit];
                        $total_debit += $debit;
                    }
                    if ($credit > 0) {
                        $credits[] = ['account_id' => $account_id, 'amount' => $credit];
                        $total_credit += $credit;
                    }
                }
            }
        }

        if (abs($total_debit - $total_credit) > 0.001) {
            throw new Exception("Journal entry is unbalanced. Debits must equal credits.");
        }
        
        if (empty($debits) && empty($credits)) {
            throw new Exception("Cannot create an empty journal entry.");
        }

        // Use the global function to create the entry
        create_journal_entry($conn, $entry_date, $description, $debits, $credits, 'Manual Entry', null, $reference_number);
        
        $conn->commit();
        log_activity('JOURNAL_CREATED', "Created manual journal entry: " . $description, $conn);
        header("Location: journal_entries.php?success=created");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}


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
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'error' ? 'bg-red-100/80 text-red-800' : 'bg-green-100/80 text-green-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="journal_entry_form.php" method="POST" id="journal-entry-form" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label for="entry_date" class="block text-sm font-medium text-gray-700">Date</label>
                <input type="date" name="entry_date" id="entry_date" value="<?php echo date('Y-m-d'); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
            <div>
                <label for="reference_number" class="block text-sm font-medium text-gray-700">Reference # (Optional)</label>
                <input type="text" name="reference_number" id="reference_number" class="form-input mt-1 block w-full rounded-md p-3">
            </div>
            <div class="md:col-span-3">
                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                <input type="text" name="description" id="description" placeholder="e.g., Owner's capital investment" class="form-input mt-1 block w-full rounded-md p-3" required>
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
                    <tbody id="journal-items-container">
                        </tbody>
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
            <button type="button" id="add-row-btn" class="mt-4 px-4 py-2 bg-green-500 text-white text-sm font-semibold rounded-lg hover:bg-green-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block -mt-1 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                Add Line
            </button>
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
            <input type="number" step="0.01" min="0" name="items[debit][]" class="form-input w-full text-right debit-input" placeholder="0.00">
        </td>
        <td class="p-2">
            <input type="number" step="0.01" min="0" name="items[credit][]" class="form-input w-full text-right credit-input" placeholder="0.00">
        </td>
        <td class="p-2 text-center">
            <button type="button" class="remove-row-btn p-1 text-red-500 hover:text-red-700 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </button>
        </td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const accounts = <?php echo $accounts_json; ?>;
    const container = document.getElementById('journal-items-container');
    const template = document.getElementById('journal-item-template');
    const addRowBtn = document.getElementById('add-row-btn');
    const form = document.getElementById('journal-entry-form');

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
    container.addEventListener('click', e => { 
        if (e.target.closest('.remove-row-btn')) { 
            e.target.closest('tr').remove(); 
            updateTotals(); 
        } 
    });
    container.addEventListener('input', e => {
        if (e.target.matches('.debit-input, .credit-input')) {
            const row = e.target.closest('tr');
            const debitInput = row.querySelector('.debit-input');
            const creditInput = row.querySelector('.credit-input');
            if (e.target.matches('.debit-input') && e.target.value) {
                creditInput.value = '';
            } else if (e.target.matches('.credit-input') && e.target.value) {
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

        const imbalanceRow = document.getElementById('imbalance-row');
        if (Math.abs(totalDebit - totalCredit) > 0.001 || (totalDebit === 0 && totalCredit === 0)) {
            imbalanceRow.classList.remove('hidden');
        } else {
            imbalanceRow.classList.add('hidden');
        }
    }
    
    form.addEventListener('submit', function(e) {
        const totalDebit = parseFloat(document.getElementById('total-debit').textContent);
        const totalCredit = parseFloat(document.getElementById('total-credit').textContent);
        if (Math.abs(totalDebit - totalCredit) > 0.001 || (totalDebit === 0 && totalCredit === 0)) {
            e.preventDefault();
            alert('Cannot save an empty or unbalanced entry. Total debits must equal total credits.');
        }
    });

    // Add initial rows
    addRow();
    addRow();
    updateTotals();
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>