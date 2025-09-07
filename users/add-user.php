<?php
// users/add-user.php

// Go up one level to include the global header.
require_once __DIR__ . '/../templates/header.php';

$page_title = "Add User - BizManager";

// --- SECURITY CHECK ---
if (!check_permission('Users', 'create')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to create new users.</div>');
}


// Initialize variables
$message = '';
$message_type = '';
$full_name = '';
$email = '';
$selected_role = '';
$selected_location = '';
$selected_data_scope = 'Local'; // Default to Local for security

// --- DATA FETCHING for the page ---
// Fetch all roles for the dropdown
$roles_result = $conn->query("SELECT id, role_name FROM scs_roles ORDER BY role_name ASC");

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize form data
    $full_name = trim($_POST['full-name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];
    $role_id = $_POST['role'];
    $location_id = !empty($_POST['location']) ? $_POST['location'] : NULL;
    $data_scope = $_POST['data_scope']; // New data scope field

    // Keep selected values on POST
    $selected_role = $role_id;
    $selected_location = $location_id;
    $selected_data_scope = $data_scope;

    // --- Validation ---
    if (empty($full_name) || empty($email) || empty($password) || empty($role_id)) {
        $message = "Please fill in all required fields.";
        $message_type = 'error';
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = 'error';
    } else {
        // --- FIX: DUPLICATE EMAIL CHECK ---
        $stmt_check = $conn->prepare("SELECT id FROM scs_users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $message = "An account with this email already exists.";
            $message_type = 'error';
        } else {
            // --- Handle Image Upload ---
            $profile_image_url = NULL;
            $target_dir = __DIR__ . "/../uploads/";

            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] != UPLOAD_ERR_NO_FILE) {
                if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
                    $message = "Upload Error Code: " . $_FILES['profile_image']['error'];
                    $message_type = 'error';
                }
                elseif (!is_dir($target_dir) || !is_writable($target_dir)) {
                    $message = "Upload directory does not exist or is not writable. Please check permissions.";
                    $message_type = 'error';
                }
                else {
                    $image_file_type = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
                    $unique_filename = uniqid('user_', true) . '.' . $image_file_type;
                    $target_file = $target_dir . $unique_filename;
                    
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                    if (in_array($image_file_type, $allowed_types) && $_FILES["profile_image"]["size"] <= 5000000) { // 5MB limit
                        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                            $profile_image_url = 'uploads/' . $unique_filename;
                        } else {
                            $message = "Sorry, there was a server error moving the uploaded file.";
                            $message_type = 'error';
                        }
                    } else {
                        $message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed, and file size must be less than 5MB.";
                        $message_type = 'error';
                    }
                }
            }

            // Proceed only if there was no upload error
            if ($message_type !== 'error') {
                // --- Use a transaction for data integrity ---
                $conn->begin_transaction();
                try {
                    // 1. Insert the user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_user = $conn->prepare("INSERT INTO scs_users (full_name, email, password, role_id, location_id, data_scope, profile_image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt_user->bind_param("sssisss", $full_name, $email, $hashed_password, $role_id, $location_id, $data_scope, $profile_image_url);
                    $stmt_user->execute();

                    $new_user_id = $conn->insert_id;

                    // 2. Generate and Update Professional Company ID
                    $current_year = date('Y');
                    $stmt_count = $conn->prepare("SELECT COUNT(id) as year_count FROM scs_users WHERE YEAR(created_at) = ?");
                    $stmt_count->bind_param("s", $current_year);
                    $stmt_count->execute();
                    $result_count = $stmt_count->get_result();
                    $user_count_this_year = $result_count->fetch_assoc()['year_count'];
                    
                    $sequence_number = str_pad($user_count_this_year, 4, '0', STR_PAD_LEFT);
                    
                    $company_id = "SBM" . $current_year . $sequence_number;
                    
                    $stmt_update_id = $conn->prepare("UPDATE scs_users SET company_id = ? WHERE id = ?");
                    $stmt_update_id->bind_param("si", $company_id, $new_user_id);
                    $stmt_update_id->execute();
                    $stmt_count->close();
                    $stmt_update_id->close();
                    
                    // 3. Log this activity
                    $log_description = "Created a new user: " . htmlspecialchars($full_name) . " (ID: " . $new_user_id . ", Company ID: " . $company_id . ")";
                    log_activity('USER_CREATED', $log_description, $conn);

                    // 4. Send Welcome Email
                    $email_subject = "Welcome to " . $app_config['company_name'];
                    $email_body = "
                        <h1>Welcome Aboard!</h1>
                        <p>Hello " . htmlspecialchars($full_name) . ",</p>
                        <p>An account has been created for you in our system. Here are your login details:</p>
                        <ul>
                            <li><strong>Email:</strong> " . htmlspecialchars($email) . "</li>
                            <li><strong>Password:</strong> " . htmlspecialchars($password) . "</li>
                            <li><strong>Company ID:</strong> " . htmlspecialchars($company_id) . "</li>
                        </ul>
                        <p>You can log in at: <a href='" . htmlspecialchars($app_config['company_website']) . "'>" . htmlspecialchars($app_config['company_website']) . "</a></p>
                        <p>Thank you,<br>The " . htmlspecialchars($app_config['company_name']) . " Team</p>
                    ";
                    
                    send_email($email, $email_subject, $email_body, $app_config);

                    $conn->commit();
                    $message = "User created successfully! New Company ID: " . $company_id;
                    $message_type = 'success';
                    
                    $full_name = $email = $selected_role = $selected_location = '';
                    $selected_data_scope = 'Local';


                } catch (mysqli_sql_exception $exception) {
                    $conn->rollback();
                    $message = "Error creating user: " . $exception->getMessage();
                    $message_type = 'error';
                }
                $stmt_user->close();
            }
        }
        $stmt_check->close();
    }
}
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Create New User</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to User List
    </a>
