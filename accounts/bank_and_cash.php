<?php
// accounts/bank_and_cash.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Bank & Cash Management - BizManager";

$message = '';
$message_type = '';

// --- FORM PROCESSING FOR NEW ACCOUNTS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && check_permission('Accounts', 'create')) {
    $account_name = trim($_POST['account_name']);
    $account_code = trim($_POST['account_code']);
    $description = trim($_POST['description']);
    
    // All accounts created here are of type 'Asset'
    $account_type = 'Asset';

    // --- FIX: DUPLICATE ACCOUNT CHECK ---
    $dupe_check_stmt = $conn->prepare("SELECT id FROM scs_chart_of_accounts WHERE account_name = ? OR account_code = ?");
    $dupe_check_stmt->bind_param("ss", $account_name, $account_code);
    $dupe_check_stmt->execute();
    $dupe_result = $dupe_check_stmt->get_result();
    if ($dupe_result->num_rows > 0) {
        $message = "Error: An account with this name or code already exists in the Chart of Accounts.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO scs_chart_of_accounts (account_name, account_code, account_type, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $account_name, $account_code, $account_type, $description);
        
        if ($stmt->execute()) {
            $message = "Account created successfully!";
            $message_type = 'success';
        } else {
            $message = "Error creating account: " . $stmt->error;
            $message_type = 'error';
        }
        $stmt->close();
    }
    $dupe_check_stmt->close();
}


// --- DATA FETCHING ---
$accounts_result = $conn->query("
    SELECT 
        coa.id,
        coa.account_code,
        coa.account_name,
        coa.description,
        (SELECT COALESCE(SUM(jei.debit_amount - jei.credit_amount), 0) 
         FROM scs_journal_entry_items jei 
         WHERE jei.account_id = coa.id) as balance
    FROM 
        scs_chart_of_accounts coa
    WHERE 
        coa.account_type = 'Asset' AND coa.is_active = 1
    ORDER BY 
        coa.account_code ASC
");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Bank & Cash Accounts</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Accounts
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-1">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Add New Account</h3>
            <?php if (!empty($message)): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <form action="bank_and_cash.php" method="POST" class="space-y-4">
                <div>
                    <label for="account_name" class="block text-sm font-medium text-gray-700">Account Name</label>
                    <input type="text" name="account_name" id="account_name" placeholder="e.g., City Bank Checking" class="form-input mt-1 block w-full rounded-md p-3" required>
                </div>
                <div>
                    <label for="account_code" class="block text-sm font-medium text-gray-700">Account Code</label>
                    <input type="text" name="account_code" id="account_code" placeholder="e.g., 1021" class="form-input mt-1 block w-full rounded-md p-3" required>
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description (Optional)</label>
                    <textarea name="description" id="description" rows="3" class="form-input mt-1 block w-full rounded-md p-3" placeholder="e.g., Account number, branch info"></textarea>
                </div>
                <div class="flex justify-end pt-2">
                    <button type="submit" class="w-full inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Add Account
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div class="lg:col-span-2">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Account Balances</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Account</th>
                            <th scope="col" class="px-6 py-3 text-right">Current Balance</th>
                            <th scope="col" class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($accounts_result->num_rows > 0): ?>
                            <?php while($account = $accounts_result->fetch_assoc()): ?>
                            <tr class="bg-white/50 border-b border-gray-200/50">
                                <td class="px-6 py-4">
                                    <div class="font-semibold"><?php echo htmlspecialchars($account['account_name']); ?></div>
                                    <div class="text-xs text-gray-500 font-mono"><?php echo htmlspecialchars($account['account_code']); ?></div>
                                </td>
                                <td class="px-6 py-4 text-right font-mono font-semibold text-lg">
                                    <?php echo number_format($account['balance'], 2); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="edit_account.php?id=<?php echo $account['id']; ?>" class="font-medium text-indigo-600 hover:underline">Edit</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                             <tr>
                                <td colspan="3" class="px-6 py-4 text-center text-gray-500">No asset accounts found. Add one using the form.</td>
                            </tr>
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