<?php
// index.php (Login Page)

// Start the session and load config FIRST.
require_once __DIR__ . '/config.php';

// If the user is already logged in, redirect them to the dashboard.
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Initialize variables
$email = '';
$message = '';

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $message = "Please enter both email and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password, role_id, profile_image_url, location_id, data_scope FROM scs_users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                session_regenerate_id();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_full_name'] = $user['full_name'];
                $_SESSION['user_role_id'] = $user['role_id'];
                $_SESSION['user_profile_image'] = $user['profile_image_url'];
                $_SESSION['location_id'] = $user['location_id'];
                $_SESSION['data_scope'] = $user['data_scope'];

                // Fetch ROLE-BASED permissions
                $_SESSION['permissions'] = [];
                $perm_stmt = $conn->prepare("
                    SELECT m.module_name, rp.can_view, rp.can_create, rp.can_edit, rp.can_delete
                    FROM scs_role_permissions rp
                    JOIN scs_modules m ON rp.module_id = m.id
                    WHERE rp.role_id = ?
                ");
                $perm_stmt->bind_param("i", $user['role_id']);
                $perm_stmt->execute();
                $perm_result = $perm_stmt->get_result();
                while ($perm = $perm_result->fetch_assoc()) {
                    $_SESSION['permissions'][$perm['module_name']] = [
                        'can_view' => $perm['can_view'],
                        'can_create' => $perm['can_create'],
                        'can_edit' => $perm['can_edit'],
                        'can_delete' => $perm['can_delete']
                    ];
                }
                $perm_stmt->close();

                // --- NEW: Fetch CUSTOM USER permissions ---
                $_SESSION['custom_permissions'] = [];
                $custom_perm_stmt = $conn->prepare("
                    SELECT m.module_name, up.can_view, up.can_create, up.can_edit, up.can_delete
                    FROM scs_user_permissions up
                    JOIN scs_modules m ON up.module_id = m.id
                    WHERE up.user_id = ?
                ");
                $custom_perm_stmt->bind_param("i", $user['id']);
                $custom_perm_stmt->execute();
                $custom_perm_result = $custom_perm_stmt->get_result();
                while ($perm = $custom_perm_result->fetch_assoc()) {
                    $_SESSION['custom_permissions'][$perm['module_name']] = [
                        'can_view' => $perm['can_view'],
                        'can_create' => $perm['can_create'],
                        'can_edit' => $perm['can_edit'],
                        'can_delete' => $perm['can_delete']
                    ];
                }
                $custom_perm_stmt->close();

                header("Location: dashboard.php");
                exit();
            } else {
                $message = "Invalid email or password.";
            }
        } else {
            $message = "Invalid email or password.";
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($app_config['company_name'] ?? 'BizManager'); ?></title>
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
        .form-input {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.4);
        }
        .form-input:focus {
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.4);
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">

    <div class="w-full max-w-md">
        <div class="glass-card p-8 space-y-6">
            <div class="text-center">
                <h1 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($app_config['company_name'] ?? 'BizManager'); ?></h1>
                <p class="text-gray-600 mt-2">Sign in to your account</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="p-3 rounded-md bg-red-100/80 text-red-800 text-sm">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form action="index.php" method="POST" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" name="email" id="email" placeholder="you@example.com" value="<?php echo htmlspecialchars($email); ?>" class="form-input mt-1 block w-full rounded-md shadow-sm focus:outline-none p-3" required>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" name="password" id="password" class="form-input mt-1 block w-full rounded-md shadow-sm focus:outline-none p-3" required>
                </div>

                <div>
                    <button type="submit" class="w-full inline-flex justify-center py-3 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Sign In
                    </button>
                </div>
            </form>
        </div>
        
        <div class="mt-4 p-4 bg-yellow-100/80 border border-yellow-200/80 text-yellow-800 text-sm rounded-lg text-center">
            <p><strong class="font-semibold">Demo Admin:</strong></p>
            <p>Email: <span class="font-mono">shilconsultancy@gmail.com</span> | Password: <span class="font-mono">admin</span></p>
            <p class="mt-2"><strong class="font-semibold">Demo Sales:</strong></p>
            <p>Email: <span class="font-mono">rahim.ahmed@example.com</span> | Password: <span class="font-mono">password123</span></p>
        </div>
    </div>

</body>
</html>