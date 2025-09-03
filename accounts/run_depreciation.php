<?php
// accounts/run_depreciation.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'create')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to run depreciation.</div>');
}

$page_title = "Run Depreciation - BizManager";
$message = '';
$message_type = '';

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $depreciation_month = $_POST['depreciation_month']; // Format YYYY-MM
    $month_end_date = date("Y-m-t", strtotime($depreciation_month . "-01"));

    // IMPORTANT: Get the Account IDs for journal entries from your Chart of Accounts
    // You must create these accounts first.
    $depreciation_expense_account_id = 10; // REPLACE with your actual 'Depreciation Expense' account ID
    $accumulated_depreciation_account_id = 11; // REPLACE with your actual 'Accumulated Depreciation' account ID

    $conn->begin_transaction();
    try {
        // Find all assets that are "In Use" and have a Straight-Line Method
        $assets_to_depreciate_sql = "
            SELECT * FROM scs_fixed_assets 
            WHERE status = 'In Use' 
            AND depreciation_method = 'SLM'
            AND acquisition_date <= ?
        ";
        $stmt = $conn->prepare($assets_to_depreciate_sql);
        $stmt->bind_param("s", $month_end_date);
        $stmt->execute();
        $assets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $depreciation_run_count = 0;

        foreach ($assets as $asset) {
            // Check if depreciation has already been run for this asset for this month
            $check_sql = "SELECT id FROM scs_asset_depreciation_schedule WHERE asset_id = ? AND DATE_FORMAT(depreciation_date, '%Y-%m') = ?";
            $stmt_check = $conn->prepare($check_sql);
            $stmt_check->bind_param("is", $asset['id'], $depreciation_month);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                continue; // Skip if already run
            }

            // Calculate monthly depreciation
            $depreciable_value = $asset['acquisition_cost'] - $asset['salvage_value'];
            $annual_depreciation = $depreciable_value / $asset['useful_life_years'];
            $monthly_depreciation = $annual_depreciation / 12;

            // Check if asset is fully depreciated
            $accumulated_dep_sql = "SELECT COALESCE(SUM(depreciation_amount), 0) as total FROM scs_asset_depreciation_schedule WHERE asset_id = ?";
            $stmt_acc = $conn->prepare($accumulated_dep_sql);
            $stmt_acc->bind_param("i", $asset['id']);
            $stmt_acc->execute();
            $accumulated_dep = $stmt_acc->get_result()->fetch_assoc()['total'];

            if ($accumulated_dep + $monthly_depreciation > $depreciable_value) {
                $monthly_depreciation = $depreciable_value - $accumulated_dep;
            }

            if ($monthly_depreciation > 0) {
                // 1. Create Journal Entry
                $je_description = "Monthly depreciation for " . $asset['asset_name'];
                $debits = [['account_id' => $depreciation_expense_account_id, 'amount' => $monthly_depreciation]];
                $credits = [['account_id' => $accumulated_depreciation_account_id, 'amount' => $monthly_depreciation]];
                
                // create_journal_entry returns true on success, throws exception on failure
                create_journal_entry($conn, $month_end_date, $je_description, $debits, $credits, 'Depreciation', $asset['id']);
                $journal_entry_id = $conn->insert_id;

                // 2. Insert into our depreciation schedule log
                $stmt_schedule = $conn->prepare("INSERT INTO scs_asset_depreciation_schedule (asset_id, depreciation_date, depreciation_amount, journal_entry_id) VALUES (?, ?, ?, ?)");
                $stmt_schedule->bind_param("isdi", $asset['id'], $month_end_date, $monthly_depreciation, $journal_entry_id);
                $stmt_schedule->execute();
                
                $depreciation_run_count++;
            }
        }

        $conn->commit();
        log_activity('DEPRECIATION_RUN', "Ran depreciation for " . date("F Y", strtotime($depreciation_month . "-01")) . ". Processed " . $depreciation_run_count . " assets.", $conn);
        $message = "Depreciation for " . date("F Y", strtotime($depreciation_month . "-01")) . " completed successfully for " . $depreciation_run_count . " assets.";
        $message_type = 'success';
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Run Monthly Depreciation</h2>
    <a href="fixed_assets.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Asset Register
    </a>
</div>

<div class="glass-card p-8 max-w-lg mx-auto">
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <form action="run_depreciation.php" method="POST" class="space-y-4">
        <div>
            <label for="depreciation_month" class="block text-sm font-medium text-gray-700">Select Month to Run Depreciation For</label>
            <input type="month" name="depreciation_month" id="depreciation_month" value="<?php echo date('Y-m'); ?>" class="form-input mt-1 block w-full p-2" required>
            <p class="text-xs text-gray-500 mt-2">This will calculate and post depreciation journal entries for all eligible assets for the selected month. This action cannot be undone.</p>
        </div>
        <div class="flex justify-end pt-2">
            <button type="submit" class="w-full inline-flex justify-center py-3 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700" onclick="return confirm('Are you sure you want to run depreciation for the selected month?');">
                Run Depreciation
            </button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>