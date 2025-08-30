<?php
// users/edit-user.php

require_once __DIR__ . '/../templates/header.php';

$page_title = "Edit User - BizManager";

// --- SECURITY CHECK ---
if (!check_permission('Users', 'edit')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

// Initialize variables
$message = '';
$message_type = '';
$user_id = $_GET['id'] ?? null;

if (!$user_id) {
    header("Location: index.php");
    exit();
}

// --- DATA FETCHING for the form ---
$stmt = $conn->prepare("SELECT * FROM scs_users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found.");
}

// Fetch all roles, locations, etc. for dropdowns
$roles_result = $conn->query("SELECT id, role_name FROM scs_roles ORDER BY role_name ASC");

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize form data
    $full_name = trim($_POST['full-name']);
    $email = trim($_POST['email']);
    $role_id = $_POST['role'];
    $location_id = !empty($_POST['location']) ? $_POST['location'] : NULL;
    $data_scope = $_POST['data_scope'];
    $password = $_POST['password']; // Optional password change
    $profile_image_url = $user['profile_image_url']; // Keep old image by default

    // --- Handle Image Upload ---
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $target_dir = __DIR__ . "/../uploads/";
        $image_file_type = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
        $unique_filename = uniqid('user_', true) . '.' . $image_file_type;
        $target_file = $target_dir . $unique_filename;
        
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($image_file_type, $allowed_types) && $_FILES["profile_image"]["size"] <= 5000000) {
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                // If upload is successful, delete the old image if it exists
                if (!empty($profile_image_url) && file_exists(__DIR__ . '/../' . $profile_image_url)) {
                    unlink(__DIR__ . '/../' . $profile_image_url);
                }
                $profile_image_url = 'uploads/' . $unique_filename; // Set the new image URL
            }
        } else {
            $message = "Invalid file type or size.";
            $message_type = 'error';
        }
    }
    
    if ($message_type !== 'error') {
        // --- UPDATE user details ---
        $stmt_update = $conn->prepare("UPDATE scs_users SET full_name = ?, email = ?, role_id = ?, location_id = ?, data_scope = ?, profile_image_url = ? WHERE id = ?");
        $stmt_update->bind_param("ssisssi", $full_name, $email, $role_id, $location_id, $data_scope, $profile_image_url, $user_id);


        if ($stmt_update->execute()) {
            $message = "User updated successfully!";
            $message_type = 'success';
            
            // --- Handle optional password change ---
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_pass = $conn->prepare("UPDATE scs_users SET password = ? WHERE id = ?");
                $stmt_pass->bind_param("si", $hashed_password, $user_id);
                $stmt_pass->execute();
                $stmt_pass->close();
            }

            log_activity('USER_UPDATED', "Updated details for user: " . htmlspecialchars($full_name), $conn);

            // Re-fetch user data to display updated values
            $stmt = $conn->prepare("SELECT * FROM scs_users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
        } else {
            $message = "Error updating user: " . $stmt_update->error;
            $message_type = 'error';
        }
        $stmt_update->close();
    }
}
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Edit User: <?php echo htmlspecialchars($user['full_name']); ?></h2>
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

    <form action="edit-user.php?id=<?php echo $user_id; ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="full-name" class="block text-sm font-medium text-gray-700">Full Name</label>
                <input type="text" name="full-name" id="full-name" value="<?php echo htmlspecialchars($user['full_name']); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">New Password (leave blank to keep current password)</label>
            <input type="password" name="password" id="password" class="form-input mt-1 block w-full rounded-md p-3">
        </div>

        <div>
            <label for="profile_image" class="block text-sm font-medium text-gray-700">Profile Image</label>
            <div class="mt-1 flex items-center">
                <img class="h-16 w-16 rounded-full object-cover mr-4" 
                     src="<?php echo htmlspecialchars(!empty($user['profile_image_url']) ? '../' . $user['profile_image_url'] : 'https://placehold.co/100x100/6366f1/white?text=' . strtoupper(substr($user['full_name'], 0, 1))); ?>" 
                     alt="Current profile picture">
                <input type="file" name="profile_image" id="profile_image" class="form-input block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            </div>
        </div>

        <div class="border-t border-gray-200/50 pt-6"></div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="role" class="block text-sm font-medium text-gray-700">Primary Role</label>
                <select id="role" name="role" class="form-input mt-1 block w-full pl-3 pr-10 py-3 rounded-md" required>
                    <?php while($role = $roles_result->fetch_assoc()): ?>
                        <option value="<?php echo $role['id']; ?>" <?php if ($role['id'] == $user['role_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($role['role_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label for="location" class="block text-sm font-medium text-gray-700">Assigned Location</label>
                <select id="location" name="location" class="form-input mt-1 block w-full pl-3 pr-10 py-3 rounded-md">
                    <option value="">None</option>
                    <?php 
                    $locations_result = $conn->query("SELECT id, location_name FROM scs_locations ORDER BY location_name ASC");
                    while($row = $locations_result->fetch_assoc()): ?>
                        <option value="<?php echo $row['id']; ?>" <?php if ($row['id'] == $user['location_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($row['location_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        
        <div>
            <label for="data_scope" class="block text-sm font-medium text-gray-700">Data Access Scope</label>
            <select id="data_scope" name="data_scope" class="form-input mt-1 block w-full pl-3 pr-10 py-3 rounded-md" required>
                <option value="Local" <?php if ($user['data_scope'] == 'Local') echo 'selected'; ?>>Local (Can only see data from their assigned location)</option>
                <option value="Global" <?php if ($user['data_scope'] == 'Global') echo 'selected'; ?>>Global (Can see data from all locations)</option>
            </select>
        </div>

        <div class="flex justify-end pt-6 border-t border-gray-200/50">
            <button type="button" onclick="window.location.href='index.php'" class="bg-white/80 py-2 px-4 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50/50">
                Cancel
            </button>
            <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                Save Changes
            </button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>