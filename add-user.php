<?php
// add-user.php

// --- DEVELOPMENT ONLY: Display all errors ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Use require_once for the database connection.
require_once 'db_connect.php';

// Initialize variables
$message = '';
$message_type = '';
$full_name = '';
$email = '';
$selected_role = '';
$selected_location = '';

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Retrieve and sanitize form data
    $full_name = trim($_POST['full-name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];
    $role_id = $_POST['role'];
    $location_id = !empty($_POST['location']) ? $_POST['location'] : NULL;
    
    // Keep selected values on POST
    $selected_role = $role_id;
    $selected_location = $location_id;

    // --- Basic Server-Side Validation ---
    if (empty($full_name) || empty($email) || empty($password) || empty($role_id)) {
        $message = "Please fill in all required fields.";
        $message_type = 'error';
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $message_type = 'error';
    } else {
        // --- Check if email already exists ---
        $stmt_check = $conn->prepare("SELECT id FROM scs_users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $message = "An account with this email already exists.";
            $message_type = 'error';
        } else {
            // --- Hash the password for security ---
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // --- Prepare the SQL INSERT statement ---
            $stmt = $conn->prepare("INSERT INTO scs_users (full_name, email, password, role_id, location_id) VALUES (?, ?, ?, ?, ?)");
            
            // Bind the parameters to the statement
            $stmt->bind_param("sssis", $full_name, $email, $hashed_password, $role_id, $location_id);

            // --- Execute the statement and provide feedback ---
            if ($stmt->execute()) {
                $message = "User created successfully!";
                $message_type = 'success';
                // Clear form fields on success
                $full_name = '';
                $email = '';
                $selected_role = '';
                $selected_location = '';
            } else {
                $message = "Error creating user: " . $stmt->error;
                $message_type = 'error';
            }
            
            $stmt->close();
        }
        $stmt_check->close();
    }
}

// --- DATA FETCHING for Dropdowns ---
$roles_result = $conn->query("SELECT id, role_name FROM scs_roles ORDER BY role_name ASC");
$locations_result = $conn->query("SELECT id, location_name FROM scs_locations ORDER BY location_name ASC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - BizManager</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f4ff 0%, #e6f0ff 100%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }
        .glass-header {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(241, 245, 249, 0.5); }
        ::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.5); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(100, 116, 139, 0.5); }
        .form-input {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(2px);
        }
        .form-input:focus {
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.4);
        }
    </style>
</head>
<body class="min-h-screen">

    <div class="flex flex-col h-screen">
        <!-- Header -->
        <header class="glass-header flex justify-between items-center p-4 sticky top-0 z-40">
            <div class="flex items-center">
                <h1 class="text-2xl font-bold text-gray-800 ml-2">BizManager</h1>
            </div>
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <button id="user-menu-button" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                        <img class="h-8 w-8 rounded-full object-cover" src="https://placehold.co/100x100/6366f1/white?text=A" alt="Admin avatar">
                    </button>
                    <div id="user-menu" class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white/90 backdrop-blur-md ring-1 ring-black/5 focus:outline-none">
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100/50">Your Profile</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100/50">Settings</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100/50">Sign out</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main content area -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto p-6">
            
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-gray-800">Create New User</h2>
                <a href="dashboard.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
                    &larr; Back to Dashboard
                </a>
            </div>

            <div class="glass-card p-8">
                
                <?php if (!empty($message)): ?>
                    <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form action="add-user.php" method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="full-name" class="block text-sm font-medium text-gray-700">Full Name</label>
                            <input type="text" name="full-name" id="full-name" placeholder="John Doe" value="<?php echo htmlspecialchars($full_name); ?>" class="form-input mt-1 block w-full rounded-md shadow-sm focus:outline-none focus:border-indigo-500 sm:text-sm p-3" required>
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                            <input type="email" name="email" id="email" placeholder="john.doe@example.com" value="<?php echo htmlspecialchars($email); ?>" class="form-input mt-1 block w-full rounded-md shadow-sm focus:outline-none focus:border-indigo-500 sm:text-sm p-3" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                            <input type="password" name="password" id="password" class="form-input mt-1 block w-full rounded-md shadow-sm focus:outline-none focus:border-indigo-500 sm:text-sm p-3" required>
                        </div>
                        <div>
                            <label for="confirm-password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                            <input type="password" name="confirm-password" id="confirm-password" class="form-input mt-1 block w-full rounded-md shadow-sm focus:outline-none focus:border-indigo-500 sm:text-sm p-3" required>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="flex items-center">
                            <input type="checkbox" id="show-password-checkbox" class="rounded h-4 w-4 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-600">Show Password</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700">Role / Department</label>
                            <select id="role" name="role" class="form-input mt-1 block w-full pl-3 pr-10 py-3 text-base focus:outline-none focus:border-indigo-500 sm:text-sm rounded-md" required>
                                <option value="">Select a role</option>
                                <?php
                                if ($roles_result->num_rows > 0) {
                                    while($row = $roles_result->fetch_assoc()) {
                                        $is_selected = ($row['id'] == $selected_role) ? 'selected' : '';
                                        echo "<option value='" . htmlspecialchars($row['id']) . "' $is_selected>" . htmlspecialchars($row['role_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700">Assigned Location (Optional)</label>
                            <select id="location" name="location" class="form-input mt-1 block w-full pl-3 pr-10 py-3 text-base focus:outline-none focus:border-indigo-500 sm:text-sm rounded-md">
                                <option value="">Select a location</option>
                                <?php
                                if ($locations_result->num_rows > 0) {
                                    while($row = $locations_result->fetch_assoc()) {
                                        $is_selected = ($row['id'] == $selected_location) ? 'selected' : '';
                                        echo "<option value='" . htmlspecialchars($row['id']) . "' $is_selected>" . htmlspecialchars($row['location_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="button" onclick="window.location.href='dashboard.php'" class="bg-white/80 py-2 px-4 border border-gray-300/50 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50/50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </button>
                        <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Create User
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const userMenuButton = document.getElementById('user-menu-button');
            const userMenu = document.getElementById('user-menu');
            const showPasswordCheckbox = document.getElementById('show-password-checkbox');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm-password');
            
            userMenuButton.addEventListener('click', function(event) {
                event.stopPropagation();
                userMenu.classList.toggle('hidden');
            });
            
            document.addEventListener('click', function(event) {
                if (userMenu && !userMenu.classList.contains('hidden') && !userMenuButton.contains(event.target)) {
                    userMenu.classList.add('hidden');
                }
            });

            // --- Show/Hide Password Logic ---
            showPasswordCheckbox.addEventListener('change', function() {
                // Check if the checkbox is checked
                const isChecked = this.checked;
                // Set the input type based on the checkbox state
                passwordInput.type = isChecked ? 'text' : 'password';
                confirmPasswordInput.type = isChecked ? 'text' : 'password';
            });
        });
    </script>

</body>
</html>
<?php
// Close the database connection at the end of the script
$conn->close();
?>
