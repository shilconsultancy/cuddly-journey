<?php
// settings/payment-methods.php

require_once __DIR__ . '/../templates/header.php';

// --- SECURITY CHECK ---
// Only Super Admins and Administrators can manage payment methods
$user_role_id = $_SESSION['user_role_id'] ?? 0;
if (!in_array($user_role_id, [1, 2])) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}


$page_title = "Manage Payment Methods - BizManager";

// Initialize variables
$message = '';
$message_type = '';
$edit_mode = false;
$method_to_edit = ['id' => '', 'method_name' => '', 'method_type' => 'Other', 'is_active' => 1];

// --- FORM PROCESSING: ADD or UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $method_name = trim($_POST['method_name']);
    $method_type = $_POST['method_type'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $method_id = $_POST['method_id'];

    if (empty($method_name) || empty($method_type)) {
        $message = "Method Name and Type are required.";
        $message_type = 'error';
    } else {
        if (!empty($method_id)) {
            // --- UPDATE existing method ---
            $stmt = $conn->prepare("UPDATE scs_payment_methods SET method_name = ?, method_type = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param("ssii", $method_name, $method_type, $is_active, $method_id);
            if ($stmt->execute()) {
                $message = "Payment method updated successfully!";
                $message_type = 'success';
            } else {
                $message = "Error updating method: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            // --- FIX: DUPLICATE PAYMENT METHOD CHECK ---
            $dupe_check_stmt = $conn->prepare("SELECT id FROM scs_payment_methods WHERE method_name = ?");
            $dupe_check_stmt->bind_param("s", $method_name);
            $dupe_check_stmt->execute();
            $dupe_result = $dupe_check_stmt->get_result();
            if ($dupe_result->num_rows > 0) {
                $message = "Error: A payment method with this name already exists.";
                $message_type = 'error';
            } else {
                // --- ADD new method ---
                $stmt = $conn->prepare("INSERT INTO scs_payment_methods (method_name, method_type, is_active) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $method_name, $method_type, $is_active);
                if ($stmt->execute()) {
                    $message = "Payment method added successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error adding method: " . $stmt->error;
                    $message_type = 'error';
                }
                $stmt->close();
            }
            $dupe_check_stmt->close();
        }
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete'])) {
    $method_id_to_delete = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM scs_payment_methods WHERE id = ?");
    $stmt->bind_param("i", $method_id_to_delete);
    if ($stmt->execute()) {
        $message = "Payment method deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting method. It might be in use.";
        $message_type = 'error';
    }
    $stmt->close();
}

// --- HANDLE EDIT ---
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $method_id_to_edit = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM scs_payment_methods WHERE id = ?");
    $stmt->bind_param("i", $method_id_to_edit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $method_to_edit = $result->fetch_assoc();
    }
    $stmt->close();
}


// --- DATA FETCHING for the list ---
$methods_result = $conn->query("SELECT * FROM scs_payment_methods ORDER BY method_name ASC");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Manage Payment Methods</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Settings
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-1">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4"><?php echo $edit_mode ? 'Edit Method' : 'Add New Method'; ?></h3>
            <form action="payment-methods.php" method="POST" class="space-y-4">
                <input type="hidden" name="method_id" value="<?php echo htmlspecialchars($method_to_edit['id']); ?>">
                <div>
                    <label for="method_name" class="block text-sm font-medium text-gray-700">Method Name</label>
                    <input type="text" name="method_name" id="method_name" value="<?php echo htmlspecialchars($method_to_edit['method_name']); ?>" placeholder="e.g., bKash, Visa Card" class="form-input mt-1 block w-full rounded-md p-3" required>
                </div>
                <div>
                    <label for="method_type" class="block text-sm font-medium text-gray-700">Type</label>
                    <select name="method_type" id="method_type" class="form-input mt-1 block w-full rounded-md p-3" required>
                        <?php $types = ['Cash', 'Card', 'Mobile Banking', 'Other']; ?>
                        <?php foreach($types as $type): ?>
                            <option value="<?php echo $type; ?>" <?php if ($method_to_edit['method_type'] == $type) echo 'selected'; ?>><?php echo $type; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" class="rounded h-4 w-4 text-indigo-600" <?php if ($method_to_edit['is_active']) echo 'checked'; ?>>
                        <span class="ml-2 text-sm text-gray-700">Active</span>
                    </label>
                </div>
                <div class="flex justify-end pt-2">
                    <?php if ($edit_mode): ?>
                        <a href="payment-methods.php" class="bg-gray-200 py-2 px-4 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-300">Cancel</a>
                    <?php endif; ?>
                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <?php echo $edit_mode ? 'Update Method' : 'Add Method'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="glass-card p-6">
            <?php if (!empty($message)): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Method Name</th>
                            <th scope="col" class="px-6 py-3">Type</th>
                            <th scope="col" class="px-6 py-3">Status</th>
                            <th scope="col" class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $methods_result->fetch_assoc()): ?>
                        <tr class="bg-white/50 border-b border-gray-200/50">
                            <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($row['method_name']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($row['method_type']); ?></td>
                            <td class="px-6 py-4">
                                <?php if ($row['is_active']): ?>
                                    <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Active</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="payment-methods.php?edit=<?php echo $row['id']; ?>" class="font-medium text-indigo-600 hover:underline">Edit</a>
                                <a href="payment-methods.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this payment method?');" class="font-medium text-red-600 hover:underline">Delete</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>