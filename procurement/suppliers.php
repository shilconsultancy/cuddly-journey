<?php
// procurement/suppliers.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Procurement', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Supplier Management - BizManager";

// Initialize variables
$message = '';
$message_type = '';
$edit_mode = false;
$supplier_to_edit = ['id' => '', 'supplier_name' => '', 'contact_person' => '', 'email' => '', 'phone' => '', 'address' => ''];

// --- FORM PROCESSING: ADD or UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $supplier_name = trim($_POST['supplier_name']);
    $contact_person = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $supplier_id = $_POST['supplier_id'];

    if (empty($supplier_name)) {
        $message = "Supplier Name is required.";
        $message_type = 'error';
    } else {
        if (!empty($supplier_id) && check_permission('Procurement', 'edit')) {
            $stmt = $conn->prepare("UPDATE scs_suppliers SET supplier_name = ?, contact_person = ?, email = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $supplier_name, $contact_person, $email, $phone, $address, $supplier_id);
            if ($stmt->execute()) {
                log_activity('SUPPLIER_UPDATED', "Updated supplier: " . $supplier_name, $conn);
                $message = "Supplier updated successfully!";
                $message_type = 'success';
            } else {
                $message = "Error updating supplier: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        } elseif (check_permission('Procurement', 'create')) {
            // --- FIX: DUPLICATE SUPPLIER CHECK ---
            $dupe_check_stmt = $conn->prepare("SELECT id FROM scs_suppliers WHERE supplier_name = ?");
            $dupe_check_stmt->bind_param("s", $supplier_name);
            $dupe_check_stmt->execute();
            $dupe_result = $dupe_check_stmt->get_result();
            if ($dupe_result->num_rows > 0) {
                $message = "Error: A supplier with this name already exists.";
                $message_type = 'error';
            } else {
                $created_by = $_SESSION['user_id'];
                $stmt = $conn->prepare("INSERT INTO scs_suppliers (supplier_name, contact_person, email, phone, address, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssi", $supplier_name, $contact_person, $email, $phone, $address, $created_by);
                if ($stmt->execute()) {
                    log_activity('SUPPLIER_CREATED', "Created new supplier: " . $supplier_name, $conn);
                    $message = "Supplier added successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error adding supplier: " . $stmt->error;
                    $message_type = 'error';
                }
                $stmt->close();
            }
            $dupe_check_stmt->close();
        }
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete']) && check_permission('Procurement', 'delete')) {
    $supplier_id_to_delete = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM scs_suppliers WHERE id = ?");
    $stmt->bind_param("i", $supplier_id_to_delete);
    if ($stmt->execute()) {
        log_activity('SUPPLIER_DELETED', "Deleted supplier with ID: " . $supplier_id_to_delete, $conn);
        $message = "Supplier deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting supplier: Cannot delete a supplier that is linked to a purchase order.";
        $message_type = 'error';
    }
    $stmt->close();
}

// --- HANDLE EDIT ---
if (isset($_GET['edit']) && check_permission('Procurement', 'edit')) {
    $edit_mode = true;
    $supplier_id_to_edit = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM scs_suppliers WHERE id = ?");
    $stmt->bind_param("i", $supplier_id_to_edit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $supplier_to_edit = $result->fetch_assoc();
    }
    $stmt->close();
}

// --- DATA FETCHING for the list ---
$suppliers_result = $conn->query("SELECT * FROM scs_suppliers ORDER BY supplier_name ASC");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Manage Suppliers</h2>
        <p class="text-gray-600 mt-1">Add, edit, and view your company's suppliers.</p>
    </div>
    <a href="index.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Procurement
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <?php if (check_permission('Procurement', 'create') || check_permission('Procurement', 'edit')): ?>
    <div class="lg:col-span-1">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4"><?php echo $edit_mode ? 'Edit Supplier' : 'Add New Supplier'; ?></h3>
            <form action="suppliers.php" method="POST" class="space-y-4">
                <input type="hidden" name="supplier_id" value="<?php echo htmlspecialchars($supplier_to_edit['id']); ?>">
                <div>
                    <label for="supplier_name" class="block text-sm font-medium text-gray-700">Supplier Name</label>
                    <input type="text" name="supplier_name" id="supplier_name" value="<?php echo htmlspecialchars($supplier_to_edit['supplier_name']); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
                </div>
                <div>
                    <label for="contact_person" class="block text-sm font-medium text-gray-700">Contact Person</label>
                    <input type="text" name="contact_person" id="contact_person" value="<?php echo htmlspecialchars($supplier_to_edit['contact_person']); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($supplier_to_edit['email']); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                        <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($supplier_to_edit['phone']); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                    </div>
                </div>
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                    <textarea name="address" id="address" rows="3" class="form-input mt-1 block w-full rounded-md p-3"><?php echo htmlspecialchars($supplier_to_edit['address']); ?></textarea>
                </div>
                <div class="flex justify-end pt-2">
                    <?php if ($edit_mode): ?>
                        <a href="suppliers.php" class="bg-gray-200 py-2 px-4 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-300">Cancel</a>
                    <?php endif; ?>
                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <?php echo $edit_mode ? 'Update Supplier' : 'Add Supplier'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?php echo (check_permission('Procurement', 'create') || check_permission('Procurement', 'edit')) ? 'lg:col-span-2' : 'lg:col-span-3'; ?>">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Supplier List</h3>
            <?php if (!empty($message)): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Supplier Name</th>
                            <th scope="col" class="px-6 py-3">Contact</th>
                            <th scope="col" class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $suppliers_result->fetch_assoc()): ?>
                        <tr class="bg-white/50 border-b border-gray-200/50">
                            <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                            <td class="px-6 py-4">
                                <div class="font-semibold"><?php echo htmlspecialchars($row['contact_person']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['email']); ?></div>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="supplier-details.php?id=<?php echo $row['id']; ?>" class="font-medium text-green-600 hover:underline">View</a>
                                <?php if (check_permission('Procurement', 'edit')): ?>
                                <a href="suppliers.php?edit=<?php echo $row['id']; ?>" class="font-medium text-indigo-600 hover:underline">Edit</a>
                                <?php endif; ?>
                                <?php if (check_permission('Procurement', 'delete')): ?>
                                <a href="suppliers.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this supplier?');" class="font-medium text-red-600 hover:underline">Delete</a>
                                <?php endif; ?>
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