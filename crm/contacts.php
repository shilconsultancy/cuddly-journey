<?php
// crm/contacts.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('CRM', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Contact Management - BizManager";

// Initialize variables
$message = '';
$message_type = '';
$edit_mode = false;
$contact_to_edit = ['id' => '', 'customer_id' => '', 'contact_name' => '', 'job_title' => '', 'email' => '', 'phone' => '', 'notes' => ''];

// --- FORM PROCESSING: ADD or UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customer_id = $_POST['customer_id'];
    $contact_name = trim($_POST['contact_name']);
    $job_title = trim($_POST['job_title']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $notes = trim($_POST['notes']);
    $contact_id = $_POST['contact_id'];

    if (empty($customer_id) || empty($contact_name)) {
        $message = "Company and Contact Name are required.";
        $message_type = 'error';
    } else {
        if (!empty($contact_id) && check_permission('CRM', 'edit')) {
            // --- UPDATE existing contact ---
            $stmt = $conn->prepare("UPDATE scs_contacts SET customer_id = ?, contact_name = ?, job_title = ?, email = ?, phone = ?, notes = ? WHERE id = ?");
            $stmt->bind_param("isssssi", $customer_id, $contact_name, $job_title, $email, $phone, $notes, $contact_id);
            if ($stmt->execute()) {
                log_activity('CONTACT_UPDATED', "Updated contact: " . $contact_name, $conn);
                $message = "Contact updated successfully!";
                $message_type = 'success';
            } else {
                $message = "Error updating contact: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        } elseif (check_permission('CRM', 'create')) {
            // --- ADD new contact ---
            $created_by = $_SESSION['user_id'];
            $stmt = $conn->prepare("INSERT INTO scs_contacts (customer_id, contact_name, job_title, email, phone, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssi", $customer_id, $contact_name, $job_title, $email, $phone, $notes, $created_by);
            if ($stmt->execute()) {
                log_activity('CONTACT_CREATED', "Created new contact: " . $contact_name, $conn);
                $message = "Contact added successfully!";
                $message_type = 'success';
            } else {
                $message = "Error adding contact: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete']) && check_permission('CRM', 'delete')) {
    $contact_id_to_delete = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM scs_contacts WHERE id = ?");
    $stmt->bind_param("i", $contact_id_to_delete);
    if ($stmt->execute()) {
        log_activity('CONTACT_DELETED', "Deleted contact with ID: " . $contact_id_to_delete, $conn);
        $message = "Contact deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting contact.";
        $message_type = 'error';
    }
    $stmt->close();
}

// --- HANDLE EDIT ---
if (isset($_GET['edit']) && check_permission('CRM', 'edit')) {
    $edit_mode = true;
    $contact_id_to_edit = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM scs_contacts WHERE id = ?");
    $stmt->bind_param("i", $contact_id_to_edit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $contact_to_edit = $result->fetch_assoc();
    }
    $stmt->close();
}

// --- DATA FETCHING for the page ---
// Fetch all B2B customers for the dropdown
$b2b_customers_result = $conn->query("SELECT id, customer_name FROM scs_customers WHERE customer_type = 'B2B' ORDER BY customer_name ASC");

// Fetch all contacts with their company name
$contacts_result = $conn->query("
    SELECT cont.*, cust.customer_name 
    FROM scs_contacts cont
    JOIN scs_customers cust ON cont.customer_id = cust.id
    ORDER BY cust.customer_name, cont.contact_name ASC
");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Page Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Manage Contacts</h2>
        <p class="text-gray-600 mt-1">Add, edit, and view contact persons for your B2B customers.</p>
    </div>
    <a href="index.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to CRM
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Add/Edit Contact Form -->
    <?php if (check_permission('CRM', 'create') || check_permission('CRM', 'edit')): ?>
    <div class="lg:col-span-1">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4"><?php echo $edit_mode ? 'Edit Contact' : 'Add New Contact'; ?></h3>
            <form action="contacts.php" method="POST" class="space-y-4">
                <input type="hidden" name="contact_id" value="<?php echo htmlspecialchars($contact_to_edit['id']); ?>">
                <div>
                    <label for="customer_id" class="block text-sm font-medium text-gray-700">Company</label>
                    <select name="customer_id" id="customer_id" class="form-input mt-1 block w-full rounded-md p-3" required>
                        <option value="">Select a B2B customer...</option>
                        <?php while($b2b = $b2b_customers_result->fetch_assoc()): ?>
                            <option value="<?php echo $b2b['id']; ?>" <?php if ($contact_to_edit['customer_id'] == $b2b['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($b2b['customer_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label for="contact_name" class="block text-sm font-medium text-gray-700">Contact Name</label>
                    <input type="text" name="contact_name" id="contact_name" value="<?php echo htmlspecialchars($contact_to_edit['contact_name'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
                </div>
                <div>
                    <label for="job_title" class="block text-sm font-medium text-gray-700">Job Title / Position</label>
                    <input type="text" name="job_title" id="job_title" value="<?php echo htmlspecialchars($contact_to_edit['job_title'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($contact_to_edit['email'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                        <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($contact_to_edit['phone'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                    </div>
                </div>
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea name="notes" id="notes" rows="3" class="form-input mt-1 block w-full rounded-md p-3"><?php echo htmlspecialchars($contact_to_edit['notes'] ?? ''); ?></textarea>
                </div>
                <div class="flex justify-end pt-2">
                    <?php if ($edit_mode): ?>
                        <a href="contacts.php" class="bg-gray-200 py-2 px-4 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-300">Cancel</a>
                    <?php endif; ?>
                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <?php echo $edit_mode ? 'Update Contact' : 'Add Contact'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contacts List -->
    <div class="<?php echo (check_permission('CRM', 'create') || check_permission('CRM', 'edit')) ? 'lg:col-span-2' : 'lg:col-span-3'; ?>">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Contact List</h3>
            <?php if (!empty($message)): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Contact Person</th>
                            <th scope="col" class="px-6 py-3">Company</th>
                            <th scope="col" class="px-6 py-3">Contact Info</th>
                            <th scope="col" class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $contacts_result->fetch_assoc()): ?>
                        <tr class="bg-white/50 border-b border-gray-200/50">
                            <td class="px-6 py-4">
                                <div class="font-semibold"><?php echo htmlspecialchars($row['contact_name']); ?></div>
                                <!-- UPDATED: Display Job Title -->
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['job_title'] ?? ''); ?></div>
                            </td>
                            <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                            <td class="px-6 py-4">
                                <div class="font-semibold"><?php echo htmlspecialchars($row['phone'] ?? ''); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['email'] ?? ''); ?></div>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <?php if (check_permission('CRM', 'edit')): ?>
                                <a href="contacts.php?edit=<?php echo $row['id']; ?>" class="font-medium text-indigo-600 hover:underline">Edit</a>
                                <?php endif; ?>
                                <?php if (check_permission('CRM', 'delete')): ?>
                                <a href="contacts.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this contact?');" class="font-medium text-red-600 hover:underline">Delete</a>
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