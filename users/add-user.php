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
$full_name = ''; $email = ''; $selected_role = ''; $selected_location = '';
$selected_data_scope = 'Local'; // Default to Local for security
$job_title = ''; $department = ''; $hire_date = date('Y-m-d');
$salary = ''; $address = ''; $emergency_contact_name = ''; $emergency_contact_phone = '';

// Data fetching for dropdowns
$roles_result = $conn->query("SELECT id, role_name FROM scs_roles ORDER BY role_name ASC");
$locations_result = $conn->query("SELECT id, location_name FROM scs_locations ORDER BY location_name ASC");

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- Retrieve All Form Data ---
    // Account Details
    $full_name = trim($_POST['full-name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];
    $role_id = $_POST['role'];
    $location_id = !empty($_POST['location']) ? $_POST['location'] : NULL;
    $data_scope = $_POST['data_scope'];

    // HR Details
    $job_title = trim($_POST['job_title']);
    $department = trim($_POST['department']);
    $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : NULL;
    $salary = !empty($_POST['salary']) ? (float)$_POST['salary'] : NULL;
    $address = trim($_POST['address']);
    $emergency_contact_name = trim($_POST['emergency_contact_name']);
    $emergency_contact_phone = trim($_POST['emergency_contact_phone']);

    // Keep selected values on POST
    $selected_role = $role_id;
    $selected_location = $location_id;
    $selected_data_scope = $data_scope;

    // --- Validation ---
    if (empty($full_name) || empty($email) || empty($password) || empty($role_id)) {
        $message = "Please fill in all required account fields.";
        $message_type = 'error';
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
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
            // --- Database transaction starts here ---
            $conn->begin_transaction();
            try {
                // 1. Insert into scs_users table
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_user = $conn->prepare("INSERT INTO scs_users (full_name, email, password, role_id, location_id, data_scope) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_user->bind_param("sssis", $full_name, $email, $hashed_password, $role_id, $location_id, $data_scope);
                $stmt_user->execute();
                $new_user_id = $conn->insert_id;

                // 2. Insert into scs_employee_details table
                $stmt_hr = $conn->prepare("INSERT INTO scs_employee_details (user_id, job_title, department, hire_date, salary, address, emergency_contact_name, emergency_contact_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_hr->bind_param("isssdsss", $new_user_id, $job_title, $department, $hire_date, $salary, $address, $emergency_contact_name, $emergency_contact_phone);
                $stmt_hr->execute();

                // 3. Generate and Update Company ID
                $company_id = "SBM" . date('Y') . str_pad($new_user_id, 4, '0', STR_PAD_LEFT);
                $stmt_update_id = $conn->prepare("UPDATE scs_users SET company_id = ? WHERE id = ?");
                $stmt_update_id->bind_param("si", $company_id, $new_user_id);
                $stmt_update_id->execute();

                log_activity('USER_CREATED', "Created new employee: " . htmlspecialchars($full_name) . " (ID: " . $new_user_id . ")", $conn);
                
                $conn->commit();
                header("Location: ../hr/employees.php?success=created");
                exit();

            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error creating user: " . $e->getMessage();
                $message_type = 'error';
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
        <!-- Account Details Section -->
        <fieldset>
            <legend class="text-lg font-semibold text-gray-800">Account Details</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                <div>
                    <label for="full-name" class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" name="full-name" id="full-name" value="<?php echo htmlspecialchars($full_name); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" name="password" id="password" class="form-input mt-1 block w-full rounded-md p-3" required>
                </div>
                <div>
                    <label for="confirm-password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                    <input type="password" name="confirm-password" id="confirm-password" class="form-input mt-1 block w-full rounded-md p-3" required>
                </div>
            </div>
        </fieldset>

        <!-- Role & Location Section -->
        <fieldset>
            <legend class="text-lg font-semibold text-gray-800">Role & Location</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700">Primary Role</label>
                    <select id="role" name="role" class="form-input mt-1 block w-full p-3 rounded-md" required>
                        <option value="">Select a role</option>
                        <?php mysqli_data_seek($roles_result, 0); while($role = $roles_result->fetch_assoc()): ?>
                            <option value="<?php echo $role['id']; ?>" <?php if ($role['id'] == $selected_role) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($role['role_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label for="location" class="block text-sm font-medium text-gray-700">Assigned Location</label>
                    <select id="location" name="location" class="form-input mt-1 block w-full p-3 rounded-md">
                        <option value="">None</option>
                        <?php mysqli_data_seek($locations_result, 0); while($row = $locations_result->fetch_assoc()): ?>
                            <option value="<?php echo $row['id']; ?>" <?php if ($row['id'] == $selected_location) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($row['location_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                 <div>
                    <label for="data_scope" class="block text-sm font-medium text-gray-700">Data Access Scope</label>
                    <select id="data_scope" name="data_scope" class="form-input mt-1 block w-full p-3 rounded-md" required>
                        <option value="Local" <?php if ($selected_data_scope == 'Local') echo 'selected'; ?>>Local</option>
                        <option value="Global" <?php if ($selected_data_scope == 'Global') echo 'selected'; ?>>Global</option>
                    </select>
                </div>
            </div>
        </fieldset>
        
        <!-- HR Details Section -->
        <fieldset>
            <legend class="text-lg font-semibold text-gray-800">HR & Employment Details</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                <div>
                    <label for="job_title" class="block text-sm font-medium text-gray-700">Job Title</label>
                    <input type="text" name="job_title" id="job_title" value="<?php echo htmlspecialchars($job_title); ?>" class="form-input mt-1 block w-full p-2">
                </div>
                <div>
                    <label for="department" class="block text-sm font-medium text-gray-700">Department</label>
                    <input type="text" name="department" id="department" value="<?php echo htmlspecialchars($department); ?>" class="form-input mt-1 block w-full p-2">
                </div>
                <div>
                    <label for="hire_date" class="block text-sm font-medium text-gray-700">Hire Date</label>
                    <input type="date" name="hire_date" id="hire_date" value="<?php echo htmlspecialchars($hire_date); ?>" class="form-input mt-1 block w-full p-2">
                </div>
                <div>
                    <label for="salary" class="block text-sm font-medium text-gray-700">Salary (<?php echo $app_config['currency_symbol']; ?> per month)</label>
                    <input type="number" step="0.01" name="salary" id="salary" value="<?php echo htmlspecialchars($salary); ?>" class="form-input mt-1 block w-full p-2">
                </div>
                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700">Employee Address</label>
                    <textarea name="address" id="address" rows="3" class="form-input mt-1 block w-full p-2"><?php echo htmlspecialchars($address); ?></textarea>
                </div>
                 <div>
                    <label for="emergency_contact_name" class="block text-sm font-medium text-gray-700">Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" id="emergency_contact_name" value="<?php echo htmlspecialchars($emergency_contact_name); ?>" class="form-input mt-1 block w-full p-2">
                </div>
                <div>
                    <label for="emergency_contact_phone" class="block text-sm font-medium text-gray-700">Emergency Contact Phone</label>
                    <input type="text" name="emergency_contact_phone" id="emergency_contact_phone" value="<?php echo htmlspecialchars($emergency_contact_phone); ?>" class="form-input mt-1 block w-full p-2">
                </div>
            </div>
        </fieldset>

        <div class="flex justify-end pt-4 border-t border-gray-200/50">
            <button type="submit" class="ml-3 inline-flex justify-center py-2 px-6 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                Create Employee
            </button>
        </div>
    </form>
</div>