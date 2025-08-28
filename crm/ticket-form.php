<?php
// crm/ticket-form.php

// Step 1: Load config and functions first for all backend logic.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Step 2: Initialize all PHP variables and determine the mode.
$edit_mode = false;
$page_title = "Create New Support Ticket";
$ticket_data = [
    'id' => '', 'customer_id' => '', 'contact_id' => '', 'subject' => '',
    'description' => '', 'status' => 'Open', 'priority' => 'Medium', 'assigned_to' => ''
];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    if (!check_permission('Support', 'edit')) { die('Permission denied.'); }
    $edit_mode = true;
    $ticket_id = (int)$_GET['id'];
    $page_title = "Edit Support Ticket";
} else {
    if (!check_permission('Support', 'create')) { die('Permission denied.'); }
}

$message = '';
$message_type = '';

// Step 3: Handle the entire form submission. It will redirect and exit on success.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customer_id = $_POST['customer_id'];
    $contact_id = !empty($_POST['contact_id']) ? $_POST['contact_id'] : NULL;
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);
    $status = $_POST['status'];
    $priority = $_POST['priority'];
    $assigned_to = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : NULL;
    $ticket_id_post = !empty($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;

    if (empty($customer_id) || empty($subject) || empty($description)) {
        $message = "Customer, Subject, and Description are required fields.";
        $message_type = 'error';
    } else {
        $conn->begin_transaction();
        try {
            if ($edit_mode && $ticket_id_post > 0) {
                // --- UPDATE LOGIC ---
                $stmt = $conn->prepare("UPDATE scs_support_tickets SET customer_id=?, contact_id=?, subject=?, description=?, status=?, priority=?, assigned_to=? WHERE id=?");
                $stmt->bind_param("iissssii", $customer_id, $contact_id, $subject, $description, $status, $priority, $assigned_to, $ticket_id_post);
                if (!$stmt->execute()) { throw new Exception($stmt->error); }
                log_activity('TICKET_UPDATED', "Updated support ticket ID: " . $ticket_id_post, $conn);
                $success_message = "updated";

            } else {
                // --- CREATE LOGIC ---
                // NEW: Duplicate Check
                $dupe_check_stmt = $conn->prepare("SELECT ticket_number FROM scs_support_tickets WHERE customer_id = ? AND subject = ? AND status IN ('Open', 'In Progress')");
                $dupe_check_stmt->bind_param("is", $customer_id, $subject);
                $dupe_check_stmt->execute();
                $dupe_result = $dupe_check_stmt->get_result();
                if ($dupe_result->num_rows > 0) {
                    $existing_ticket = $dupe_result->fetch_assoc();
                    throw new Exception("An active ticket ({$existing_ticket['ticket_number']}) with the same subject already exists for this customer.");
                }

                $created_by = $_SESSION['user_id'];
                $placeholder_ticket_number = 'TEMP-' . time();
                $stmt = $conn->prepare("INSERT INTO scs_support_tickets (ticket_number, customer_id, contact_id, subject, description, status, priority, assigned_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("siissssii", $placeholder_ticket_number, $customer_id, $contact_id, $subject, $description, $status, $priority, $assigned_to, $created_by);
                if (!$stmt->execute()) { throw new Exception($stmt->error); }

                $new_ticket_id = $conn->insert_id;
                $ticket_number = 'TKT-' . date('Y') . '-' . str_pad($new_ticket_id, 5, '0', STR_PAD_LEFT);
                $conn->query("UPDATE scs_support_tickets SET ticket_number = '$ticket_number' WHERE id = $new_ticket_id");
                log_activity('TICKET_CREATED', "Created new support ticket: " . $ticket_number, $conn);
                $success_message = "created";
            }
            
            $conn->commit();
            header("Location: tickets.php?success=" . $success_message);
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// --- DATA FETCHING for form in Edit Mode ---
if ($edit_mode) {
    $stmt = $conn->prepare("SELECT * FROM scs_support_tickets WHERE id = ?");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $ticket_data = $result->fetch_assoc();
    } else {
        die("Ticket not found.");
    }
    $stmt->close();
}
$customers_result = $conn->query("SELECT id, customer_name FROM scs_customers WHERE is_active = 1 ORDER BY customer_name ASC");
$users_result = $conn->query("SELECT id, full_name FROM scs_users WHERE is_active = 1 ORDER BY full_name ASC");

// Step 4: Now that all logic is done, we can safely start the HTML output.
require_once __DIR__ . '/../templates/header.php';
?>
<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div><h2 class="text-2xl font-semibold text-gray-800"><?php echo htmlspecialchars($page_title); ?></h2></div>
    <a href="tickets.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">&larr; Back to Ticket List</a>
</div>

<div class="glass-card p-6 lg:p-8 max-w-3xl mx-auto">
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'error' ? 'bg-red-100/80 text-red-800' : 'bg-green-100/80 text-green-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <form action="ticket-form.php<?php if($edit_mode) echo '?id='.$ticket_id; ?>" method="POST" class="space-y-6">
        <input type="hidden" name="ticket_id" value="<?php echo htmlspecialchars($ticket_data['id'] ?? ''); ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <label for="customer_id" class="block text-sm font-medium text-gray-700">Customer</label>
                <select name="customer_id" id="customer_id" class="form-input mt-1 block w-full" required>
                    <option value="">Select a customer...</option>
                    <?php while($customer = $customers_result->fetch_assoc()): ?>
                        <option value="<?php echo $customer['id']; ?>" <?php if(($ticket_data['customer_id'] ?? '') == $customer['id']) echo 'selected'; ?>><?php echo htmlspecialchars($customer['customer_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label for="contact_id" class="block text-sm font-medium text-gray-700">Contact Person (Optional)</label>
                <select name="contact_id" id="contact_id" class="form-input mt-1 block w-full">
                    <option value="">Select a contact...</option>
                </select>
            </div>
        </div>

        <div>
            <label for="subject" class="block text-sm font-medium text-gray-700">Subject</label>
            <input type="text" name="subject" id="subject" value="<?php echo htmlspecialchars($ticket_data['subject'] ?? ''); ?>" class="form-input mt-1 block w-full" required>
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
            <textarea name="description" id="description" rows="6" class="form-input mt-1 block w-full" required><?php echo htmlspecialchars($ticket_data['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                <select name="status" id="status" class="form-input mt-1 block w-full">
                    <?php foreach(['Open', 'In Progress', 'On Hold', 'Closed'] as $status): ?>
                        <option value="<?php echo $status; ?>" <?php if(($ticket_data['status'] ?? 'Open') == $status) echo 'selected'; ?>><?php echo $status; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="priority" class="block text-sm font-medium text-gray-700">Priority</label>
                <select name="priority" id="priority" class="form-input mt-1 block w-full">
                     <?php foreach(['Low', 'Medium', 'High', 'Urgent'] as $priority): ?>
                        <option value="<?php echo $priority; ?>" <?php if(($ticket_data['priority'] ?? 'Medium') == $priority) echo 'selected'; ?>><?php echo $priority; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="assigned_to" class="block text-sm font-medium text-gray-700">Assigned To</label>
                <select name="assigned_to" id="assigned_to" class="form-input mt-1 block w-full">
                    <option value="">Unassigned</option>
                    <?php mysqli_data_seek($users_result, 0); while($user = $users_result->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>" <?php if(($ticket_data['assigned_to'] ?? '') == $user['id']) echo 'selected'; ?>><?php echo htmlspecialchars($user['full_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="flex justify-end pt-4">
            <a href="tickets.php" class="bg-gray-200 py-2 px-5 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-300">Cancel</a>
            <button type="submit" class="ml-3 inline-flex justify-center py-2 px-5 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                <?php echo $edit_mode ? 'Update Ticket' : 'Save Ticket'; ?>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const customerDropdown = document.getElementById('customer_id');
    const contactDropdown = document.getElementById('contact_id');
    const isEditMode = <?php echo $edit_mode ? 'true' : 'false'; ?>;
    const selectedContactId = <?php echo json_encode($ticket_data['contact_id'] ?? null); ?>;

    function fetchContacts(customerId, contactToSelect = null) {
        if (!customerId) {
            contactDropdown.innerHTML = '<option value="">Select a contact...</option>';
            return;
        }
        fetch(`../api/get_contacts.php?customer_id=${customerId}`)
            .then(response => response.json())
            .then(data => {
                let options = '<option value="">None</option>';
                if (data.success && data.contacts.length > 0) {
                    data.contacts.forEach(contact => {
                        const selected = (contact.id == contactToSelect) ? 'selected' : '';
                        options += `<option value="${contact.id}" ${selected}>${contact.contact_name}</option>`;
                    });
                } else {
                    options = '<option value="">No contacts found</option>';
                }
                contactDropdown.innerHTML = options;
            });
    }

    customerDropdown.addEventListener('change', function() {
        fetchContacts(this.value);
    });
    
    if (isEditMode && customerDropdown.value) {
        fetchContacts(customerDropdown.value, selectedContactId);
    }
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>