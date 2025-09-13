<?php
// users/edit-user.php

// --- All PHP logic is now at the TOP of the file ---
require_once __DIR__ . '/../config.php';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$user_id) {
    header("Location: index.php");
    exit();
}

$message = '';
$message_type = '';

// Check if the user's details exist to determine if we need to INSERT or UPDATE
$check_stmt = $conn->prepare("SELECT id FROM scs_employee_details WHERE user_id = ?");
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$employee_details_exist = $check_result->num_rows > 0;
$check_stmt->close();


// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    try {
        // --- Retrieve All Form Data ---
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role_id = $_POST['role'];
        $location_id = !empty($_POST['location']) ? $_POST['location'] : NULL;
        $data_scope = $_POST['data_scope'];
        $password = $_POST['password'];
        $permissions = $_POST['permissions'] ?? [];
        
        $father_name = trim($_POST['father_name']);
        $mother_name = trim($_POST['mother_name']);
        $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : NULL;
        $gender = $_POST['gender'];
        $marital_status = $_POST['marital_status'];
        $national_id = trim($_POST['national_id']);
        $blood_group = trim($_POST['blood_group']);
        $religion = trim($_POST['religion']);
        $nationality = trim($_POST['nationality']);
        $address = trim($_POST['address']);
        $permanent_address = trim($_POST['permanent_address']);
        $phone = trim($_POST['phone']);
        $emergency_contact_name = trim($_POST['emergency_contact_name']);
        $emergency_contact_phone = trim($_POST['emergency_contact_phone']);
        $job_title = trim($_POST['job_title']);
        $department = trim($_POST['department']);
        $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : NULL;
        $employment_type = $_POST['employment_type'];
        $probation_period_months = !empty($_POST['probation_period_months']) ? (int)$_POST['probation_period_months'] : NULL;
        $reporting_manager_id = !empty($_POST['reporting_manager_id']) ? (int)$_POST['reporting_manager_id'] : NULL;
        
        $basic_salary = (float)($_POST['basic_salary'] ?? 0);
        $house_rent_allowance = (float)($_POST['house_rent_allowance'] ?? 0);
        $medical_allowance = (float)($_POST['medical_allowance'] ?? 0);
        $transport_allowance = (float)($_POST['transport_allowance'] ?? 0);
        $other_allowances = (float)($_POST['other_allowances'] ?? 0);
        $gross_salary = $basic_salary + $house_rent_allowance + $medical_allowance + $transport_allowance + $other_allowances;

        $bank_name = trim($_POST['bank_name']);
        $bank_branch = trim($_POST['bank_branch']);
        $bank_account_number = trim($_POST['bank_account_number']);
        $tin_number = trim($_POST['tin_number']);

        $stmt_user = $conn->prepare("UPDATE scs_users SET full_name = ?, email = ?, role_id = ?, location_id = ?, data_scope = ? WHERE id = ?");
        $stmt_user->bind_param("ssiisi", $full_name, $email, $role_id, $location_id, $data_scope, $user_id);
        $stmt_user->execute();
        $stmt_user->close();
        
        if ($employee_details_exist) {
            $stmt_hr = $conn->prepare("
                UPDATE scs_employee_details SET 
                father_name=?, mother_name=?, date_of_birth=?, gender=?, marital_status=?, national_id=?, 
                blood_group=?, religion=?, nationality=?, address=?, permanent_address=?, phone=?, 
                emergency_contact_name=?, emergency_contact_phone=?, job_title=?, department=?, hire_date=?, 
                employment_type=?, probation_period_months=?, reporting_manager_id=?, 
                gross_salary=?, basic_salary=?, house_rent_allowance=?, medical_allowance=?, transport_allowance=?, other_allowances=?,
                bank_name=?, bank_branch=?, bank_account_number=?, tin_number=?
                WHERE user_id = ?
            ");
            $stmt_hr->bind_param("ssssssssssssssssssiidddddsssssi", 
                $father_name, $mother_name, $date_of_birth, $gender, $marital_status, $national_id, 
                $blood_group, $religion, $nationality, $address, $permanent_address, $phone, 
                $emergency_contact_name, $emergency_contact_phone, $job_title, $department, $hire_date,
                $employment_type, $probation_period_months, $reporting_manager_id, 
                $gross_salary, $basic_salary, $house_rent_allowance, $medical_allowance, $transport_allowance, $other_allowances,
                $bank_name, $bank_branch, $bank_account_number, $tin_number, $user_id
            );
        } else {
            $stmt_hr = $conn->prepare("
                INSERT INTO scs_employee_details (
                    user_id, father_name, mother_name, date_of_birth, gender, marital_status,
                    national_id, blood_group, religion, nationality, address, permanent_address,
                    phone, emergency_contact_name, emergency_contact_phone, job_title, department,
                    hire_date, employment_type, probation_period_months, reporting_manager_id, 
                    gross_salary, basic_salary, house_rent_allowance, medical_allowance, transport_allowance, other_allowances,
                    bank_name, bank_branch, bank_account_number, tin_number
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt_hr->bind_param("issssssssssssssssssiidddddsssss",
                $user_id, $father_name, $mother_name, $date_of_birth, $gender, $marital_status, $national_id, 
                $blood_group, $religion, $nationality, $address, $permanent_address, $phone, 
                $emergency_contact_name, $emergency_contact_phone, $job_title, $department, $hire_date,
                $employment_type, $probation_period_months, $reporting_manager_id, 
                $gross_salary, $basic_salary, $house_rent_allowance, $medical_allowance, $transport_allowance, $other_allowances,
                $bank_name, $bank_branch, $bank_account_number, $tin_number
            );
        }
        $stmt_hr->execute();
        $stmt_hr->close();

        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_pass = $conn->prepare("UPDATE scs_users SET password = ? WHERE id = ?");
            $stmt_pass->bind_param("si", $hashed_password, $user_id);
            $stmt_pass->execute();
            $stmt_pass->close();
        }
        
        $conn->query("DELETE FROM scs_user_permissions WHERE user_id = $user_id");
        if (!empty($permissions)) {
            $stmt_perm = $conn->prepare("INSERT INTO scs_user_permissions (user_id, module_id, can_view, can_create, can_edit, can_delete) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($permissions as $module_id => $actions) {
                $can_view = isset($actions['view']) ? 1 : 0;
                $can_create = isset($actions['create']) ? 1 : 0;
                $can_edit = isset($actions['edit']) ? 1 : 0;
                $can_delete = isset($actions['delete']) ? 1 : 0;
                if ($can_view || $can_create || $can_edit || $can_delete) {
                    $stmt_perm->bind_param("iiiiii", $user_id, $module_id, $can_view, $can_create, $can_edit, $can_delete);
                    $stmt_perm->execute();
                }
            }
            $stmt_perm->close();
        }

        $conn->commit();
        log_activity('USER_UPDATED', "Updated profile for user: " . htmlspecialchars($full_name), $conn);
        
        // This redirect will now work correctly
        header("Location: edit-user.php?id=$user_id&success=1");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        // Redirect back with an error message
        header("Location: edit-user.php?id=$user_id&error=" . urlencode($e->getMessage()));
        exit();
    }
}

