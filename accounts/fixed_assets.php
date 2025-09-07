<?php
// accounts/fixed_assets.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Fixed Asset Register - BizManager";

$message = '';
$message_type = '';

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && check_permission('Accounts', 'create')) {
    $conn->begin_transaction();
    try {
        $asset_name = trim($_POST['asset_name']);
        $asset_code = trim($_POST['asset_code']);
        $location_id = !empty($_POST['location_id']) ? $_POST['location_id'] : NULL;
        $acquisition_date = $_POST['acquisition_date'];
        $acquisition_cost = (float)$_POST['acquisition_cost'];
        $depreciation_method = $_POST['depreciation_method'];
        $useful_life_years = (int)$_POST['useful_life_years'];
        $salvage_value = (float)$_POST['salvage_value'];
        $created_by = $_SESSION['user_id'];

        if (empty($asset_name) || empty($acquisition_date) || $acquisition_cost <= 0 || $useful_life_years <= 0) {
            throw new Exception("Asset Name, Acquisition Date, Cost, and Useful Life are required.");
        }
        
        // --- FIX: DUPLICATE ASSET CODE CHECK ---
        if (!empty($asset_code)) {
            $dupe_check_stmt = $conn->prepare("SELECT id FROM scs_fixed_assets WHERE asset_code = ?");
            $dupe_check_stmt->bind_param("s", $asset_code);
            $dupe_check_stmt->execute();
            $dupe_result = $dupe_check_stmt->get_result();
            if ($dupe_result->num_rows > 0) {
                throw new Exception("An asset with this code/tag already exists.");
            }
            $dupe_check_stmt->close();
        }
        // --- END FIX ---

        $stmt_asset = $conn->prepare("INSERT INTO scs_fixed_assets (asset_name, asset_code, location_id, acquisition_date, acquisition_cost, depreciation_method, useful_life_years, salvage_value, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_asset->bind_param("ssisdsidi", $asset_name, $asset_code, $location_id, $acquisition_date, $acquisition_cost, $depreciation_method, $useful_life_years, $salvage_value, $created_by);
        $stmt_asset->execute();
        $asset_id = $conn->insert_id;

        $je_description = "Acquisition of asset: " . $asset_name;
        
        // IMPORTANT: Ensure you have these accounts in your Chart of Accounts.
        $fixed_asset_account_id = 9; // Replace with your actual 'Fixed Assets - Equipment' account ID from CoA
        $payment_account_id = 3;     // Replace with your actual 'Main Bank Account' ID from CoA

        $debits = [['account_id' => $fixed_asset_account_id, 'amount' => $acquisition_cost]];
        $credits = [['account_id' => $payment_account_id, 'amount' => $acquisition_cost]];
        create_journal_entry($conn, $acquisition_date, $je_description, $debits, $credits, 'Fixed Asset', $asset_id);
        
        $conn->commit();
        log_activity('FIXED_ASSET_CREATED', "Added new asset: " . $asset_name, $conn);
        $message = "Asset added successfully and journal entry created!";
        $message_type = 'success';
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}


// --- DATA FETCHING ---
$assets_result = $conn->query("
    SELECT 
        fa.*, 
        l.location_name,
        (SELECT COALESCE(SUM(ds.depreciation_amount), 0) FROM scs_asset_depreciation_schedule ds WHERE ds.asset_id = fa.id) as accumulated_depreciation
    FROM scs_fixed_assets fa 
    LEFT JOIN scs_locations l ON fa.location_id = l.id
    ORDER BY fa.acquisition_date DESC
");
$locations_result = $conn->query("SELECT id, location_name FROM scs_locations ORDER BY location_name ASC");
$fixed_asset_accounts = $conn->query("SELECT id, account_name, account_code FROM scs_chart_of_accounts WHERE account_type = 'Asset' AND parent_id IS NOT NULL"); // Example, adjust as needed

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Fixed Asset Register</h2>
    <div class="flex space-x-2">
        <a href="run_depreciation.php" class="px-4 py-2 bg-green-600 text-white font-semibold rounded-lg shadow-sm hover:bg-green-700">
            Run Depreciation
        </a>
        <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to Accounts
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-1">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Add New Asset</h3>
            <?php if (!empty($message)): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <form action="fixed_assets.php" method="POST" class="space-y-4">
                 <div>
                    <label for="asset_name" class="block text-sm font-medium text-gray-700">Asset Name</label>
                    <input type="text" name="asset_name" id="asset_name" placeholder="e.g., MacBook Pro 16-inch" class="form-input mt-1 block w-full p-2" required>
                </div>
                <div>
                    <label for="asset_code" class="block text-sm font-medium text-gray-700">Asset Code/Tag (Optional)</label>
                    <input type="text" name="asset_code" id="asset_code" class="form-input mt-1 block w-full p-2">
                </div>
                <div>
                    <label for="location_id" class="block text-sm font-medium text-gray-700">Location</label>
                    <select name="location_id" id="location_id" class="form-input mt-1 block w-full p-2">
                        <option value="">Select location...</option>
                        <?php mysqli_data_seek($locations_result, 0); while($loc = $locations_result->fetch_assoc()): ?>
                            <option value="<?php echo $loc['id']; ?>"><?php echo htmlspecialchars($loc['location_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="acquisition_date" class="block text-sm font-medium text-gray-700">Acquisition Date</label>
                        <input type="date" name="acquisition_date" id="acquisition_date" value="<?php echo date('Y-m-d'); ?>" class="form-input mt-1 block w-full p-2" required>
                    </div>
                    <div>
                        <label for="acquisition_cost" class="block text-sm font-medium text-gray-700">Acquisition Cost</label>
                        <input type="number" step="0.01" name="acquisition_cost" id="acquisition_cost" class="form-input mt-1 block w-full p-2" required>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="useful_life_years" class="block text-sm font-medium text-gray-700">Useful Life (Years)</label>
                        <input type="number" name="useful_life_years" id="useful_life_years" class="form-input mt-1 block w-full p-2" required>
                    </div>
                    <div>
                        <label for="salvage_value" class="block text-sm font-medium text-gray-700">Salvage Value</label>
                        <input type="number" step="0.01" name="salvage_value" id="salvage_value" value="0.00" class="form-input mt-1 block w-full p-2" required>
                    </div>
                </div>
                <div>
                    <label for="depreciation_method" class="block text-sm font-medium text-gray-700">Depreciation Method</label>
                    <select name="depreciation_method" id="depreciation_method" class="form-input mt-1 block w-full p-2">
                        <option value="SLM">Straight-Line Method</option>
                        <option value="WDV" disabled>Written-Down Value (Coming Soon)</option>
                        <option value="None">None</option>
                    </select>
                </div>
                <div class="flex justify-end pt-2">
                    <button type="submit" class="w-full inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Add Asset
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div class="lg:col-span-2">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Asset List</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-3">Asset</th>
                            <th class="px-6 py-3">Acquired</th>
                            <th class="px-6 py-3 text-right">Cost</th>
                            <th class="px-6 py-3 text-right">Book Value</th>
                            <th class="px-6 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($assets_result->num_rows > 0): ?>
                            <?php while($asset = $assets_result->fetch_assoc()): 
                                $book_value = $asset['acquisition_cost'] - $asset['accumulated_depreciation'];
                            ?>
                            <tr class="bg-white/50 border-b border-gray-200/50">
                                <td class="px-6 py-4">
                                    <div class="font-semibold"><?php echo htmlspecialchars($asset['asset_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($asset['location_name'] ?? 'N/A'); ?></div>
                                </td>
                                <td class="px-6 py-4"><?php echo date($app_config['date_format'], strtotime($asset['acquisition_date'])); ?></td>
                                <td class="px-6 py-4 text-right font-mono"><?php echo number_format($asset['acquisition_cost'], 2); ?></td>
                                <td class="px-6 py-4 text-right font-mono font-semibold"><?php echo number_format($book_value, 2); ?></td>
                                <td class="px-6 py-4"><span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800"><?php echo htmlspecialchars($asset['status']); ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                             <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No fixed assets have been added yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>