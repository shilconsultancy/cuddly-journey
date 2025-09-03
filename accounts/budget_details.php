<?php
// accounts/budget_details.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$budget_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($budget_id === 0) {
    header("Location: budgets.php");
    exit();
}

$page_title = "Budget Details - BizManager";
$message = '';
$message_type = '';

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && check_permission('Accounts', 'edit')) {
    $budget_items = $_POST['budget_items'] ?? [];
    $conn->begin_transaction();
    try {
        // Use INSERT ... ON DUPLICATE KEY UPDATE for an efficient upsert
        $stmt = $conn->prepare("
            INSERT INTO scs_budget_items (budget_id, account_id, period, amount) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE amount = VALUES(amount)
        ");

        foreach ($budget_items as $account_id => $months) {
            foreach ($months as $month => $amount) {
                $period_date = date('Y-m-d', strtotime($month . "-01"));
                $amount_val = !empty($amount) ? (float)$amount : 0.00;
                $stmt->bind_param("iisd", $budget_id, $account_id, $period_date, $amount_val);
                $stmt->execute();
            }
        }
        $conn->commit();
        $message = "Budget saved successfully!";
        $message_type = 'success';
        log_activity('BUDGET_UPDATED', "Updated budget ID: " . $budget_id, $conn);

    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error saving budget: " . $e->getMessage();
        $message_type = 'error';
    }
}


// --- DATA FETCHING ---
$budget_stmt = $conn->prepare("SELECT * FROM scs_budgets WHERE id = ?");
$budget_stmt->bind_param("i", $budget_id);
$budget_stmt->execute();
$budget = $budget_stmt->get_result()->fetch_assoc();

if (!$budget) {
    die("Budget not found.");
}

// Fetch Revenue and Expense accounts
$accounts_result = $conn->query("SELECT id, account_name, account_code, account_type FROM scs_chart_of_accounts WHERE account_type IN ('Revenue', 'Expense') AND is_active = 1 ORDER BY account_type, account_code");
$accounts = $accounts_result->fetch_all(MYSQLI_ASSOC);

// Fetch existing budget items
$items_stmt = $conn->prepare("SELECT account_id, DATE_FORMAT(period, '%Y-%m') as month_period, amount FROM scs_budget_items WHERE budget_id = ?");
$items_stmt->bind_param("i", $budget_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$budget_data = [];
while($row = $items_result->fetch_assoc()) {
    $budget_data[$row['account_id']][$row['month_period']] = $row['amount'];
}

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Budget: <?php echo htmlspecialchars($budget['budget_name']); ?> (<?php echo $budget['fiscal_year']; ?>)</h2>
    </div>
    <a href="budgets.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Budgets
    </a>
</div>

<div class="glass-card p-6">
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <form action="budget_details.php?id=<?php echo $budget_id; ?>" method="POST">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="text-xs text-gray-800 uppercase bg-gray-50/50 sticky top-0">
                    <tr>
                        <th class="px-4 py-3">Account</th>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <th class="px-4 py-3 text-right"><?php echo date('M', mktime(0, 0, 0, $m, 10)); ?></th>
                        <?php endfor; ?>
                        <th class="px-4 py-3 text-right font-bold">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $current_type = ''; ?>
                    <?php foreach ($accounts as $account): ?>
                        <?php if ($account['account_type'] !== $current_type): ?>
                            <tr class="bg-gray-100/80"><td colspan="14" class="px-4 py-2 font-bold text-gray-700"><?php echo htmlspecialchars($account['account_type']); ?>s</td></tr>
                            <?php $current_type = $account['account_type']; ?>
                        <?php endif; ?>
                        <tr class="bg-white/50 border-b border-gray-200/50">
                            <td class="px-4 py-2 font-semibold"><?php echo htmlspecialchars($account['account_name']); ?></td>
                            <?php for ($m = 1; $m <= 12; $m++): 
                                $month_key = $budget['fiscal_year'] . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
                                $value = $budget_data[$account['id']][$month_key] ?? '';
                            ?>
                                <td class="p-1">
                                    <input type="number" step="0.01" 
                                           name="budget_items[<?php echo $account['id']; ?>][<?php echo $month_key; ?>]"
                                           value="<?php echo htmlspecialchars($value); ?>"
                                           class="form-input w-full text-right p-2 rounded-md border-gray-300/50 bg-gray-50/50 focus:bg-white focus:ring-indigo-500 focus:border-indigo-500"
                                           oninput="updateTotals(this)">
                                </td>
                            <?php endfor; ?>
                            <td class="px-4 py-2 text-right font-bold font-mono" data-total-for-account="<?php echo $account['id']; ?>">0.00</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="font-bold text-gray-800 bg-gray-100/80">
                    <tr>
                        <td class="px-4 py-3">Monthly Totals</td>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <td class="px-4 py-3 text-right font-mono" data-total-for-month="<?php echo $m; ?>">0.00</td>
                        <?php endfor; ?>
                        <td class="px-4 py-3 text-right font-mono text-lg" id="grand-total">0.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php if (check_permission('Accounts', 'edit')): ?>
        <div class="flex justify-end mt-6">
            <button type="submit" class="inline-flex justify-center py-2 px-6 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                Save Budget
            </button>
        </div>
        <?php endif; ?>
    </form>
</div>

<script>
function updateTotals(element) {
    const table = element.closest('table');
    
    // Update row total
    const row = element.closest('tr');
    let rowTotal = 0;
    row.querySelectorAll('input[type="number"]').forEach(input => {
        rowTotal += parseFloat(input.value) || 0;
    });
    const accountId = row.querySelector('input[type="number"]').name.match(/\[(\d+)\]/)[1];
    table.querySelector(`[data-total-for-account="${accountId}"]`).textContent = rowTotal.toFixed(2);

    // Update column and grand totals
    let grandTotal = 0;
    for (let m = 1; m <= 12; m++) {
        let monthTotal = 0;
        const monthKeyPart = '-' + ('0' + m).slice(-2);
        table.querySelectorAll(`input[name*="[${monthKeyPart}]"]`).forEach(input => {
            monthTotal += parseFloat(input.value) || 0;
        });
        table.querySelector(`[data-total-for-month="${m}"]`).textContent = monthTotal.toFixed(2);
        grandTotal += monthTotal;
    }
    table.querySelector('#grand-total').textContent = grandTotal.toFixed(2);
}

// Initial calculation on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('tbody input[type="number"]').forEach(input => {
        if (input.value) {
            updateTotals(input);
        }
    });
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>