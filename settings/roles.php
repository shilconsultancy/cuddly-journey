<?php
// settings/roles.php

require_once __DIR__ . '/../templates/header.php';

$page_title = "Roles & Permissions - BizManager";

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_permissions'])) {
    $conn->begin_transaction();
    try {
        // First, clear all existing permissions from the table
        $conn->query("DELETE FROM scs_role_permissions");

        $stmt = $conn->prepare("INSERT INTO scs_role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete) VALUES (?, ?, ?, ?, ?, ?)");

        if (!empty($_POST['permissions'])) {
            foreach ($_POST['permissions'] as $role_id => $modules) {
                foreach ($modules as $module_id => $actions) {
                    $can_view = isset($actions['view']) ? 1 : 0;
                    $can_create = isset($actions['create']) ? 1 : 0;
                    $can_edit = isset($actions['edit']) ? 1 : 0;
                    $can_delete = isset($actions['delete']) ? 1 : 0;
                    
                    // Only insert if at least one permission is granted
                    if ($can_view || $can_create || $can_edit || $can_delete) {
                        $stmt->bind_param("iiiiii", $role_id, $module_id, $can_view, $can_create, $can_edit, $can_delete);
                        $stmt->execute();
                    }
                }
            }
        }
        
        $conn->commit();
        $message = "Permissions updated successfully!";
        $message_type = 'success';
        
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $message = "Error updating permissions: " . $exception->getMessage();
        $message_type = 'error';
    }
}

// --- DATA FETCHING ---
$roles_result = $conn->query("SELECT * FROM scs_roles WHERE id != 1 ORDER BY id"); // Exclude Super Admin
$roles = $roles_result->fetch_all(MYSQLI_ASSOC);

$modules_result = $conn->query("SELECT * FROM scs_modules ORDER BY module_name ASC");
$modules = $modules_result->fetch_all(MYSQLI_ASSOC);

$role_permissions_result = $conn->query("SELECT * FROM scs_role_permissions");
$current_permissions = [];
while ($row = $role_permissions_result->fetch_assoc()) {
    $current_permissions[$row['role_id']][$row['module_id']] = $row;
}
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Page Header -->
<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Roles & Permissions</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Settings
    </a>
</div>

<div class="glass-card p-8">
    
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="roles.php" method="POST">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                    <tr>
                        <th scope="col" class="px-6 py-3">Module</th>
                        <?php foreach ($roles as $role): ?>
                            <th scope="col" class="px-6 py-3 text-center" style="min-width: 200px;"><?php echo htmlspecialchars($role['role_name']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $module): ?>
                        <tr class="bg-white/50 border-b border-gray-200/50">
                            <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($module['module_name']); ?></td>
                            <?php foreach ($roles as $role): ?>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex justify-around items-center space-x-4">
                                        <?php
                                        $actions = ['view', 'create', 'edit', 'delete'];
                                        foreach ($actions as $action):
                                            $perm_key = 'can_' . $action;
                                            $checked = isset($current_permissions[$role['id']][$module['id']][$perm_key]) && $current_permissions[$role['id']][$module['id']][$perm_key] == 1;
                                        ?>
                                        <label class="flex flex-col items-center text-xs">
                                            <input type="checkbox" name="permissions[<?php echo $role['id']; ?>][<?php echo $module['id']; ?>][<?php echo $action; ?>]"
                                                   class="w-4 h-4 text-indigo-600 bg-gray-100 rounded border-gray-300 focus:ring-indigo-500"
                                                   <?php if ($checked) echo 'checked'; ?>>
                                            <span class="mt-1 capitalize"><?php echo $action; ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end pt-6 mt-4 border-t border-gray-200/50">
            <button type="submit" name="save_permissions" class="inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                Save Changes
            </button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>