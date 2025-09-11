<?php
// users/add-user.php

require_once __DIR__ . '/../templates/header.php';

$page_title = "Add New Employee - BizManager";

if (!check_permission('Users', 'create')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to create new users.</div>');
}

// Initialize variables for all form fields
$message = '';
$message_type = '';
$form_data = [
    'full_name' => '', 'father_name' => '', 'mother_name' => '', 'date_of_birth' => '', 'gender' => 'Male',
    'marital_status' => 'Single', 'national_id' => '', 'blood_group' => '', 'religion' => '', 'nationality' => 'Bangladeshi',
    'address' => '', 'permanent_address' => '', 'phone' => '', 'emergency_contact_name' => '', 'emergency_contact_phone' => '',
    'job_title' => '', 'department' => '', 'hire_date' => date('Y-m-d'), 'employment_type' => 'Full-time', 'probation_period_months' => 3,
    'reporting_manager_id' => NULL,
    'basic_salary' => '', 'house_rent_allowance' => '', 'medical_allowance' => '', 'transport_allowance' => '', 'other_allowances' => '',
    'bank_name' => '', 'bank_branch' => '', 'bank_account_number' => '', 'tin_number' => ''
];
$email = '';
$selected_role = '';
$selected_location = '';
$selected_data_scope = 'Local';


// Data fetching for dropdowns
$roles_result = $conn->query("SELECT id, role_name FROM scs_roles ORDER BY role_name ASC");
$locations_result = $conn->query("SELECT id, location_name FROM scs_locations ORDER BY location_name ASC");
$managers_result = $conn->query("SELECT id, full_name FROM scs_users WHERE is_active = 1 ORDER BY full_name ASC");
$modules_result = $conn->query("SELECT * FROM scs_modules ORDER BY module_name ASC");


// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    foreach ($form_data as $key => &$value) {
        if (isset($_POST[$key])) {
            $value = trim($_POST[$key]);
        }
    }
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];
    $role_id = $_POST['role'];
    $location_id = !empty($_POST['location']) ? $_POST['location'] : NULL;
    $data_scope = $_POST['data_scope'];
    $permissions = $_POST['permissions'] ?? [];

    $gross_salary = (float)($form_data['basic_salary'] ?? 0) + (float)($form_data['house_rent_allowance'] ?? 0) + (float)($form_data['medical_allowance'] ?? 0) + (float)($form_data['transport_allowance'] ?? 0) + (float)($form_data['other_allowances'] ?? 0);

    if (empty($form_data['full_name']) || empty($email) || empty($password) || empty($role_id)) {
        $message = "Please fill in all required fields.";
        $message_type = 'error';
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM scs_users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $message = "An account with this email already exists.";
            $message_type = 'error';
        } else {
            $upload_error = false;
            function handle_upload($file_key, $prefix) {
                global $message, $message_type, $upload_error;
                if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == UPLOAD_ERR_OK) {
                    $target_dir = __DIR__ . "/../uploads/documents/";
                    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

                    $file_ext = strtolower(pathinfo($_FILES[$file_key]["name"], PATHINFO_EXTENSION));
                    $unique_filename = $prefix . uniqid() . '.' . $file_ext;
                    $target_file = $target_dir . $unique_filename;
                    
                    $allowed_types = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
                    if (in_array($file_ext, $allowed_types) && $_FILES[$file_key]["size"] <= 5000000) { // 5MB limit
                        if (move_uploaded_file($_FILES[$file_key]["tmp_name"], $target_file)) {
                            return 'uploads/documents/' . $unique_filename;
                        }
                    }
                    $message = "Invalid file type or size for " . $file_key;
                    $message_type = 'error';
                    $upload_error = true;
                    return null;
                }
                return null;
            }
            
            $profile_image_url = handle_upload('profile_image', 'profile_');
            if (!$upload_error) $id_card_url = handle_upload('id_card_scan', 'id_');
            if (!$upload_error) $cv_url = handle_upload('cv_document', 'cv_');
            if (!$upload_error) $edu_cert_url = handle_upload('edu_cert', 'edu_');
            if (!$upload_error) $exp_cert_url = handle_upload('exp_cert', 'exp_');

            if (!$upload_error) {
                 $conn->begin_transaction();
                try {
                    // 1. Insert into scs_users table
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_user = $conn->prepare("INSERT INTO scs_users (full_name, email, password, role_id, location_id, data_scope, profile_image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    // --- FIX: Corrected the type string to 'sssiss' to match the variable types ---
                    $stmt_user->bind_param("sssisis", $form_data['full_name'], $email, $hashed_password, $role_id, $location_id, $data_scope, $profile_image_url);
                    $stmt_user->execute();
                    $new_user_id = $conn->insert_id;

                    // 2. Insert into scs_employee_details table with new salary fields
                    $stmt_hr = $conn->prepare("
                        INSERT INTO scs_employee_details (
                            user_id, father_name, mother_name, date_of_birth, gender, marital_status,
                            national_id, blood_group, religion, nationality, address, permanent_address,
                            phone, emergency_contact_name, emergency_contact_phone, job_title, department,
                            hire_date, employment_type, probation_period_months, reporting_manager_id, 
                            gross_salary, basic_salary, house_rent_allowance, medical_allowance, transport_allowance, other_allowances,
                            bank_name, bank_branch, bank_account_number, tin_number, id_card_url, cv_url, 
                            educational_certs_url, experience_certs_url
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    
                    $stmt_hr->bind_param("issssssssssssssssssiidddddsssssssss",
                        $new_user_id, $form_data['father_name'], $form_data['mother_name'], $form_data['date_of_birth'],
                        $form_data['gender'], $form_data['marital_status'], $form_data['national_id'], $form_data['blood_group'],
                        $form_data['religion'], $form_data['nationality'], $form_data['address'], $form_data['permanent_address'],
                        $form_data['phone'], $form_data['emergency_contact_name'], $form_data['emergency_contact_phone'], $form_data['job_title'],
                        $form_data['department'], $form_data['hire_date'], $form_data['employment_type'], $form_data['probation_period_months'],
                        $form_data['reporting_manager_id'], $gross_salary, $form_data['basic_salary'], $form_data['house_rent_allowance'],
                        $form_data['medical_allowance'], $form_data['transport_allowance'], $form_data['other_allowances'],
                        $form_data['bank_name'], $form_data['bank_branch'], $form_data['bank_account_number'], $form_data['tin_number'], 
                        $id_card_url, $cv_url, $edu_cert_url, $exp_cert_url
                    );
                    $stmt_hr->execute();

                    $company_id = "SBM" . date('Y') . str_pad($new_user_id, 4, '0', STR_PAD_LEFT);
                    $stmt_update_id = $conn->prepare("UPDATE scs_users SET company_id = ? WHERE id = ?");
                    $stmt_update_id->bind_param("si", $company_id, $new_user_id);
                    $stmt_update_id->execute();
                    log_activity('USER_CREATED', "Created new employee: " . htmlspecialchars($form_data['full_name']) . " (ID: " . $new_user_id . ")", $conn);
                    $conn->commit();
                    header("Location: ../hr/employees.php?success=created");
                    exit();

                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Error creating user: " . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
        $stmt_check->close();
    }
}
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Create New Employee</h2>
    <a href="../hr/employees.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Employee List
    </a>
</div>

<div class="glass-card p-8">
    
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="add-user.php" method="POST" enctype="multipart/form-data" class="space-y-8">

        <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">1. Personal Information</legend>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div><label for="full_name" class="block text-sm font-medium text-gray-700">Full Name *</label><input type="text" name="full_name" id="full_name" class="form-input mt-1 block w-full p-2" required></div>
                <div><label for="father_name" class="block text-sm font-medium text-gray-700">Father’s Name *</label><input type="text" name="father_name" id="father_name" class="form-input mt-1 block w-full p-2" required></div>
                <div><label for="mother_name" class="block text-sm font-medium text-gray-700">Mother’s Name *</label><input type="text" name="mother_name" id="mother_name" class="form-input mt-1 block w-full p-2" required></div>
                <div><label for="date_of_birth" class="block text-sm font-medium text-gray-700">Date of Birth *</label><input type="date" name="date_of_birth" id="date_of_birth" class="form-input mt-1 block w-full p-2" required></div>
                <div><label for="gender" class="block text-sm font-medium text-gray-700">Gender *</label><select name="gender" id="gender" class="form-input mt-1 block w-full p-2" required><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select></div>
                <div><label for="marital_status" class="block text-sm font-medium text-gray-700">Marital Status *</label><select name="marital_status" id="marital_status" class="form-input mt-1 block w-full p-2" required><option value="Single">Single</option><option value="Married">Married</option><option value="Divorced">Divorced</option><option value="Widowed">Widowed</option></select></div>
                <div><label for="national_id" class="block text-sm font-medium text-gray-700">National ID / Passport *</label><input type="text" name="national_id" id="national_id" class="form-input mt-1 block w-full p-2" required></div>
                <div><label for="blood_group" class="block text-sm font-medium text-gray-700">Blood Group</label><input type="text" name="blood_group" id="blood_group" class="form-input mt-1 block w-full p-2"></div>
                <div><label for="religion" class="block text-sm font-medium text-gray-700">Religion</label><input type="text" name="religion" id="religion" class="form-input mt-1 block w-full p-2"></div>
                <div><label for="nationality" class="block text-sm font-medium text-gray-700">Nationality *</label><input type="text" name="nationality" id="nationality" value="Bangladeshi" class="form-input mt-1 block w-full p-2" required></div>
            </div>
        </fieldset>

        <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">2. Contact Details</legend>
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label for="present_address" class="block text-sm font-medium text-gray-700">Present Address *</label><textarea name="address" id="present_address" rows="3" class="form-input mt-1 block w-full p-2" required></textarea></div>
                <div><label for="permanent_address" class="block text-sm font-medium text-gray-700">Permanent Address *</label><textarea name="permanent_address" id="permanent_address" rows="3" class="form-input mt-1 block w-full p-2" required></textarea></div>
                <div><label for="mobile_number" class="block text-sm font-medium text-gray-700">Mobile Number *</label><input type="text" name="phone" id="mobile_number" class="form-input mt-1 block w-full p-2" required></div>
                <div><label for="emergency_contact_name" class="block text-sm font-medium text-gray-700">Emergency Contact Name *</label><input type="text" name="emergency_contact_name" id="emergency_contact_name" class="form-input mt-1 block w-full p-2" required></div>
                <div><label for="emergency_contact_phone" class="block text-sm font-medium text-gray-700">Emergency Contact Phone *</label><input type="text" name="emergency_contact_phone" id="emergency_contact_phone" class="form-input mt-1 block w-full p-2" required></div>
             </div>
        </fieldset>

        <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">3. Account & Login</legend>
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label for="email" class="block text-sm font-medium text-gray-700">Email Address *</label><input type="email" name="email" id="email" class="form-input mt-1 block w-full p-2" required></div>
                <div><label for="role" class="block text-sm font-medium text-gray-700">Primary Role *</label><select name="role" id="role" class="form-input mt-1 block w-full p-2" required><option value="">Select a role</option><?php mysqli_data_seek($roles_result, 0); while($role = $roles_result->fetch_assoc()){ echo "<option value='{$role['id']}'>{$role['role_name']}</option>"; } ?></select></div>
                <div><label for="password" class="block text-sm font-medium text-gray-700">Password *</label><input type="password" name="password" id="password" class="form-input mt-1 block w-full p-2" required></div>
                <div><label for="confirm-password" class="block text-sm font-medium text-gray-700">Confirm Password *</label><input type="password" name="confirm-password" id="confirm-password" class="form-input mt-1 block w-full p-2" required></div>
             </div>
        </fieldset>
        
        <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">4. Employment Details</legend>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div><label for="job_title" class="block text-sm font-medium text-gray-700">Designation / Job Title *</label><input type="text" name="job_title" id="job_title" class="form-input mt-1 block w-full p-2" required></div>
                <div><label for="department" class="block text-sm font-medium text-gray-700">Department / Team *</label><input type="text" name="department" id="department" class="form-input mt-1 block w-full p-2" required></div>
                <div><label for="hire_date" class="block text-sm font-medium text-gray-700">Date of Joining *</label><input type="date" name="hire_date" id="hire_date" value="<?php echo date('Y-m-d'); ?>" class="form-input mt-1 block w-full p-2" required></div>
                <div><label for="employment_type" class="block text-sm font-medium text-gray-700">Employment Type *</label><select name="employment_type" id="employment_type" class="form-input mt-1 block w-full p-2" required><option value="Full-time">Full-time</option><option value="Part-time">Part-time</option><option value="Contract">Contract</option><option value="Internship">Internship</option></select></div>
                <div><label for="probation_period_months" class="block text-sm font-medium text-gray-700">Probation Period (Months)</label><input type="number" name="probation_period_months" id="probation_period_months" value="3" class="form-input mt-1 block w-full p-2"></div>
                <div><label for="reporting_manager_id" class="block text-sm font-medium text-gray-700">Reporting Manager</label><select name="reporting_manager_id" id="reporting_manager_id" class="form-input mt-1 block w-full p-2"><option value="">None</option><?php mysqli_data_seek($managers_result, 0); while($mgr = $managers_result->fetch_assoc()){ echo "<option value='{$mgr['id']}'>{$mgr['full_name']}</option>"; } ?></select></div>
                <div><label for="location" class="block text-sm font-medium text-gray-700">Work Location / Branch *</label><select name="location" id="location" class="form-input mt-1 block w-full p-2" required><option value="">Select a location</option><?php mysqli_data_seek($locations_result, 0); while($row = $locations_result->fetch_assoc()){ echo "<option value='{$row['id']}'>{$row['location_name']}</option>"; } ?></select></div>
                <div><label for="data_scope" class="block text-sm font-medium text-gray-700">Data Access Scope *</label><select name="data_scope" id="data_scope" class="form-input mt-1 block w-full p-2" required><option value="Local">Local</option><option value="Global">Global</option></select></div>
            </div>
        </fieldset>
        
        <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">5. Salary & Benefits</legend>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                 <div><label for="basic_salary" class="block text-sm font-medium text-gray-700">Basic Salary *</label><input type="number" step="0.01" name="basic_salary" id="basic_salary" class="form-input salary-component mt-1 block w-full p-2" required></div>
                 <div><label for="house_rent_allowance" class="block text-sm font-medium text-gray-700">House Rent Allowance</label><input type="number" step="0.01" name="house_rent_allowance" id="house_rent_allowance" class="form-input salary-component mt-1 block w-full p-2"></div>
                 <div><label for="medical_allowance" class="block text-sm font-medium text-gray-700">Medical Allowance</label><input type="number" step="0.01" name="medical_allowance" id="medical_allowance" class="form-input salary-component mt-1 block w-full p-2"></div>
                 <div><label for="transport_allowance" class="block text-sm font-medium text-gray-700">Transport Allowance</label><input type="number" step="0.01" name="transport_allowance" id="transport_allowance" class="form-input salary-component mt-1 block w-full p-2"></div>
                 <div><label for="other_allowances" class="block text-sm font-medium text-gray-700">Other Allowances</label><input type="number" step="0.01" name="other_allowances" id="other_allowances" class="form-input salary-component mt-1 block w-full p-2"></div>
                 <div class="bg-indigo-50 p-4 rounded-lg flex flex-col justify-center">
                    <label class="block text-sm font-medium text-gray-700">Gross Salary (Monthly)</label>
                    <p class="text-2xl font-bold text-indigo-600 mt-1"><?php echo $app_config['currency_symbol']; ?> <span id="gross_salary_display">0.00</span></p>
                 </div>
            </div>
             <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                 <div><label for="bank_name" class="block text-sm font-medium text-gray-700">Bank Name</label><input type="text" name="bank_name" id="bank_name" class="form-input mt-1 block w-full p-2"></div>
                 <div><label for="bank_branch" class="block text-sm font-medium text-gray-700">Bank Branch</label><input type="text" name="bank_branch" id="bank_branch" class="form-input mt-1 block w-full p-2"></div>
                 <div><label for="bank_account_number" class="block text-sm font-medium text-gray-700">Bank Account Number</label><input type="text" name="bank_account_number" id="bank_account_number" class="form-input mt-1 block w-full p-2"></div>
                 <div><label for="tin_number" class="block text-sm font-medium text-gray-700">Tax ID (TIN)</label><input type="text" name="tin_number" id="tin_number" class="form-input mt-1 block w-full p-2"></div>
            </div>
        </fieldset>

        <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">6. Document Uploads</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <div><label for="profile_image" class="block text-sm font-medium text-gray-700">Passport-size Photo *</label><input type="file" name="profile_image" id="profile_image" class="form-input mt-1 block w-full" required></div>
                 <div><label for="id_card_scan" class="block text-sm font-medium text-gray-700">NID/Passport Scan *</label><input type="file" name="id_card_scan" id="id_card_scan" class="form-input mt-1 block w-full" required></div>
                 <div><label for="cv_document" class="block text-sm font-medium text-gray-700">CV/Resume *</label><input type="file" name="cv_document" id="cv_document" class="form-input mt-1 block w-full" required></div>
                 <div><label for="edu_cert" class="block text-sm font-medium text-gray-700">Educational Certificates</label><input type="file" name="edu_cert" id="edu_cert" class="form-input mt-1 block w-full"></div>
                 <div><label for="exp_cert" class="block text-sm font-medium text-gray-700">Experience Certificates</label><input type="file" name="exp_cert" id="exp_cert" class="form-input mt-1 block w-full"></div>
            </div>
        </fieldset>
        
        <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">7. Custom Module Permissions</legend>
            <p class="text-sm text-gray-500 mb-4">Set temporary or extra permissions for this user that override their role's defaults. Leave unchecked to use role defaults.</p>
            <div class="space-y-4">
                <?php mysqli_data_seek($modules_result, 0); while($module = $modules_result->fetch_assoc()): ?>
                    <div>
                        <h4 class="font-medium text-gray-700"><?php echo htmlspecialchars($module['module_name']); ?></h4>
                        <div class="flex space-x-6 mt-2">
                            <label class="flex items-center"><input type="checkbox" name="permissions[<?php echo $module['id']; ?>][view]" class="rounded h-4 w-4 text-indigo-600"><span class="ml-2 text-sm">View</span></label>
                            <label class="flex items-center"><input type="checkbox" name="permissions[<?php echo $module['id']; ?>][create]" class="rounded h-4 w-4 text-indigo-600"><span class="ml-2 text-sm">Create</span></label>
                            <label class="flex items-center"><input type="checkbox" name="permissions[<?php echo $module['id']; ?>][edit]" class="rounded h-4 w-4 text-indigo-600"><span class="ml-2 text-sm">Edit</span></label>
                            <label class="flex items-center"><input type="checkbox" name="permissions[<?php echo $module['id']; ?>][delete]" class="rounded h-4 w-4 text-indigo-600"><span class="ml-2 text-sm">Delete</span></label>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </fieldset>

        <div class="flex justify-end pt-8 border-t border-gray-200/50">
            <button type="submit" class="ml-3 inline-flex justify-center py-3 px-6 rounded-md text-white bg-indigo-600 hover:bg-indigo-700 font-semibold text-lg">
                Create Employee Profile
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const salaryComponents = document.querySelectorAll('.salary-component');
    const grossSalaryDisplay = document.getElementById('gross_salary_display');

    function calculateGrossSalary() {
        let total = 0;
        salaryComponents.forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        grossSalaryDisplay.textContent = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    salaryComponents.forEach(input => {
        input.addEventListener('input', calculateGrossSalary);
    });

    calculateGrossSalary(); // Initial calculation
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>