// --- The HTML header is now included AFTER all the processing logic is done. ---
require_once __DIR__ . '/../templates/header.php';
$page_title = "Edit Employee Details";

// --- DATA FETCHING for form display ---
$stmt = $conn->prepare("
    SELECT u.*, ed.* FROM scs_users u
    LEFT JOIN scs_employee_details ed ON u.id = ed.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found.");
}

$roles_result = $conn->query("SELECT id, role_name FROM scs_roles ORDER BY role_name ASC");
$locations_result = $conn->query("SELECT id, location_name FROM scs_locations ORDER BY location_name ASC");

// FIX for the SQL error
$managers_stmt = $conn->prepare("SELECT id, full_name FROM scs_users WHERE is_active = 1 AND id != ? ORDER BY full_name ASC");
$managers_stmt->bind_param("i", $user_id);
$managers_stmt->execute();
$managers_result = $managers_stmt->get_result();

$modules_result = $conn->query("SELECT * FROM scs_modules ORDER BY module_name ASC");

$custom_perms_stmt = $conn->prepare("SELECT * FROM scs_user_permissions WHERE user_id = ?");
$custom_perms_stmt->bind_param("i", $user_id);
$custom_perms_stmt->execute();
$custom_perms_result = $custom_perms_stmt->get_result();
$existing_permissions = [];
while ($row = $custom_perms_result->fetch_assoc()) {
    $existing_permissions[$row['module_id']] = $row;
}
$custom_perms_stmt->close();

