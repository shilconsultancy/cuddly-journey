<?php
// accounts/budgets.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'view')) { // Assuming view is enough to see, create/edit for actions
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Budgeting - BizManager";
$message = '';
$message_type = '';

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && check_permission('Accounts', 'create')) {
    if (isset($_POST['create_budget'])) {
        $budget_name = trim($_POST['budget_name']);
        $fiscal_year = (int)$_POST['fiscal_year'];
        $description = trim($_POST['description']);
        $created_by = $_SESSION['user_id'];

        // --- FIX: DUPLICATE BUDGET CHECK ---
        $dupe_check_stmt = $conn->prepare("SELECT id FROM scs_budgets WHERE budget_name = ? AND fiscal_year = ?");
        $dupe_check_stmt->bind_param("si", $budget_name, $fiscal_year);
        $dupe_check_stmt->execute();
        $dupe_result = $dupe_check_stmt->get_result();
        if ($dupe_result->num_rows > 0) {
            $message = "Error: A budget with this name already exists for the selected fiscal year.";
            $message_type = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO scs_budgets (budget_name, fiscal_year, description, created_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sisi", $budget_name, $fiscal_year, $description, $created_by);
            if ($stmt->execute()) {
                $new_budget_id = $conn->insert_id;
                header("Location: budget_details.php?id=" . $new_budget_id);
                exit();
            } else {
                $message = "Error creating budget: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
        $dupe_check_stmt->close();
    }
}

// --- DATA FETCHING ---
$budgets_result = $conn->query("SELECT b.*, u.full_name as creator_name FROM scs_budgets b LEFT JOIN scs_users u ON b.created_by = u.id ORDER BY b.fiscal_year DESC, b.budget_name ASC");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Financial Budgets</h2>
    <div class="flex space-x-2">
        <a href="budget_vs_actual.php" class="px-4 py-2 bg-green-600 text-white font-semibold rounded-lg shadow-sm hover:bg-green-700">
            View Report
        </a>
        <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to Accounts
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-1">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Create New Budget</h3>
            <?php if (!empty($message)): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <form action="budgets.php" method="POST" class="space-y-4">
                <div>
                    <label for="budget_name" class="block text-sm font-medium text-gray-700">Budget Name</label>
                    <input type="text" name="budget_name" id="budget_name" placeholder="e.g., Annual Operating Budget" class="form-input mt-1 block w-full p-2" required>
                </div>
                <div>
                    <label for="fiscal_year" class="block text-sm font-medium text-gray-700">Fiscal Year</label>
                    <input type="number" name="fiscal_year" id="fiscal_year" value="<?php echo date('Y'); ?>" class="form-input mt-1 block w-full p-2" required>
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description (Optional)</label>
                    <textarea name="description" id="description" rows="3" class="form-input mt-1 block w-full p-2"></textarea>
                </div>
                <div class="flex justify-end pt-2">
                    <button type="submit" name="create_budget" class="w-full inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Create & Define Budget
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div class="lg:col-span-2">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Existing Budgets</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-3">Budget Name</th>
                            <th class="px-6 py-3">Year</th>
                            <th class="px-6 py-3">Created By</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($budgets_result->num_rows > 0): ?>
                            <?php while($budget = $budgets_result->fetch_assoc()): ?>
                            <tr class="bg-white/50 border-b border-gray-200/50">
                                <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($budget['budget_name']); ?></td>
                                <td class="px-6 py-4 font-mono"><?php echo htmlspecialchars($budget['fiscal_year']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($budget['creator_name'] ?? 'N/A'); ?></td>
                                <td class="px-6 py-4 text-right">
                                    <a href="budget_details.php?id=<?php echo $budget['id']; ?>" class="font-medium text-indigo-600 hover:underline">View/Edit</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                             <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No budgets have been created yet.</td></tr>
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