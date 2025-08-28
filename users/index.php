<?php
// users/index.php

// Go up one level to include the global header.
require_once __DIR__ . '/../templates/header.php';

$page_title = "User Management - BizManager";

// --- DATA FETCHING for the user list ---
// We use LEFT JOIN to ensure all users are shown, even if they don't have a location assigned.
$users_result = $conn->query("
    SELECT 
        u.id,
        u.full_name,
        u.email,
        u.company_id,
        u.profile_image_url,
        u.is_active,
        r.role_name,
        l.location_name
    FROM 
        scs_users u
    LEFT JOIN 
        scs_roles r ON u.role_id = r.id
    LEFT JOIN 
        scs_locations l ON u.location_id = l.id
    ORDER BY 
        u.full_name ASC
");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Page Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">User Management</h2>
        <p class="text-gray-600 mt-1">View, add, and manage all user accounts in the system.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <!-- Back to Dashboard Button -->
        <a href="../dashboard.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Dashboard
        </a>
        <!-- Add New User Button -->
        <a href="add-user.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add New User
        </a>
    </div>
</div>

<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th scope="col" class="px-6 py-3">User</th>
                    <th scope="col" class="px-6 py-3">Company ID</th>
                    <th scope="col" class="px-6 py-3">Role</th>
                    <th scope="col" class="px-6 py-3">Location</th>
                    <th scope="col" class="px-6 py-3">Status</th>
                    <th scope="col" class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users_result->num_rows > 0): ?>
                    <?php while($user = $users_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50 hover:bg-gray-50/50">
                        <td class="px-6 py-4 font-medium">
                            <div class="flex items-center">
                                <img class="h-10 w-10 rounded-full object-cover mr-4" 
                                     src="<?php echo htmlspecialchars(!empty($user['profile_image_url']) ? '../' . $user['profile_image_url'] : 'https://placehold.co/100x100/6366f1/white?text=' . strtoupper(substr($user['full_name'], 0, 1))); ?>" 
                                     alt="<?php echo htmlspecialchars($user['full_name']); ?>'s profile picture">
                                <div>
                                    <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($user['company_id']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($user['role_name']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($user['location_name'] ?? 'N/A'); ?></td>
                        <td class="px-6 py-4">
                            <?php if ($user['is_active']): ?>
                                <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Active</span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="font-medium text-indigo-600 hover:underline">Edit</a>
                            <a href="toggle-status.php?id=<?php echo $user['id']; ?>" class="font-medium text-red-600 hover:underline">
                                <?php echo $user['is_active'] ? 'Disable' : 'Enable'; ?>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No users found in the system.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>