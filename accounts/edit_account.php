<?php
// accounts/edit_account.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'edit')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to edit accounts.</div>');
}

$page_title = "Edit Account - BizManager";
$account_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($account_id === 0) {
    header("Location: chart_of_accounts.php");
    exit();
}

// --- FORM PROCESSING ---
$message = '';
$message_type = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $account_name = trim($_POST['account_name']);
    $account_code = trim($_POST['account_code']);
    $account_type = $_POST['account_type'];
    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : NULL;
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE scs_chart_of_accounts SET account_name = ?, account_code = ?, account_type = ?, parent_id = ?, description = ?, is_active = ? WHERE id = ?");
    $stmt->bind_param("sssissi", $account_name, $account_code, $account_type, $parent_id, $description, $is_active, $account_id);
    
    if ($stmt->execute()) {
        $message = "Account updated successfully!";
        $message_type = 'success';
    } else {
        $message = "Error updating account: " . $stmt->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// --- DATA FETCHING ---
$account_stmt = $conn->prepare("SELECT * FROM scs_chart_of_accounts WHERE id = ?");
$account_stmt->bind_param("i", $account_id);
$account_stmt->execute();
$account = $account_stmt->get_result()->fetch_assoc();
$account_stmt->close();

if (!$account) {
    die("Account not found.");
}

// Fetch all other accounts for the parent dropdown, excluding the current one
$all_accounts_result = $conn->query("SELECT id, account_name, account_code FROM scs_chart_of_accounts WHERE id != $account_id ORDER BY account_code ASC");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Edit Account: <span class="text-indigo-600"><?php echo htmlspecialchars($account['account_name']); ?></span></h2>
    <a href="chart_of_accounts.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Chart of Accounts
    </a>
</div>

<div class="glass-card p-8 max-w-2xl mx-auto">
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <form action="edit_account.php?id=<?php echo $account_id; ?>" method="POST" class="space-y-4">
        <div>
            <label for="account_name" class="block text-sm font-medium text-gray-700">Account Name</label>
            <input type="text" name="account_name" id="account_name" value="<?php echo htmlspecialchars($account['account_name']); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
        </div>
        <div>
            <label for="account_code" class="block text-sm font-medium text-gray-700">Account Code</label>
            <input type="text" name="account_code" id="account_code" value="<?php echo htmlspecialchars($account['account_code']); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
        </div>
        <div>
            <label for="account_type" class="block text-sm font-medium text-gray-700">Account Type</label>
            <select name="account_type" id="account_type" class="form-input mt-1 block w-full rounded-md p-3" required>
                <option value="Asset" <?php if ($account['account_type'] == 'Asset') echo 'selected'; ?>>Asset</option>
                <option value="Liability" <?php if ($account['account_type'] == 'Liability') echo 'selected'; ?>>Liability</option>
                <option value="Equity" <?php if ($account['account_type'] == 'Equity') echo 'selected'; ?>>Equity</option>
                <option value="Revenue" <?php if ($account['account_type'] == 'Revenue') echo 'selected'; ?>>Revenue</option>
                <option value="Expense" <?php if ($account['account_type'] == 'Expense') echo 'selected'; ?>>Expense</option>
            </select>
        </div>
        <div>
            <label for="parent_id" class="block text-sm font-medium text-gray-700">Parent Account</label>
            <select name="parent_id" id="parent_id" class="form-input mt-1 block w-full rounded-md p-3">
                <option value="">None</option>
                <?php while($acc = $all_accounts_result->fetch_assoc()): ?>
                    <option value="<?php echo $acc['id']; ?>" <?php if ($account['parent_id'] == $acc['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($acc['account_code'] . ' - ' . $acc['account_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
            <textarea name="description" id="description" rows="3" class="form-input mt-1 block w-full rounded-md p-3"><?php echo htmlspecialchars($account['description']); ?></textarea>
        </div>
        <div>
            <label class="flex items-center">
                <input type="checkbox" name="is_active" value="1" class="rounded h-4 w-4 text-indigo-600" <?php if ($account['is_active']) echo 'checked'; ?>>
                <span class="ml-2 text-sm text-gray-700">Active</span>
            </label>
        </div>
        <div class="flex justify-between items-center pt-2">
            <a href="delete_account.php?id=<?php echo $account_id; ?>" 
               onclick="return confirm('Are you sure you want to permanently delete this account? This action cannot be undone.');" 
               class="inline-flex justify-center py-2 px-4 rounded-md text-white bg-red-600 hover:bg-red-700">
                Delete Account
            </a>
            <button type="submit" class="inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                Update Account
            </button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>