if(isset($_GET['success'])){
    $message = "User details saved successfully!";
    $message_type = 'success';
     if(isset($_GET['new'])){
        $message = "User account created! You can now fill in the employee's details below.";
    }
}
if(isset($_GET['error'])){
    $message = "An error occurred: " . htmlspecialchars($_GET['error']);
    $message_type = 'error';
}
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Edit Details for: <?php echo htmlspecialchars($user['full_name']); ?></h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to User List
    </a>
</div>

<div class="glass-card p-8">
    
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form action="edit-user.php?id=<?php echo $user_id; ?>" method="POST" enctype="multipart/form-data" class="space-y-8">
        
        <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">1. Personal Information</legend>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div><label class="block text-sm font-medium text-gray-700">Full Name *</label><input type="text" name="full_name" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['full_name']); ?>" required></div>
                <div><label class="block text-sm font-medium text-gray-700">Father’s Name</label><input type="text" name="father_name" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['father_name'] ?? ''); ?>"></div>
                <div><label class="block text-sm font-medium text-gray-700">Mother’s Name</label><input type="text" name="mother_name" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['mother_name'] ?? ''); ?>"></div>
                <div><label class="block text-sm font-medium text-gray-700">Date of Birth</label><input type="date" name="date_of_birth" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>"></div>
                <div><label class="block text-sm font-medium text-gray-700">Gender</label><select name="gender" class="form-input mt-1 block w-full p-2"><option value="Male" <?php if(isset($user['gender']) && $user['gender'] == 'Male') echo 'selected'; ?>>Male</option><option value="Female" <?php if(isset($user['gender']) && $user['gender'] == 'Female') echo 'selected'; ?>>Female</option><option value="Other" <?php if(isset($user['gender']) && $user['gender'] == 'Other') echo 'selected'; ?>>Other</option></select></div>
                <div><label class="block text-sm font-medium text-gray-700">Marital Status</label><select name="marital_status" class="form-input mt-1 block w-full p-2"><option value="Single" <?php if(isset($user['marital_status']) && $user['marital_status'] == 'Single') echo 'selected'; ?>>Single</option><option value="Married" <?php if(isset($user['marital_status']) && $user['marital_status'] == 'Married') echo 'selected'; ?>>Married</option><option value="Divorced" <?php if(isset($user['marital_status']) && $user['marital_status'] == 'Divorced') echo 'selected'; ?>>Divorced</option><option value="Widowed" <?php if(isset($user['marital_status']) && $user['marital_status'] == 'Widowed') echo 'selected'; ?>>Widowed</option></select></div>
                <div><label class="block text-sm font-medium text-gray-700">National ID / Passport</label><input type="text" name="national_id" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['national_id'] ?? ''); ?>"></div>
                <div><label class="block text-sm font-medium text-gray-700">Blood Group</label><input type="text" name="blood_group" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['blood_group'] ?? ''); ?>"></div>
                <div><label class="block text-sm font-medium text-gray-700">Religion</label><input type="text" name="religion" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['religion'] ?? ''); ?>"></div>
                <div><label class="block text-sm font-medium text-gray-700">Nationality</label><input type="text" name="nationality" value="<?php echo htmlspecialchars($user['nationality'] ?? 'Bangladeshi'); ?>" class="form-input mt-1 block w-full p-2"></div>
            </div>
        </fieldset>

        <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">2. Contact Details</legend>
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label class="block text-sm font-medium text-gray-700">Present Address</label><textarea name="address" rows="3" class="form-input mt-1 block w-full p-2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea></div>
                <div><label class="block text-sm font-medium text-gray-700">Permanent Address</label><textarea name="permanent_address" rows="3" class="form-input mt-1 block w-full p-2"><?php echo htmlspecialchars($user['permanent_address'] ?? ''); ?></textarea></div>
                <div><label class="block text-sm font-medium text-gray-700">Mobile Number</label><input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="form-input mt-1 block w-full p-2"></div>
                <div><label class="block text-sm font-medium text-gray-700">Emergency Contact Name</label><input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($user['emergency_contact_name'] ?? ''); ?>" class="form-input mt-1 block w-full p-2"></div>
                <div><label class="block text-sm font-medium text-gray-700">Emergency Contact Phone</label><input type="text" name="emergency_contact_phone" value="<?php echo htmlspecialchars($user['emergency_contact_phone'] ?? ''); ?>" class="form-input mt-1 block w-full p-2"></div>
             </div>
        </fieldset>
        
        <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">3. Account & Login</legend>
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label class="block text-sm font-medium text-gray-700">Email Address *</label><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="form-input mt-1 block w-full p-2" required></div>
                <div><label class="block text-sm font-medium text-gray-700">New Password (leave blank to keep current)</label><input type="password" name="password" id="password" class="form-input mt-1 block w-full p-2"></div>
                <div><label class="block text-sm font-medium text-gray-700">Primary Role *</label><select name="role" class="form-input mt-1 block w-full p-2" required><?php mysqli_data_seek($roles_result, 0); while($role = $roles_result->fetch_assoc()){ echo "<option value='{$role['id']}' ".($user['role_id']==$role['id']?'selected':'').">{$role['role_name']}</option>"; } ?></select></div>
             </div>
        </fieldset>

        <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">4. Employment Details</legend>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div><label class="block text-sm font-medium text-gray-700">Designation</label><input type="text" name="job_title" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['job_title'] ?? ''); ?>"></div>
                <div><label class="block text-sm font-medium text-gray-700">Department</label><input type="text" name="department" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>"></div>
                <div><label class="block text-sm font-medium text-gray-700">Date of Joining</label><input type="date" name="hire_date" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['hire_date'] ?? ''); ?>"></div>
                <div><label class="block text-sm font-medium text-gray-700">Employment Type</label><select name="employment_type" class="form-input mt-1 block w-full p-2"><option value="Full-time" <?php if(isset($user['employment_type']) && $user['employment_type'] == 'Full-time') echo 'selected'; ?>>Full-time</option><option value="Part-time" <?php if(isset($user['employment_type']) && $user['employment_type'] == 'Part-time') echo 'selected'; ?>>Part-time</option><option value="Contract" <?php if(isset($user['employment_type']) && $user['employment_type'] == 'Contract') echo 'selected'; ?>>Contract</option><option value="Internship" <?php if(isset($user['employment_type']) && $user['employment_type'] == 'Internship') echo 'selected'; ?>>Internship</option></select></div>
                <div><label class="block text-sm font-medium text-gray-700">Probation (Months)</label><input type="number" name="probation_period_months" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['probation_period_months'] ?? ''); ?>"></div>
                <div><label class="block text-sm font-medium text-gray-700">Reporting Manager</label><select name="reporting_manager_id" class="form-input mt-1 block w-full p-2"><option value="">None</option><?php while($mgr = $managers_result->fetch_assoc()){ echo "<option value='{$mgr['id']}' ".(($user['reporting_manager_id'] ?? '')==$mgr['id']?'selected':'').">{$mgr['full_name']}</option>"; } ?></select></div>
                <div><label class="block text-sm font-medium text-gray-700">Work Location</label><select name="location" class="form-input mt-1 block w-full p-2"><option value="">Select a location</option><?php mysqli_data_seek($locations_result, 0); while($row = $locations_result->fetch_assoc()){ echo "<option value='{$row['id']}' ".($user['location_id']==$row['id']?'selected':'').">{$row['location_name']}</option>"; } ?></select></div>
                <div><label class="block text-sm font-medium text-gray-700">Data Access Scope</label><select name="data_scope" class="form-input mt-1 block w-full p-2"><option value="Local" <?php if($user['data_scope'] == 'Local') echo 'selected'; ?>>Local</option><option value="Global" <?php if($user['data_scope'] == 'Global') echo 'selected'; ?>>Global</option></select></div>
            </div>
        </fieldset>
        
        <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">5. Salary & Benefits</legend>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                 <div><label for="basic_salary" class="block text-sm font-medium text-gray-700">Basic Salary</label><input type="number" step="0.01" name="basic_salary" id="basic_salary" value="<?php echo htmlspecialchars($user['basic_salary'] ?? '0'); ?>" class="form-input salary-component mt-1 block w-full p-2"></div>
                 <div><label for="house_rent_allowance" class="block text-sm font-medium text-gray-700">House Rent Allowance</label><input type="number" step="0.01" name="house_rent_allowance" id="house_rent_allowance" value="<?php echo htmlspecialchars($user['house_rent_allowance'] ?? '0'); ?>" class="form-input salary-component mt-1 block w-full p-2"></div>
                 <div><label for="medical_allowance" class="block text-sm font-medium text-gray-700">Medical Allowance</label><input type="number" step="0.01" name="medical_allowance" id="medical_allowance" value="<?php echo htmlspecialchars($user['medical_allowance'] ?? '0'); ?>" class="form-input salary-component mt-1 block w-full p-2"></div>
                 <div><label for="transport_allowance" class="block text-sm font-medium text-gray-700">Transport Allowance</label><input type="number" step="0.01" name="transport_allowance" id="transport_allowance" value="<?php echo htmlspecialchars($user['transport_allowance'] ?? '0'); ?>" class="form-input salary-component mt-1 block w-full p-2"></div>
                 <div><label for="other_allowances" class="block text-sm font-medium text-gray-700">Other Allowances</label><input type="number" step="0.01" name="other_allowances" id="other_allowances" value="<?php echo htmlspecialchars($user['other_allowances'] ?? '0'); ?>" class="form-input salary-component mt-1 block w-full p-2"></div>
                 <div class="bg-indigo-50 p-4 rounded-lg flex flex-col justify-center">
                    <label class="block text-sm font-medium text-gray-700">Gross Salary (Monthly)</label>
                    <p class="text-2xl font-bold text-indigo-600 mt-1"><?php echo $app_config['currency_symbol']; ?> <span id="gross_salary_display">0.00</span></p>
                 </div>
            </div>
             <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                 <div><label for="bank_name" class="block text-sm font-medium text-gray-700">Bank Name</label><input type="text" name="bank_name" id="bank_name" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['bank_name'] ?? ''); ?>"></div>
                 <div><label for="bank_branch" class="block text-sm font-medium text-gray-700">Bank Branch</label><input type="text" name="bank_branch" id="bank_branch" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['bank_branch'] ?? ''); ?>"></div>
                 <div><label for="bank_account_number" class="block text-sm font-medium text-gray-700">Bank Account Number</label><input type="text" name="bank_account_number" id="bank_account_number" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['bank_account_number'] ?? ''); ?>"></div>
                 <div><label for="tin_number" class="block text-sm font-medium text-gray-700">Tax ID (TIN)</label><input type="text" name="tin_number" id="tin_number" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['tin_number'] ?? ''); ?>"></div>
            </div>
        </fieldset>
        
        <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">7. Custom Module Permissions</legend>
            <p class="text-sm text-gray-500 mb-4">Override the user's default role permissions. Ticking any box here will create a specific rule for this user.</p>
            <div class="space-y-4">
                <?php mysqli_data_seek($modules_result, 0); while($module = $modules_result->fetch_assoc()): ?>
                    <div>
                        <h4 class="font-medium text-gray-700"><?php echo htmlspecialchars($module['module_name']); ?></h4>
                        <div class="flex space-x-6 mt-2">
                            <?php 
                                $current_perm = $existing_permissions[$module['id']] ?? [];
                                $actions = ['view', 'create', 'edit', 'delete'];
                                foreach($actions as $action):
                                    $checked = !empty($current_perm['can_'.$action]) ? 'checked' : '';
                            ?>
                            <label class="flex items-center"><input type="checkbox" name="permissions[<?php echo $module['id']; ?>][<?php echo $action; ?>]" class="rounded h-4 w-4 text-indigo-600" <?php echo $checked; ?>><span class="ml-2 text-sm"><?php echo ucfirst($action); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </fieldset>

        <div class="flex justify-end pt-8 border-t border-gray-200/50">
            <button type="submit" class="ml-3 inline-flex justify-center py-3 px-6 rounded-md text-white bg-indigo-600 hover:bg-indigo-700 font-semibold text-lg">
                Save All Details
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

    calculateGrossSalary();
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>