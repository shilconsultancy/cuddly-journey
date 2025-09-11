<?php
// users/edit-user.php

require_once __DIR__ . '/../templates/header.php';

$page_title = "Edit User - BizManager";

// --- SECURITY CHECK ---
if (!check_permission('Users', 'edit')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    header("Location: index.php");
    exit();
}

// --- DATA FETCHING for the form ---
$stmt = $conn->prepare("
    SELECT u.*, ed.* FROM scs_users u
    LEFT JOIN scs_employee_details ed ON u.id = ed.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found.");
}

// Fetch dropdown data
$roles_result = $conn->query("SELECT id, role_name FROM scs_roles ORDER BY role_name ASC");
$locations_result = $conn->query("SELECT id, location_name FROM scs_locations ORDER BY location_name ASC");
$managers_result = $conn->query("SELECT id, full_name FROM scs_users WHERE is_active = 1 ORDER BY full_name ASC");
$modules_result = $conn->query("SELECT * FROM scs_modules ORDER BY module_name ASC");

// Fetch existing custom permissions
$custom_perms_stmt = $conn->prepare("SELECT * FROM scs_user_permissions WHERE user_id = ?");
$custom_perms_stmt->bind_param("i", $user_id);
$custom_perms_stmt->execute();
$custom_perms_result = $custom_perms_stmt->get_result();
$existing_permissions = [];
while ($row = $custom_perms_result->fetch_assoc()) {
    $existing_permissions[$row['module_id']] = $row;
}
$custom_perms_stmt->close();


// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- Retrieve and sanitize all form data ---
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role_id = $_POST['role'];
    $location_id = !empty($_POST['location']) ? $_POST['location'] : NULL;
    $data_scope = $_POST['data_scope'];
    $password = $_POST['password']; 
    $permissions = $_POST['permissions'] ?? [];

    $father_name = trim($_POST['father_name']);
    $mother_name = trim($_POST['mother_name']);
    $date_of_birth = $_POST['date_of_birth'];
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
    $hire_date = $_POST['hire_date'];
    $employment_type = $_POST['employment_type'];
    $probation_period_months = $_POST['probation_period_months'];
    $reporting_manager_id = !empty($_POST['reporting_manager_id']) ? $_POST['reporting_manager_id'] : NULL;
    $salary = $_POST['salary'];
    $bank_name = trim($_POST['bank_name']);
    $bank_branch = trim($_POST['bank_branch']);
    $bank_account_number = trim($_POST['bank_account_number']);
    $tin_number = trim($_POST['tin_number']);

    $conn->begin_transaction();
    try {
        // --- Handle File Uploads ---
        $upload_error = false;
        function handle_upload($file_key, $prefix, $current_file_url) {
            global $message, $message_type, $upload_error;
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == UPLOAD_ERR_OK) {
                // Delete old file if it exists
                if (!empty($current_file_url) && file_exists(__DIR__ . '/../' . $current_file_url)) {
                    unlink(__DIR__ . '/../' . $current_file_url);
                }

                $target_dir = __DIR__ . "/../uploads/documents/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

                $file_ext = strtolower(pathinfo($_FILES[$file_key]["name"], PATHINFO_EXTENSION));
                $unique_filename = $prefix . uniqid() . '.' . $file_ext;
                $target_file = $target_dir . $unique_filename;
                
                $allowed_types = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
                if (in_array($file_ext, $allowed_types) && $_FILES[$file_key]["size"] <= 5000000) {
                    if (move_uploaded_file($_FILES[$file_key]["tmp_name"], $target_file)) {
                        return 'uploads/documents/' . $unique_filename;
                    }
                }
                $message = "Invalid file type or size for " . $file_key;
                $message_type = 'error';
                $upload_error = true;
                return $current_file_url; // Return old URL on failure
            }
            return $current_file_url; // Return old URL if no new file is uploaded
        }
        
        $profile_image_url = handle_upload('profile_image', 'profile_', $user['profile_image_url']);
        if (!$upload_error) $id_card_url = handle_upload('id_card_scan', 'id_', $user['id_card_url']);
        if (!$upload_error) $cv_url = handle_upload('cv_document', 'cv_', $user['cv_url']);
        if (!$upload_error) $edu_cert_url = handle_upload('edu_cert', 'edu_', $user['educational_certs_url']);
        if (!$upload_error) $exp_cert_url = handle_upload('exp_cert', 'exp_', $user['experience_certs_url']);
        
        if ($upload_error) {
            throw new Exception($message);
        }

        // 1. Update scs_users table
        $stmt_user = $conn->prepare("UPDATE scs_users SET full_name = ?, email = ?, role_id = ?, location_id = ?, data_scope = ?, profile_image_url = ? WHERE id = ?");
        $stmt_user->bind_param("ssisssi", $full_name, $email, $role_id, $location_id, $data_scope, $profile_image_url, $user_id);
        $stmt_user->execute();
        $stmt_user->close();
        
        // 2. Update scs_employee_details table
        $stmt_hr = $conn->prepare("
            UPDATE scs_employee_details SET 
            father_name=?, mother_name=?, date_of_birth=?, gender=?, marital_status=?, national_id=?, 
            blood_group=?, religion=?, nationality=?, address=?, permanent_address=?, phone=?, 
            emergency_contact_name=?, emergency_contact_phone=?, job_title=?, department=?, hire_date=?, 
            employment_type=?, probation_period_months=?, reporting_manager_id=?, salary=?, 
            bank_name=?, bank_branch=?, bank_account_number=?, tin_number=?, id_card_url=?, 
            cv_url=?, educational_certs_url=?, experience_certs_url=? 
            WHERE user_id = ?
        ");
        $stmt_hr->bind_param("ssssssssssssssssssisdssssssssi", 
            $father_name, $mother_name, $date_of_birth, $gender, $marital_status, $national_id, 
            $blood_group, $religion, $nationality, $address, $permanent_address, $phone, 
            $emergency_contact_name, $emergency_contact_phone, $job_title, $department, $hire_date,
            $employment_type, $probation_period_months, $reporting_manager_id, $salary,
            $bank_name, $bank_branch, $bank_account_number, $tin_number, $id_card_url,
            $cv_url, $edu_cert_url, $exp_cert_url, $user_id
        );
        $stmt_hr->execute();
        $stmt_hr->close();

        // 3. Update password if provided
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_pass = $conn->prepare("UPDATE scs_users SET password = ? WHERE id = ?");
            $stmt_pass->bind_param("si", $hashed_password, $user_id);
            $stmt_pass->execute();
            $stmt_pass->close();
        }
        
        // 4. Update custom permissions (delete old, insert new)
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
        header("Location: edit-user.php?id=$user_id&success=1");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error updating user: " . $e->getMessage();
        $message_type = 'error';
    }
}


