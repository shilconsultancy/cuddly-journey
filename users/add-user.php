<?php
// users/add-user.php

// --- All PHP logic is now at the TOP of the file ---
// This ensures that if we need to redirect, no HTML has been sent to the browser yet.
require_once __DIR__ . '/../config.php';

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and retrieve form data
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role_id = $_POST['role'];
    
    // --- Validation ---
    if (empty($full_name) || empty($email) || empty($password) || empty($role_id)) {
        // In a real application, you'd use sessions to pass this error message back
        die("Error: Please fill in all required fields.");
    } 
    if ($password !== $confirm_password) {
        die("Error: Passwords do not match.");
    }

    // Check for duplicate email
    $stmt_check = $conn->prepare("SELECT id FROM scs_users WHERE email = ?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        // This is a simplified error handling. A better way is to redirect with a message.
        die("Error: An account with this email already exists.");
    } else {
        $conn->begin_transaction();
        try {
            // 1. Insert the core user account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_user = $conn->prepare("INSERT INTO scs_users (full_name, email, password, role_id) VALUES (?, ?, ?, ?)");
            $stmt_user->bind_param("sssi", $full_name, $email, $hashed_password, $role_id);
            $stmt_user->execute();
            $new_user_id = $conn->insert_id;

            // 2. Generate and Update Company ID
            $company_id = "SBM" . date('Y') . str_pad($new_user_id, 4, '0', STR_PAD_LEFT);
            $stmt_update_id = $conn->prepare("UPDATE scs_users SET company_id = ? WHERE id = ?");
            $stmt_update_id->bind_param("si", $company_id, $new_user_id);
            $stmt_update_id->execute();

            // 3. Create an empty placeholder in employee_details for future edits
            $stmt_hr = $conn->prepare("INSERT INTO scs_employee_details (user_id) VALUES (?)");
            $stmt_hr->bind_param("i", $new_user_id);
            $stmt_hr->execute();

            // 4. Log the activity
            log_activity('USER_CREATED', "Created a new user account: " . htmlspecialchars($full_name) . " (ID: " . $new_user_id . ")", $conn);

            $conn->commit();
            
            // This is the redirect that was causing the error. It will now work.
            header("Location: ../users/edit-user.php?id=$new_user_id&success=1&new=1");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            die("Error creating user: " . $e->getMessage());
        }
    }
    $stmt_check->close();
}

// --- PAGE DISPLAY LOGIC ---
// The header is now included AFTER all the processing logic is done.
require_once __DIR__ . '/../templates/header.php';

$page_title = "Add New User Account";
// Initialize variables for the form display
$full_name = '';
$email = '';
$selected_role = '';
// Fetch roles for the dropdown
$roles_result = $conn->query("SELECT id, role_name FROM scs_roles ORDER BY role_name ASC");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Create New User Account</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to User List
    </a>
</div>

<div class="glass-card p-8">
    
    <form action="add-user.php" method="POST" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name *</label>
                <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($full_name); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email Address *</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password *</label>
                <input type="password" name="password" id="password" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password *</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
        </div>
        
        <div>
            <label for="role" class="block text-sm font-medium text-gray-700">Primary Role *</label>
            <select id="role" name="role" class="form-input mt-1 block w-full pl-3 pr-10 py-3 rounded-md" required>
                <option value="">Select a role</option>
                <?php mysqli_data_seek($roles_result, 0); while($role = $roles_result->fetch_assoc()): ?>
                    <option value="<?php echo $role['id']; ?>" <?php if ($role['id'] == $selected_role) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($role['role_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="flex justify-end pt-6 border-t border-gray-200/50">
            <button type="submit" class="ml-3 inline-flex justify-center py-2 px-6 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                Create User & Add Details
            </button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>