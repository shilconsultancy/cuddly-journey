<?php
// crm/leads.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('CRM', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Lead Management - BizManager";

// Initialize variables
$message = '';
$message_type = '';
$edit_mode = false;
$lead_to_edit = ['id' => '', 'lead_name' => '', 'company_name' => '', 'email' => '', 'phone' => '', 'source' => '', 'status' => 'New', 'assigned_to' => '', 'notes' => ''];

// --- Color mapping for lead statuses ---
$status_colors = [
    'New' => 'bg-blue-100 text-blue-800',
    'Contacted' => 'bg-yellow-100 text-yellow-800',
    'Qualified' => 'bg-purple-100 text-purple-800',
    'Lost' => 'bg-red-100 text-red-800',
    'Converted' => 'bg-green-100 text-green-800'
];

// Check for messages from redirects
if (isset($_GET['success']) && $_GET['success'] == 'converted') {
    $message = "Lead converted successfully to a customer, contact, and opportunity!";
    $message_type = 'success';
}
if (isset($_GET['error'])) {
    $message = htmlspecialchars($_GET['error']);
    $message_type = 'error';
}


// --- FORM PROCESSING: ADD or UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $lead_name = trim($_POST['lead_name']);
    $company_name = trim($_POST['company_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $source = trim($_POST['source']);
    $status = $_POST['status'];
    $assigned_to = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : NULL;
    $notes = trim($_POST['notes']);
    $lead_id = $_POST['lead_id'];

    if (empty($lead_name)) {
        $message = "Lead Name is required.";
        $message_type = 'error';
    } else {
        if (!empty($lead_id) && check_permission('CRM', 'edit')) {
            // --- UPDATE existing lead ---
            $check_stmt = $conn->prepare("SELECT status FROM scs_leads WHERE id = ?");
            $check_stmt->bind_param("i", $lead_id);
            $check_stmt->execute();
            $existing_lead = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            if ($existing_lead && $existing_lead['status'] === 'Converted') {
                 $message = "Cannot update a lead that has already been converted.";
                 $message_type = 'error';
            } else {
                $stmt = $conn->prepare("UPDATE scs_leads SET lead_name = ?, company_name = ?, email = ?, phone = ?, source = ?, status = ?, assigned_to = ?, notes = ? WHERE id = ?");
                $stmt->bind_param("ssssssisi", $lead_name, $company_name, $email, $phone, $source, $status, $assigned_to, $notes, $lead_id);
                if ($stmt->execute()) {
                    log_activity('LEAD_UPDATED', "Updated lead: " . $lead_name, $conn);
                    $message = "Lead updated successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error updating lead: " . $stmt->error;
                    $message_type = 'error';
                }
                $stmt->close();
            }
        } elseif (check_permission('CRM', 'create')) {
            // --- FIX: DUPLICATE LEAD CHECK ---
            $dupe_check_stmt = $conn->prepare("SELECT id FROM scs_leads WHERE lead_name = ? AND status != 'Converted'");
            $dupe_check_stmt->bind_param("s", $lead_name);
            $dupe_check_stmt->execute();
            $dupe_result = $dupe_check_stmt->get_result();
            if ($dupe_result->num_rows > 0) {
                 $message = "Error: An active lead with this name already exists.";
                 $message_type = 'error';
            } else {
                // --- ADD new lead ---
                $created_by = $_SESSION['user_id'];
                $stmt = $conn->prepare("INSERT INTO scs_leads (lead_name, company_name, email, phone, source, status, assigned_to, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssisi", $lead_name, $company_name, $email, $phone, $source, $status, $assigned_to, $notes, $created_by);
                if ($stmt->execute()) {
                    log_activity('LEAD_CREATED', "Created new lead: " . $lead_name, $conn);
                    $message = "Lead added successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error adding lead: " . $stmt->error;
                    $message_type = 'error';
                }
                $stmt->close();
            }
            $dupe_check_stmt->close();
        }
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete']) && check_permission('CRM', 'delete')) {
    $lead_id_to_delete = $_GET['delete'];
    $check_stmt = $conn->prepare("SELECT status FROM scs_leads WHERE id = ?");
    $check_stmt->bind_param("i", $lead_id_to_delete);
    $check_stmt->execute();
    $existing_lead = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($existing_lead && $existing_lead['status'] === 'Converted') {
        $message = "Cannot delete a lead that has already been converted.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("DELETE FROM scs_leads WHERE id = ?");
        $stmt->bind_param("i", $lead_id_to_delete);
        if ($stmt->execute()) {
            log_activity('LEAD_DELETED', "Deleted lead with ID: " . $lead_id_to_delete, $conn);
            $message = "Lead deleted successfully!";
            $message_type = 'success';
        } else {
            $message = "Error deleting lead.";
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// --- HANDLE EDIT ---
if (isset($_GET['edit']) && check_permission('CRM', 'edit')) {
    $edit_mode = true;
    $lead_id_to_edit = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM scs_leads WHERE id = ?");
    $stmt->bind_param("i", $lead_id_to_edit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $lead_to_edit = $result->fetch_assoc();
        if ($lead_to_edit['status'] === 'Converted') {
            $message = "Converted leads are archived and cannot be edited.";
            $message_type = 'error';
            $edit_mode = false;
        }
    }
    $stmt->close();
}

// --- DATA FETCHING for the page ---
$sales_users_result = $conn->query("
    SELECT u.id, u.full_name 
    FROM scs_users u
    JOIN scs_roles r ON u.role_id = r.id
    WHERE r.role_name IN ('Super Admin', 'Administrator', 'Sales Team')
    ORDER BY u.full_name ASC
");

$leads_result = $conn->query("
    SELECT l.*, u.full_name as assigned_user_name 
    FROM scs_leads l
    LEFT JOIN scs_users u ON l.assigned_to = u.id
    ORDER BY l.created_at DESC
");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Manage Leads</h2>
        <p class="text-gray-600 mt-1">Track and manage potential new customers.</p>
    </div>
    <a href="index.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to CRM
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <?php if (check_permission('CRM', 'create') || check_permission('CRM', 'edit')): ?>
    <div class="lg:col-span-1">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4"><?php echo $edit_mode ? 'Edit Lead' : 'Add New Lead'; ?></h3>
            <form action="leads.php" method="POST" class="space-y-4">
                <input type="hidden" name="lead_id" value="<?php echo htmlspecialchars($lead_to_edit['id'] ?? ''); ?>">
                <div>
                    <label for="lead_name" class="block text-sm font-medium text-gray-700">Lead Name</label>
                    <input type="text" name="lead_name" id="lead_name" value="<?php echo htmlspecialchars($lead_to_edit['lead_name'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
                </div>
                <div>
                    <label for="company_name" class="block text-sm font-medium text-gray-700">Company Name (Optional)</label>
                    <input type="text" name="company_name" id="company_name" value="<?php echo htmlspecialchars($lead_to_edit['company_name'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($lead_to_edit['email'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                        <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($lead_to_edit['phone'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                    </div>
                </div>
                 <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="status" class="form-input mt-1 block w-full rounded-md p-3">
                            <?php 
                            $statuses = ['New', 'Contacted', 'Qualified', 'Lost'];
                            foreach ($statuses as $status) {
                                $selected = (($lead_to_edit['status'] ?? 'New') == $status) ? 'selected' : '';
                                echo "<option value='$status' $selected>$status</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label for="source" class="block text-sm font-medium text-gray-700">Source</label>
                        <input type="text" name="source" id="source" value="<?php echo htmlspecialchars($lead_to_edit['source'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                    </div>
                </div>
                <div>
                    <label for="assigned_to" class="block text-sm font-medium text-gray-700">Assigned To</label>
                    <select name="assigned_to" id="assigned_to" class="form-input mt-1 block w-full rounded-md p-3">
                        <option value="">Unassigned</option>
                        <?php 
                        mysqli_data_seek($sales_users_result, 0);
                        while($user = $sales_users_result->fetch_assoc()): ?>
                            <option value="<?php echo $user['id']; ?>" <?php if (($lead_to_edit['assigned_to'] ?? '') == $user['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($user['full_name'] ?? ''); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea name="notes" id="notes" rows="3" class="form-input mt-1 block w-full rounded-md p-3"><?php echo htmlspecialchars($lead_to_edit['notes'] ?? ''); ?></textarea>
                </div>
                <div class="flex justify-end pt-2">
                    <?php if ($edit_mode): ?>
                        <a href="leads.php" class="bg-gray-200 py-2 px-4 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-300">Cancel</a>
                    <?php endif; ?>
                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <?php echo $edit_mode ? 'Update Lead' : 'Add Lead'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?php echo (check_permission('CRM', 'create') || check_permission('CRM', 'edit')) ? 'lg:col-span-2' : 'lg:col-span-3'; ?>">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Lead List</h3>
            <?php if (!empty($message)): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Lead Name</th>
                            <th scope="col" class="px-6 py-3">Status</th>
                            <th scope="col" class="px-6 py-3">Assigned To</th>
                            <th scope="col" class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $leads_result->fetch_assoc()): ?>
                        <tr class="bg-white/50 border-b border-gray-200/50">
                            <td class="px-6 py-4">
                                <div class="font-semibold"><?php echo htmlspecialchars($row['lead_name'] ?? ''); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['company_name'] ?? ''); ?></div>
                                <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($row['email'] ?? ''); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_colors[$row['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo htmlspecialchars($row['status'] ?? ''); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($row['assigned_user_name'] ?? 'Unassigned'); ?></td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <?php if (($row['status'] ?? '') != 'Converted'): ?>
                                    
                                    <?php if (check_permission('CRM', 'create')): ?>
                                        <a href="convert-lead.php?id=<?php echo $row['id']; ?>" class="font-medium text-green-600 hover:underline" onclick="return confirm('Are you sure you want to convert this lead? This will create a new customer, contact, and opportunity.');">Convert</a>
                                    <?php endif; ?>

                                    <?php if (check_permission('CRM', 'edit')): ?>
                                        <a href="leads.php?edit=<?php echo $row['id']; ?>" class="font-medium text-indigo-600 hover:underline">Edit</a>
                                    <?php endif; ?>
                                    
                                    <?php if (check_permission('CRM', 'delete')): ?>
                                        <a href="leads.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this lead?');" class="font-medium text-red-600 hover:underline">Delete</a>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <span class="text-gray-400 italic text-sm">Archived</span>
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