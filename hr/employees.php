<?php
// hr/employees.php

require_once __DIR__ . '/../templates/header.php';

// --- SECURITY CHECK ---
if (!check_permission('HR', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Employees - BizManager";

// --- DATA FETCHING for the employee list ---
// We join users with roles, locations, and the new employee_details table
$sql = "
    SELECT 
        u.id,
        u.full_name,
        u.email,
        u.company_id,
        u.profile_image_url,
        u.is_active,
        r.role_name,
        l.location_name,
        ed.job_title,
        ed.department
    FROM 
        scs_users u
    LEFT JOIN 
        scs_roles r ON u.role_id = r.id
    LEFT JOIN 
        scs_locations l ON u.location_id = l.id
    LEFT JOIN
        scs_employee_details ed ON u.id = ed.user_id
    ORDER BY u.full_name ASC
";

$employees_result = $conn->query($sql);

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Employee Directory</h2>
        <p class="text-gray-600 mt-1">A complete list of all employees in the system.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to HR
        </a>
        <?php if (check_permission('Users', 'create')): // Tying this to Users->create permission for now ?>
        <a href="../users/add-user.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 transition-colors">
            Add New Employee
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th scope="col" class="px-6 py-3">Employee</th>
                    <th scope="col" class="px-6 py-3">Job Title / Role</th>
                    <th scope="col" class="px-6 py-3">Location</th>
                    <th scope="col" class="px-6 py-3">Status</th>
                    <th scope="col" class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($employees_result->num_rows > 0): ?>
                    <?php while($emp = $employees_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50 hover:bg-gray-50/50">
                        <td class="px-6 py-4 font-medium">
                            <div class="flex items-center">
                                <img class="h-10 w-10 rounded-full object-cover mr-4" 
                                     src="<?php echo htmlspecialchars(!empty($emp['profile_image_url']) ? '../' . $emp['profile_image_url'] : 'https://placehold.co/100x100/6366f1/white?text=' . strtoupper(substr($emp['full_name'], 0, 1))); ?>" 
                                     alt="<?php echo htmlspecialchars($emp['full_name']); ?>'s profile picture">
                                <div>
                                    <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($emp['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                             <div class="font-semibold"><?php echo htmlspecialchars($emp['job_title'] ?? 'Not Set'); ?></div>
                             <div class="text-xs text-gray-500"><?php echo htmlspecialchars($emp['role_name']); ?></div>
                        </td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($emp['location_name'] ?? 'N/A'); ?></td>
                        <td class="px-6 py-4">
                            <?php if ($emp['is_active']): ?>
                                <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Active</span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <a href="employee_profile.php?id=<?php echo $emp['id']; ?>" class="font-medium text-indigo-600 hover:underline">View Profile</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No employees found in the system.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>