if(isset($_GET['success'])){
    $message = "User updated successfully!";
    $message_type = 'success';
     // Re-fetch data to show the latest changes
    $stmt = $conn->prepare("SELECT u.*, ed.* FROM scs_users u LEFT JOIN scs_employee_details ed ON u.id = ed.user_id WHERE u.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Edit Employee: <?php echo htmlspecialchars($user['full_name']); ?></h2>
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

    <form action="edit-user.php?id=<?php echo $user_id; ?>" method="POST" enctype="multipart/form-data" class="space-y-8">
        
        <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">1. Personal Information</legend>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div><label class="block text-sm font-medium text-gray-700">Full Name *</label><input type="text" name="full_name" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['full_name']); ?>" required></div>
                <div><label class="block text-sm font-medium text-gray-700">Father’s Name *</label><input type="text" name="father_name" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['father_name'] ?? ''); ?>" required></div>
                <div><label class="block text-sm font-medium text-gray-700">Mother’s Name *</label><input type="text" name="mother_name" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['mother_name'] ?? ''); ?>" required></div>
                <div><label class="block text-sm font-medium text-gray-700">Date of Birth *</label><input type="date" name="date_of_birth" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>" required></div>
                <div><label class="block text-sm font-medium text-gray-700">Gender *</label><select name="gender" class="form-input mt-1 block w-full p-2" required><option value="Male" <?php if($user['gender'] == 'Male') echo 'selected'; ?>>Male</option><option value="Female" <?php if($user['gender'] == 'Female') echo 'selected'; ?>>Female</option><option value="Other" <?php if($user['gender'] == 'Other') echo 'selected'; ?>>Other</option></select></div>
                <div><label class="block text-sm font-medium text-gray-700">Marital Status *</label><select name="marital_status" class="form-input mt-1 block w-full p-2" required><option value="Single" <?php if($user['marital_status'] == 'Single') echo 'selected'; ?>>Single</option><option value="Married" <?php if($user['marital_status'] == 'Married') echo 'selected'; ?>>Married</option><option value="Divorced" <?php if($user['marital_status'] == 'Divorced') echo 'selected'; ?>>Divorced</option><option value="Widowed" <?php if($user['marital_status'] == 'Widowed') echo 'selected'; ?>>Widowed</option></select></div>
                <div><label class="block text-sm font-medium text-gray-700">National ID / Passport *</label><input type="text" name="national_id" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['national_id'] ?? ''); ?>" required></div>
                <div><label class="block text-sm font-medium text-gray-700">Blood Group</label><input type="text" name="blood_group" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['blood_group'] ?? ''); ?>"></div>
                <div><label class="block text-sm font-medium text-gray-700">Religion</label><input type="text" name="religion" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['religion'] ?? ''); ?>"></div>
                <div><label class="block text-sm font-medium text-gray-700">Nationality *</label><input type="text" name="nationality" value="<?php echo htmlspecialchars($user['nationality'] ?? 'Bangladeshi'); ?>" class="form-input mt-1 block w-full p-2" required></div>
            </div>
        </fieldset>

        <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">2. Contact Details</legend>
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label class="block text-sm font-medium text-gray-700">Present Address *</label><textarea name="address" rows="3" class="form-input mt-1 block w-full p-2" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea></div>
                <div><label class="block text-sm font-medium text-gray-700">Permanent Address *</label><textarea name="permanent_address" rows="3" class="form-input mt-1 block w-full p-2" required><?php echo htmlspecialchars($user['permanent_address'] ?? ''); ?></textarea></div>
                <div><label class="block text-sm font-medium text-gray-700">Mobile Number *</label><input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="form-input mt-1 block w-full p-2" required></div>
                <div><label class="block text-sm font-medium text-gray-700">Emergency Contact Name *</label><input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($user['emergency_contact_name'] ?? ''); ?>" class="form-input mt-1 block w-full p-2" required></div>
                <div><label class="block text-sm font-medium text-gray-700">Emergency Contact Phone *</label><input type="text" name="emergency_contact_phone" value="<?php echo htmlspecialchars($user['emergency_contact_phone'] ?? ''); ?>" class="form-input mt-1 block w-full p-2" required></div>
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
                <div><label class="block text-sm font-medium text-gray-700">Designation *</label><input type="text" name="job_title" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['job_title'] ?? ''); ?>" required></div>
                <div><label class="block text-sm font-medium text-gray-700">Department *</label><input type="text" name="department" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>" required></div>
                <div><label class="block text-sm font-medium text-gray-700">Date of Joining *</label><input type="date" name="hire_date" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['hire_date'] ?? ''); ?>" required></div>
                <div><label class="block text-sm font-medium text-gray-700">Employment Type *</label><select name="employment_type" class="form-input mt-1 block w-full p-2" required><option value="Full-time" <?php if($user['employment_type'] == 'Full-time') echo 'selected'; ?>>Full-time</option><option value="Part-time" <?php if($user['employment_type'] == 'Part-time') echo 'selected'; ?>>Part-time</option><option value="Contract" <?php if($user['employment_type'] == 'Contract') echo 'selected'; ?>>Contract</option><option value="Internship" <?php if($user['employment_type'] == 'Internship') echo 'selected'; ?>>Internship</option></select></div>
                <div><label class="block text-sm font-medium text-gray-700">Probation (Months)</label><input type="number" name="probation_period_months" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['probation_period_months'] ?? ''); ?>"></div>
                <div><label class="block text-sm font-medium text-gray-700">Reporting Manager</label><select name="reporting_manager_id" class="form-input mt-1 block w-full p-2"><option value="">None</option><?php while($mgr = $managers_result->fetch_assoc()){ echo "<option value='{$mgr['id']}' ".($user['reporting_manager_id']==$mgr['id']?'selected':'').">{$mgr['full_name']}</option>"; } ?></select></div>
                <div><label class="block text-sm font-medium text-gray-700">Work Location *</label><select name="location" class="form-input mt-1 block w-full p-2" required><option value="">Select a location</option><?php mysqli_data_seek($locations_result, 0); while($row = $locations_result->fetch_assoc()){ echo "<option value='{$row['id']}' ".($user['location_id']==$row['id']?'selected':'').">{$row['location_name']}</option>"; } ?></select></div>
                <div><label class="block text-sm font-medium text-gray-700">Data Access Scope *</label><select name="data_scope" class="form-input mt-1 block w-full p-2" required><option value="Local" <?php if($user['data_scope'] == 'Local') echo 'selected'; ?>>Local</option><option value="Global" <?php if($user['data_scope'] == 'Global') echo 'selected'; ?>>Global</option></select></div>
            </div>
        </fieldset>

        <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">5. Salary & Benefits</legend>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                 <div><label class="block text-sm font-medium text-gray-700">Gross Salary (Monthly) *</label><input type="number" step="0.01" name="salary" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['salary'] ?? ''); ?>" required></div>
                 <div><label class="block text-sm font-medium text-gray-700">Bank Name</label><input type="text" name="bank_name" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['bank_name'] ?? ''); ?>"></div>
                 <div><label class="block text-sm font-medium text-gray-700">Bank Branch</label><input type="text" name="bank_branch" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['bank_branch'] ?? ''); ?>"></div>
                 <div><label class="block text-sm font-medium text-gray-700">Bank Account Number</label><input type="text" name="bank_account_number" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['bank_account_number'] ?? ''); ?>"></div>
                 <div><label class="block text-sm font-medium text-gray-700">Tax ID (TIN)</label><input type="text" name="tin_number" class="form-input mt-1 block w-full p-2" value="<?php echo htmlspecialchars($user['tin_number'] ?? ''); ?>"></div>
            </div>
        </fieldset>

        <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">6. Document Uploads</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <div><label class="block text-sm font-medium text-gray-700">Passport-size Photo (Upload to replace)</label><input type="file" name="profile_image" class="form-input mt-1 block w-full"></div>
                 <div><label class="block text-sm font-medium text-gray-700">NID/Passport Scan (Upload to replace)</label><input type="file" name="id_card_scan" class="form-input mt-1 block w-full"></div>
                 <div><label class="block text-sm font-medium text-gray-700">CV/Resume (Upload to replace)</label><input type="file" name="cv_document" class="form-input mt-1 block w-full"></div>
                 <div><label class="block text-sm font-medium text-gray-700">Educational Certificates (Upload to replace)</label><input type="file" name="edu_cert" class="form-input mt-1 block w-full"></div>
                 <div><label class="block text-sm font-medium text-gray-700">Experience Certificates (Upload to replace)</label><input type="file" name="exp_cert" class="form-input mt-1 block w-full"></div>
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
                Save Changes
            </button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>