</div>

<div class="glass-card p-8">
    
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="add-user.php" method="POST" enctype="multipart/form-data" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="full-name" class="block text-sm font-medium text-gray-700">Full Name</label>
                <input type="text" name="full-name" id="full-name" value="<?php echo htmlspecialchars($full_name); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" id="password" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
            <div>
                <label for="confirm-password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                <input type="password" name="confirm-password" id="confirm-password" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
        </div>
        <label class="flex items-center">
            <input type="checkbox" onchange="togglePasswordVisibility(this)" class="rounded h-4 w-4 text-indigo-600">
            <span class="ml-2 text-sm text-gray-600">Show Password</span>
        </label>

        <div>
            <label for="profile_image" class="block text-sm font-medium text-gray-700">Profile Image</label>
            <input type="file" name="profile_image" id="profile_image" class="form-input mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
        </div>

        <div class="border-t border-gray-200/50 pt-6"></div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="role" class="block text-sm font-medium text-gray-700">Primary Role</label>
                <select id="role" name="role" class="form-input mt-1 block w-full pl-3 pr-10 py-3 rounded-md" required>
                    <option value="">Select a role</option>
                    <?php while($role = $roles_result->fetch_assoc()): ?>
                        <option value="<?php echo $role['id']; ?>" <?php if ($role['id'] == $selected_role) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($role['role_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label for="location" class="block text-sm font-medium text-gray-700">Assigned Location (Optional)</label>
                <select id="location" name="location" class="form-input mt-1 block w-full pl-3 pr-10 py-3 rounded-md">
                    <option value="">Select a location</option>
                    <?php 
                    $locations_result = $conn->query("SELECT id, location_name FROM scs_locations ORDER BY location_name ASC");
                    while($row = $locations_result->fetch_assoc()): ?>
                        <option value="<?php echo $row['id']; ?>" <?php if ($row['id'] == $selected_location) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($row['location_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        
        <div>
            <label for="data_scope" class="block text-sm font-medium text-gray-700">Data Access Scope</label>
            <select id="data_scope" name="data_scope" class="form-input mt-1 block w-full pl-3 pr-10 py-3 rounded-md" required>
                <option value="Local" <?php if ($selected_data_scope == 'Local') echo 'selected'; ?>>Local (Can only see data from their assigned location)</option>
                <option value="Global" <?php if ($selected_data_scope == 'Global') echo 'selected'; ?>>Global (Can see data from all locations)</option>
            </select>
        </div>


        <div class="flex justify-end pt-6 border-t border-gray-200/50">
            <button type="button" onclick="window.location.href='index.php'" class="bg-white/80 py-2 px-4 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50/50">
                Cancel
            </button>
            <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                Create User
            </button>
        </div>
    </form>
</div>

<script>
    function togglePasswordVisibility(checkbox) {
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm-password');
        passwordInput.type = checkbox.checked ? 'text' : 'password';
        confirmPasswordInput.type = checkbox.checked ? 'text' : 'password';
    }
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>