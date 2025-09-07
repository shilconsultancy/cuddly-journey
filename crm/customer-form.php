<?php
// crm/customer-form.php

// Step 1: Load config and functions first for all backend logic.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Step 2: Initialize all PHP variables and determine the mode.
$edit_mode = false;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    if (!check_permission('CRM', 'edit')) {
        die('You do not have permission to edit customers.');
    }
    $edit_mode = true;
    $customer_id = (int)$_GET['id'];
} else {
    if (!check_permission('CRM', 'create')) {
        die('You do not have permission to add customers.');
    }
}

$page_title = $edit_mode ? "Edit Customer" : "Add New Customer";

$message = '';
$message_type = '';
$customer_data = ['id' => '', 'customer_name' => '', 'customer_type' => 'B2B', 'email' => '', 'phone' => '', 'address' => ''];
$contact_data = ['id' => '', 'contact_name' => '', 'job_title' => '', 'email' => '', 'phone' => ''];

// Step 3: Handle the entire form submission. It will redirect and exit on success.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customer_name = trim($_POST['customer_name']);
    $customer_type = $_POST['customer_type'];
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $customer_id_post = $_POST['customer_id'];
    $contact_name = trim($_POST['contact_name'] ?? '');
    $job_title = trim($_POST['job_title'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $contact_id_post = $_POST['contact_id'];

    if (empty($customer_name) || ($customer_type === 'B2B' && empty($contact_name))) {
        $message = "Company Name and Contact Name are required for B2B customers.";
        $message_type = 'error';
    } else {
        $conn->begin_transaction();
        try {
            if ($edit_mode && !empty($customer_id_post)) {
                // UPDATE LOGIC
                $stmt = $conn->prepare("UPDATE scs_customers SET customer_name = ?, customer_type = ?, email = ?, phone = ?, address = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $customer_name, $customer_type, $email, $phone, $address, $customer_id_post);
                $stmt->execute();
                if ($customer_type === 'B2B' && !empty($contact_id_post)) {
                    $stmt_contact = $conn->prepare("UPDATE scs_contacts SET contact_name = ?, job_title = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt_contact->bind_param("ssssi", $contact_name, $job_title, $contact_email, $contact_phone, $contact_id_post);
                    $stmt_contact->execute();
                }
                log_activity('CUSTOMER_UPDATED', "Updated customer: " . $customer_name, $conn);
                $success_message = "Customer updated successfully!";
            } else {
                // --- FIX: DUPLICATE CUSTOMER CHECK ---
                $dupe_check_stmt = $conn->prepare("SELECT id FROM scs_customers WHERE customer_name = ? AND is_active = 1");
                $dupe_check_stmt->bind_param("s", $customer_name);
                $dupe_check_stmt->execute();
                $dupe_result = $dupe_check_stmt->get_result();
                if ($dupe_result->num_rows > 0) {
                    throw new Exception("An active customer with this exact name already exists.");
                }
                // --- END FIX ---

                // CREATE LOGIC
                $created_by = $_SESSION['user_id'];
                $stmt = $conn->prepare("INSERT INTO scs_customers (customer_name, customer_type, email, phone, address, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssi", $customer_name, $customer_type, $email, $phone, $address, $created_by);
                $stmt->execute();
                $new_customer_id = $conn->insert_id;
                log_activity('CUSTOMER_CREATED', "Created new customer: " . $customer_name, $conn);
                if ($customer_type === 'B2B') {
                    $stmt_contact = $conn->prepare("INSERT INTO scs_contacts (customer_id, contact_name, job_title, email, phone, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt_contact->bind_param("issssi", $new_customer_id, $contact_name, $job_title, $contact_email, $contact_phone, $created_by);
                    $stmt_contact->execute();
                    $new_contact_id = $conn->insert_id;
                    $stmt_update_cust = $conn->prepare("UPDATE scs_customers SET primary_contact_id = ? WHERE id = ?");
                    $stmt_update_cust->bind_param("ii", $new_contact_id, $new_customer_id);
                    $stmt_update_cust->execute();
                }
                $lead_name_for_lead = ($customer_type === 'B2B') ? $contact_name : $customer_name;
                $email_for_lead = ($customer_type === 'B2B') ? $contact_email : $email;
                $phone_for_lead = ($customer_type === 'B2B') ? $contact_phone : $phone;
                $lead_source = "Direct Customer";
                $lead_status = "New";
                $stmt_lead = $conn->prepare("INSERT INTO scs_leads (lead_name, company_name, email, phone, source, status, created_by, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, NULL)");
                $stmt_lead->bind_param("ssssssi", $lead_name_for_lead, $customer_name, $email_for_lead, $phone_for_lead, $lead_source, $lead_status, $created_by);
                $stmt_lead->execute();
                log_activity('LEAD_CREATED', "Auto-created lead for new customer: " . $customer_name, $conn);
                $success_message = "Customer and corresponding lead created successfully!";
            }
            $conn->commit();
            header("Location: customers.php?success=" . urlencode($success_message));
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $message = "An error occurred: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// --- DATA FETCHING for edit mode ---
if ($edit_mode) {
    $stmt = $conn->prepare("SELECT * FROM scs_customers WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $customer_data = $result->fetch_assoc();
        if ($customer_data['customer_type'] === 'B2B' && !empty($customer_data['primary_contact_id'])) {
            $contact_stmt = $conn->prepare("SELECT * FROM scs_contacts WHERE id = ?");
            $contact_stmt->bind_param("i", $customer_data['primary_contact_id']);
            $contact_stmt->execute();
            $contact_res = $contact_stmt->get_result();
            if($contact_res->num_rows > 0){
                $contact_data = $contact_res->fetch_assoc();
            }
            $contact_stmt->close();
        }
    } else {
        die("Customer not found or is inactive.");
    }
    $stmt->close();
}

// Step 4: Now that all logic is complete, include the header to start sending HTML.
require_once __DIR__ . '/../templates/header.php';
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800"><?php echo htmlspecialchars($page_title); ?></h2>
    </div>
    <a href="customers.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Customer List
    </a>
</div>

<div class="glass-card p-6 lg:p-8 max-w-2xl mx-auto">
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'error' ? 'bg-red-100/80 text-red-800' : 'bg-green-100/80 text-green-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <form action="customer-form.php<?php if ($edit_mode) echo '?id=' . $customer_id; ?>" method="POST" class="space-y-6">
        <input type="hidden" name="customer_id" value="<?php echo htmlspecialchars($customer_data['id'] ?? ''); ?>">
        <input type="hidden" name="contact_id" value="<?php echo htmlspecialchars($contact_data['id'] ?? ''); ?>">
        
        <fieldset class="border-t border-gray-200/50 pt-6">
            <legend id="company_details_legend" class="text-lg font-semibold text-gray-800">Company Information</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mt-4">
                <div>
                    <label for="customer_name" id="customer_name_label" class="block text-sm font-medium text-gray-700">Company Name</label>
                    <input type="text" name="customer_name" id="customer_name" value="<?php echo htmlspecialchars($customer_data['customer_name'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
                </div>
                <div>
                    <label for="customer_type" class="block text-sm font-medium text-gray-700">Customer Type</label>
                    <select name="customer_type" id="customer_type" class="form-input mt-1 block w-full rounded-md p-3">
                        <option value="B2B" <?php if (($customer_data['customer_type'] ?? 'B2B') == 'B2B') echo 'selected'; ?>>B2B (Business)</option>
                        <option value="B2C" <?php if (($customer_data['customer_type'] ?? '') == 'B2C') echo 'selected'; ?>>B2C (Individual)</option>
                    </select>
                </div>
                 <div>
                    <label for="email" id="email_label" class="block text-sm font-medium text-gray-700">Company Email</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($customer_data['email'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                </div>
                <div>
                    <label for="phone" id="phone_label" class="block text-sm font-medium text-gray-700">Company Phone</label>
                    <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($customer_data['phone'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                </div>
            </div>
            <div class="mt-6">
                <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                <textarea name="address" id="address" rows="3" class="form-input mt-1 block w-full rounded-md p-3"><?php echo htmlspecialchars($customer_data['address'] ?? ''); ?></textarea>
            </div>
        </fieldset>

        <fieldset id="b2b_fields" class="border-t border-gray-200/50 pt-6">
            <legend class="text-lg font-semibold text-gray-800">Primary Contact Information</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mt-4">
                 <div>
                    <label for="contact_name" class="block text-sm font-medium text-gray-700">Contact Person Name</label>
                    <input type="text" name="contact_name" id="contact_name" value="<?php echo htmlspecialchars($contact_data['contact_name'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                </div>
                 <div>
                    <label for="job_title" class="block text-sm font-medium text-gray-700">Position / Job Title</label>
                    <input type="text" name="job_title" id="job_title" value="<?php echo htmlspecialchars($contact_data['job_title'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                </div>
                <div>
                    <label for="contact_email" class="block text-sm font-medium text-gray-700">Contact Email</label>
                    <input type="email" name="contact_email" id="contact_email" value="<?php echo htmlspecialchars($contact_data['email'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                </div>
                <div>
                    <label for="contact_phone" class="block text-sm font-medium text-gray-700">Contact Phone</label>
                    <input type="text" name="contact_phone" id="contact_phone" value="<?php echo htmlspecialchars($contact_data['phone'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                </div>
            </div>
        </fieldset>

        <div class="flex justify-end pt-4">
            <a href="customers.php" class="bg-gray-200 py-2 px-5 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-300">Cancel</a>
            <button type="submit" class="ml-3 inline-flex justify-center py-2 px-5 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                <?php echo $edit_mode ? 'Update Customer' : 'Save Customer'; ?>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const customerTypeDropdown = document.getElementById('customer_type');
    const b2bFields = document.getElementById('b2b_fields');
    const companyDetailsLegend = document.getElementById('company_details_legend');
    const customerNameLabel = document.getElementById('customer_name_label');
    const emailLabel = document.getElementById('email_label');
    const phoneLabel = document.getElementById('phone_label');
    const contactNameInput = document.getElementById('contact_name');

    function toggleB2BFields() {
        if (customerTypeDropdown.value === 'B2B') {
            b2bFields.style.display = 'block';
            contactNameInput.required = true;
            companyDetailsLegend.textContent = 'Company Information';
            customerNameLabel.textContent = 'Company Name';
            emailLabel.textContent = 'Company Email';
            phoneLabel.textContent = 'Company Phone';
        } else { // B2C
            b2bFields.style.display = 'none';
            contactNameInput.required = false;
            companyDetailsLegend.textContent = 'Customer Information';
            customerNameLabel.textContent = 'Customer Full Name';
            emailLabel.textContent = 'Email';
            phoneLabel.textContent = 'Phone';
        }
    }

    customerTypeDropdown.addEventListener('change', toggleB2BFields);
    toggleB2BFields();
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>