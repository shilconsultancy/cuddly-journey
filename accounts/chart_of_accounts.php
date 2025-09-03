<?php
// accounts/chart_of_accounts.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Chart of Accounts - BizManager";

// --- MESSAGE HANDLING ---
$message = '';
$message_type = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'deleted') {
        $message = "Account deleted successfully!";
        $message_type = 'success';
    }
    // Add more success messages as needed
}
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'in_use') {
        $message = "Error: Cannot delete account because it is being used in journal entries.";
        $message_type = 'error';
    } elseif ($_GET['error'] == 'delete_failed') {
        $message = "Error: Could not delete the account.";
        $message_type = 'error';
    }
}

// --- FORM PROCESSING FOR NEW ACCOUNTS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && check_permission('Accounts', 'create')) {
    $account_name = trim($_POST['account_name']);
    $account_code = trim($_POST['account_code']);
    $account_type = $_POST['account_type'];
    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : NULL;
    $description = trim($_POST['description']);
    
    $stmt = $conn->prepare("INSERT INTO scs_chart_of_accounts (account_name, account_code, account_type, parent_id, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssis", $account_name, $account_code, $account_type, $parent_id, $description);
    
    if ($stmt->execute()) {
        $message = "Account created successfully!";
        $message_type = 'success';
    } else {
        $message = "Error creating account: " . $stmt->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// --- DATA FETCHING & HIERARCHY BUILDING ---
$accounts_result = $conn->query("SELECT * FROM scs_chart_of_accounts ORDER BY account_code ASC");
$accounts = [];
while ($row = $accounts_result->fetch_assoc()) {
    $accounts[$row['id']] = $row;
}

$account_tree = [];
foreach ($accounts as $id => &$account) {
    if (isset($account['parent_id'])) {
        $accounts[$account['parent_id']]['children'][] =& $account;
    } else {
        $account_tree[] =& $account;
    }
}

// --- RECURSIVE FUNCTION TO DISPLAY THE ACCOUNT TREE ---
function display_accounts_tree($accounts, $level = 0, $app_config) {
    $indent_class = 'pl-' . ($level * 6); // Tailwind uses pl-0, pl-6, pl-12 etc.
    foreach ($accounts as $account) {
        $has_children = !empty($account['children']);
        echo '<tr class="bg-white/50 border-b border-gray-200/50 hover:bg-gray-50/50">';
        echo '<td class="px-6 py-4 ' . $indent_class . '">';
        if ($has_children) {
            echo '<button class="toggle-children mr-2 text-indigo-600" data-target="children-of-'.$account['id'].'">[+]</button>';
        }
        echo '<span class="font-semibold">' . htmlspecialchars($account['account_name']) . '</span>';
        echo '<div class="text-xs text-gray-500 font-mono">' . htmlspecialchars($account['account_code']) . '</div>';
        echo '</td>';
        echo '<td class="px-6 py-4">' . htmlspecialchars($account['account_type']) . '</td>';
        echo '<td class="px-6 py-4 text-right space-x-2">';
        echo '<a href="edit_account.php?id=' . $account['id'] . '" class="font-medium text-indigo-600 hover:underline">Edit</a>';
        echo '<a href="delete_account.php?id=' . $account['id'] . '" class="font-medium text-red-600 hover:underline" onclick="return confirm(\'Are you sure you want to delete this account? This cannot be undone.\');">Delete</a>';
        echo '</td>';
        echo '</tr>';

        if ($has_children) {
            // Children rows are initially hidden
            echo '<tbody class="child-rows hidden" id="children-of-'.$account['id'].'">';
            display_accounts_tree($account['children'], $level + 1, $app_config);
            echo '</tbody>';
        }
    }
}

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Chart of Accounts</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Accounts
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-1">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Add New Account</h3>
            <?php if (!empty($message) && $_SERVER["REQUEST_METHOD"] == "POST"): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <form action="chart_of_accounts.php" method="POST" class="space-y-4">
                <div>
                    <label for="account_name" class="block text-sm font-medium text-gray-700">Account Name</label>
                    <input type="text" name="account_name" id="account_name" class="form-input mt-1 block w-full rounded-md p-3" required>
                </div>
                <div>
                    <label for="account_code" class="block text-sm font-medium text-gray-700">Account Code</label>
                    <input type="text" name="account_code" id="account_code" class="form-input mt-1 block w-full rounded-md p-3" required>
                </div>
                <div>
                    <label for="account_type" class="block text-sm font-medium text-gray-700">Account Type</label>
                    <select name="account_type" id="account_type" class="form-input mt-1 block w-full rounded-md p-3" required>
                        <option value="Asset">Asset</option>
                        <option value="Liability">Liability</option>
                        <option value="Equity">Equity</option>
                        <option value="Revenue">Revenue</option>
                        <option value="Expense">Expense</option>
                    </select>
                </div>
                <div>
                    <label for="parent_id" class="block text-sm font-medium text-gray-700">Parent Account</label>
                    <select name="parent_id" id="parent_id" class="form-input mt-1 block w-full rounded-md p-3">
                        <option value="">None (Top-Level Account)</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['account_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" id="description" rows="3" class="form-input mt-1 block w-full rounded-md p-3"></textarea>
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
            <?php if (!empty($message) && $_SERVER["REQUEST_METHOD"] != "POST"): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Account Name & Code</th>
                            <th scope="col" class="px-6 py-3">Type</th>
                            <th scope="col" class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php display_accounts_tree($account_tree, 0, $app_config); ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.toggle-children').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const childrenRows = document.getElementById(targetId);
            if (childrenRows) {
                const isHidden = childrenRows.classList.contains('hidden');
                childrenRows.classList.toggle('hidden');
                this.textContent = isHidden ? '[-]' : '[+]';
            }
        });
    });
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>