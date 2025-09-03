<?php
// accounts/budget_vs_actual.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Budget vs. Actuals Report - BizManager";

// --- FORM HANDLING & DATA FETCHING ---
$selected_budget_id = $_GET['budget_id'] ?? 0;
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

$budgets_result = $conn->query("SELECT id, budget_name, fiscal_year FROM scs_budgets ORDER BY fiscal_year DESC, budget_name ASC");

$report_data = [];
$totals = ['budget' => 0, 'actual' => 0, 'variance' => 0];

if ($selected_budget_id > 0) {
    $sql = "
        SELECT
            coa.id,
            coa.account_name,
            coa.account_code,
            coa.account_type,
            (SELECT COALESCE(SUM(bi.amount), 0) 
             FROM scs_budget_items bi 
             WHERE bi.account_id = coa.id 
             AND bi.budget_id = ? 
             AND bi.period BETWEEN ? AND ?) as budgeted_amount,
            (SELECT COALESCE(SUM(jei.credit_amount - jei.debit_amount), 0)
             FROM scs_journal_entry_items jei
             JOIN scs_journal_entries je ON jei.journal_entry_id = je.id
             WHERE jei.account_id = coa.id
             AND je.entry_date BETWEEN ? AND ?) as actual_amount
        FROM 
            scs_chart_of_accounts coa
        WHERE 
            coa.account_type IN ('Revenue', 'Expense')
        GROUP BY
            coa.id
        HAVING
            budgeted_amount != 0 OR actual_amount != 0
        ORDER BY
            coa.account_type, coa.account_code
    ";

    $stmt = $conn->prepare($sql);
    // --- THIS IS THE CORRECTED LINE ---
    $stmt->bind_param("issss", $selected_budget_id, $start_date, $end_date, $start_date, $end_date);
    $stmt->execute();
    $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6 no-print">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Budget vs. Actuals Report</h2>
    </div>
    <a href="budgets.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Budgets
    </a>
</div>

<div class="glass-card p-4 mb-6 no-print">
    <form action="budget_vs_actual.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label for="budget_id" class="block text-sm font-medium text-gray-700">Select Budget</label>
            <select name="budget_id" id="budget_id" class="form-input mt-1 block w-full" required>
                <option value="">-- Choose a Budget --</option>
                <?php while($budget = $budgets_result->fetch_assoc()): ?>
                    <option value="<?php echo $budget['id']; ?>" <?php if($selected_budget_id == $budget['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($budget['budget_name'] . ' (' . $budget['fiscal_year'] . ')'); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
             <label for="start_date" class="block text-sm font-medium text-gray-700">From</label>
             <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="form-input mt-1 block w-full">
        </div>
        <div>
            <label for="end_date" class="block text-sm font-medium text-gray-700">To</label>
            <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="form-input mt-1 block w-full">
        </div>
        <div>
            <button type="submit" class="w-full inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Generate Report</button>
        </div>
    </form>
</div>

<?php if ($selected_budget_id > 0 && !empty($report_data)): ?>
<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th class="px-6 py-3">Account</th>
                    <th class="px-6 py-3 text-right">Budgeted Amount</th>
                    <th class="px-6 py-3 text-right">Actual Amount</th>
                    <th class="px-6 py-3 text-right">Variance</th>
                </tr>
            </thead>
            <tbody>
                <?php $current_type = ''; $type_totals = ['budget' => 0, 'actual' => 0]; ?>
                <?php foreach ($report_data as $row): ?>
                    <?php
                    if ($row['account_type'] !== $current_type) {
                        if ($current_type !== '') {
                            $variance = ($current_type == 'Revenue') ? $type_totals['actual'] - $type_totals['budget'] : $type_totals['budget'] - $type_totals['actual'];
                            echo '<tr class="bg-gray-100/60 font-bold"><td class="px-6 py-2 text-right">Total ' . $current_type . '</td>';
                            echo '<td class="px-6 py-2 text-right font-mono">' . number_format($type_totals['budget'], 2) . '</td>';
                            echo '<td class="px-6 py-2 text-right font-mono">' . number_format($type_totals['actual'], 2) . '</td>';
                            echo '<td class="px-6 py-2 text-right font-mono ' . ($variance >= 0 ? 'text-green-600' : 'text-red-600') . '">' . number_format($variance, 2) . '</td></tr>';
                        }
                        echo '<tr class="bg-gray-200/80"><td colspan="4" class="px-6 py-2 font-bold text-gray-800">' . htmlspecialchars($row['account_type']) . 's</td></tr>';
                        $current_type = $row['account_type'];
                        $type_totals = ['budget' => 0, 'actual' => 0];
                    }

                    $actual = abs($row['actual_amount']);
                    $budget = $row['budgeted_amount'];
                    $variance = ($row['account_type'] == 'Revenue') ? $actual - $budget : $budget - $actual;

                    $type_totals['budget'] += $budget;
                    $type_totals['actual'] += $actual;

                    if ($row['account_type'] == 'Revenue') {
                        $totals['budget'] += $budget;
                        $totals['actual'] += $actual;
                    } else {
                        $totals['budget'] -= $budget;
                        $totals['actual'] -= $actual;
                    }
                    ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($row['account_name']); ?></td>
                        <td class="px-6 py-4 text-right font-mono"><?php echo number_format($budget, 2); ?></td>
                        <td class="px-6 py-4 text-right font-mono"><?php echo number_format($actual, 2); ?></td>
                        <td class="px-6 py-4 text-right font-mono <?php echo ($variance >= 0 ? 'text-green-600' : 'text-red-600'); ?>">
                            <?php echo number_format($variance, 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php
                    if ($current_type !== '') {
                        $variance = ($current_type == 'Revenue') ? $type_totals['actual'] - $type_totals['budget'] : $type_totals['budget'] - $type_totals['actual'];
                        echo '<tr class="bg-gray-100/60 font-bold"><td class="px-6 py-2 text-right">Total ' . $current_type . '</td>';
                        echo '<td class="px-6 py-2 text-right font-mono">' . number_format($type_totals['budget'], 2) . '</td>';
                        echo '<td class="px-6 py-2 text-right font-mono">' . number_format($type_totals['actual'], 2) . '</td>';
                        echo '<td class="px-6 py-2 text-right font-mono ' . ($variance >= 0 ? 'text-green-600' : 'text-red-600') . '">' . number_format($variance, 2) . '</td></tr>';
                    }
                ?>
            </tbody>
            <tfoot class="font-bold text-gray-800 border-t-2 border-gray-400">
                <?php $net_variance = $totals['actual'] - $totals['budget']; ?>
                <tr class="bg-gray-200/80">
                    <td class="px-6 py-3 text-right">Net Profit / (Loss)</td>
                    <td class="px-6 py-3 text-right font-mono text-lg"><?php echo number_format($totals['budget'], 2); ?></td>
                    <td class="px-6 py-3 text-right font-mono text-lg"><?php echo number_format($totals['actual'], 2); ?></td>
                    <td class="px-6 py-3 text-right font-mono text-lg <?php echo ($net_variance >= 0 ? 'text-green-600' : 'text-red-600'); ?>">
                        <?php echo number_format($net_variance, 2); ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php elseif ($selected_budget_id > 0): ?>
    <div class="glass-card p-6 text-center">
        <p class="text-gray-600">No data found for the selected budget and date range. Please try different dates or ensure the budget has been defined.</p>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>