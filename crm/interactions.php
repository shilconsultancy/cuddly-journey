<?php
// crm/interactions.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('CRM', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Interaction Log - BizManager";

// Initialize variables
$message = '';
$message_type = '';
$edit_mode = false;
$interaction_to_edit = ['id' => '', 'customer_id' => '', 'contact_id' => '', 'interaction_type' => 'Call', 'interaction_date' => date('Y-m-d\TH:i'), 'summary' => ''];

// --- FORM PROCESSING: ADD or UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customer_id = $_POST['customer_id'];
    $contact_id = !empty($_POST['contact_id']) ? $_POST['contact_id'] : NULL;
    $interaction_type = $_POST['interaction_type'];
    $interaction_date = $_POST['interaction_date'];
    $summary = trim($_POST['summary']);
    $interaction_id = $_POST['interaction_id'];

    if (empty($customer_id) || empty($interaction_type) || empty($interaction_date) || empty($summary)) {
        $message = "Please fill in all required fields.";
        $message_type = 'error';
    } else {
        if (!empty($interaction_id) && check_permission('CRM', 'edit')) {
            // --- UPDATE existing interaction ---
            $stmt = $conn->prepare("UPDATE scs_interactions SET customer_id = ?, contact_id = ?, interaction_type = ?, interaction_date = ?, summary = ? WHERE id = ?");
            $stmt->bind_param("iisssi", $customer_id, $contact_id, $interaction_type, $interaction_date, $summary, $interaction_id);
            if ($stmt->execute()) {
                log_activity('INTERACTION_UPDATED', "Updated interaction log for customer ID: " . $customer_id, $conn);
                $message = "Interaction updated successfully!";
                $message_type = 'success';
            } else {
                $message = "Error updating interaction: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        } elseif (check_permission('CRM', 'create')) {
            // --- ADD new interaction ---
            $created_by = $_SESSION['user_id'];
            $stmt = $conn->prepare("INSERT INTO scs_interactions (customer_id, contact_id, interaction_type, interaction_date, summary, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssi", $customer_id, $contact_id, $interaction_type, $interaction_date, $summary, $created_by);
            if ($stmt->execute()) {
                log_activity('INTERACTION_CREATED', "Logged new interaction for customer ID: " . $customer_id, $conn);
                $message = "Interaction logged successfully!";
                $message_type = 'success';
            } else {
                $message = "Error logging interaction: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete']) && check_permission('CRM', 'delete')) {
    $interaction_id_to_delete = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM scs_interactions WHERE id = ?");
    $stmt->bind_param("i", $interaction_id_to_delete);
    if ($stmt->execute()) {
        log_activity('INTERACTION_DELETED', "Deleted interaction log with ID: " . $interaction_id_to_delete, $conn);
        $message = "Interaction deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting interaction.";
        $message_type = 'error';
    }
    $stmt->close();
}

// --- HANDLE EDIT ---
if (isset($_GET['edit']) && check_permission('CRM', 'edit')) {
    $edit_mode = true;
    $interaction_id_to_edit = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM scs_interactions WHERE id = ?");
    $stmt->bind_param("i", $interaction_id_to_edit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $interaction_to_edit = $result->fetch_assoc();
        // Format date for the datetime-local input
        $interaction_to_edit['interaction_date'] = date('Y-m-d\TH:i', strtotime($interaction_to_edit['interaction_date']));
    }
    $stmt->close();
}

// --- DATA FETCHING for the page ---
$customers_result = $conn->query("SELECT id, customer_name FROM scs_customers ORDER BY customer_name ASC");
$interactions_result = $conn->query("
    SELECT i.*, cust.customer_name, cont.contact_name, u.full_name as creator_name
    FROM scs_interactions i
    JOIN scs_customers cust ON i.customer_id = cust.id
    LEFT JOIN scs_contacts cont ON i.contact_id = cont.id
    LEFT JOIN scs_users u ON i.created_by = u.id
    ORDER BY i.interaction_date DESC
");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Page Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Interaction Log</h2>
        <p class="text-gray-600 mt-1">Log and view all customer communications.</p>
    </div>
    <a href="index.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to CRM
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Add/Edit Interaction Form -->
    <?php if (check_permission('CRM', 'create') || check_permission('CRM', 'edit')): ?>
    <div class="lg:col-span-1">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4"><?php echo $edit_mode ? 'Edit Interaction' : 'Log New Interaction'; ?></h3>
            <form action="interactions.php" method="POST" class="space-y-4">
                <input type="hidden" name="interaction_id" value="<?php echo htmlspecialchars($interaction_to_edit['id']); ?>">
                <div>
                    <label for="customer_id" class="block text-sm font-medium text-gray-700">Customer</label>
                    <select name="customer_id" id="customer_id" class="form-input mt-1 block w-full rounded-md p-3" required>
                        <option value="">Select a customer...</option>
                        <?php while($cust = $customers_result->fetch_assoc()): ?>
                            <option value="<?php echo $cust['id']; ?>" <?php if ($interaction_to_edit['customer_id'] == $cust['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($cust['customer_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                 <div id="contact_person_field">
                    <label for="contact_id" class="block text-sm font-medium text-gray-700">Contact Person (Optional)</label>
                    <select name="contact_id" id="contact_id" class="form-input mt-1 block w-full rounded-md p-3">
                        <option value="">Select a contact...</option>
                        <!-- Options will be loaded by JavaScript -->
                    </select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="interaction_type" class="block text-sm font-medium text-gray-700">Type</label>
                        <select name="interaction_type" id="interaction_type" class="form-input mt-1 block w-full rounded-md p-3">
                            <?php 
                            $types = ['Call', 'Email', 'Meeting', 'Note'];
                            foreach ($types as $type) {
                                $selected = ($interaction_to_edit['interaction_type'] == $type) ? 'selected' : '';
                                echo "<option value='$type' $selected>$type</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label for="interaction_date" class="block text-sm font-medium text-gray-700">Date & Time</label>
                        <input type="datetime-local" name="interaction_date" id="interaction_date" value="<?php echo htmlspecialchars($interaction_to_edit['interaction_date']); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
                    </div>
                </div>
                <div>
                    <label for="summary" class="block text-sm font-medium text-gray-700">Summary</label>
                    <textarea name="summary" id="summary" rows="4" class="form-input mt-1 block w-full rounded-md p-3" required><?php echo htmlspecialchars($interaction_to_edit['summary']); ?></textarea>
                </div>
                <div class="flex justify-end pt-2">
                    <?php if ($edit_mode): ?>
                        <a href="interactions.php" class="bg-gray-200 py-2 px-4 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-300">Cancel</a>
                    <?php endif; ?>
                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <?php echo $edit_mode ? 'Update Log' : 'Save Log'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Interactions List -->
    <div class="<?php echo (check_permission('CRM', 'create') || check_permission('CRM', 'edit')) ? 'lg:col-span-2' : 'lg:col-span-3'; ?>">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Interactions</h3>
            <?php if (!empty($message)): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="overflow-y-auto max-h-[75vh]">
                <ul class="space-y-4">
                    <?php while($row = $interactions_result->fetch_assoc()): ?>
                    <li class="bg-white/60 p-4 rounded-lg border border-gray-200/50">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-bold text-gray-800"><?php echo htmlspecialchars($row['customer_name']); ?></p>
                                <?php if (!empty($row['contact_name'])): ?>
                                    <p class="text-sm text-gray-600">with <?php echo htmlspecialchars($row['contact_name']); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold text-gray-800 bg-gray-100 rounded-full">
                                <?php echo htmlspecialchars($row['interaction_type']); ?>
                            </span>
                        </div>
                        <p class="text-gray-700 my-3"><?php echo nl2br(htmlspecialchars($row['summary'])); ?></p>
                        <div class="text-xs text-gray-500 flex justify-between items-center pt-3 border-t border-gray-200/50">
                            <span>Logged by: <?php echo htmlspecialchars($row['creator_name'] ?? 'System'); ?></span>
                            <span><?php echo date($app_config['date_format'] . ' H:i', strtotime($row['interaction_date'])); ?></span>
                        </div>
                    </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const customerDropdown = document.getElementById('customer_id');
    const contactDropdown = document.getElementById('contact_id');
    const contactField = document.getElementById('contact_person_field');

    function fetchContacts(customerId, selectedContactId = null) {
        if (!customerId) {
            contactDropdown.innerHTML = '<option value="">Select a contact...</option>';
            return;
        }

        fetch(`../api/get_contacts.php?customer_id=${customerId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let options = '<option value="">Select a contact...</option>';
                    data.contacts.forEach(contact => {
                        const selected = (contact.id == selectedContactId) ? 'selected' : '';
                        options += `<option value="${contact.id}" ${selected}>${contact.contact_name}</option>`;
                    });
                    contactDropdown.innerHTML = options;
                }
            });
    }

    customerDropdown.addEventListener('change', function() {
        fetchContacts(this.value);
    });

    // On page load, if a customer is already selected (in edit mode), fetch their contacts.
    if (customerDropdown.value) {
        const selectedContactId = <?php echo json_encode($interaction_to_edit['contact_id']); ?>;
        fetchContacts(customerDropdown.value, selectedContactId);
    }
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>