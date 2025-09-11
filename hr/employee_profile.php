<?php
// hr/employee_profile.php

require_once __DIR__ . '/../templates/header.php';

// --- SECURITY CHECK ---
if (!check_permission('HR', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Employee Profile - BizManager";
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id === 0) {
    header("Location: employees.php");
    exit();
}

// --- DATA FETCHING ---
// 1. Fetch all combined user and employee details
$stmt = $conn->prepare("
    SELECT 
        u.id, u.full_name, u.email, u.company_id, u.profile_image_url, u.is_active, u.data_scope,
        r.role_name,
        l.location_name,
        mgr.full_name as manager_name,
        ed.*
    FROM scs_users u
    LEFT JOIN scs_roles r ON u.role_id = r.id
    LEFT JOIN scs_locations l ON u.location_id = l.id
    LEFT JOIN scs_employee_details ed ON u.id = ed.user_id
    LEFT JOIN scs_users mgr ON ed.reporting_manager_id = mgr.id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$employee) {
    die("Employee not found.");
}

// 2. Fetch user's activity logs
$activity_logs_result = $conn->query("SELECT * FROM scs_activity_logs WHERE user_id = $user_id ORDER BY timestamp DESC LIMIT 50");

// 3. Fetch permissions (Role-based and Custom)
$modules_result = $conn->query("SELECT id, module_name FROM scs_modules ORDER BY module_name ASC");
$modules = $modules_result->fetch_all(MYSQLI_ASSOC);

// Get Role permissions
$role_perms_stmt = $conn->prepare("SELECT * FROM scs_role_permissions WHERE role_id = ?");
$role_perms_stmt->bind_param("i", $employee['role_id']);
$role_perms_stmt->execute();
$role_perms_result = $role_perms_stmt->get_result();
$role_permissions = [];
while ($row = $role_perms_result->fetch_assoc()) {
    $role_permissions[$row['module_id']] = $row;
}
$role_perms_stmt->close();

// Get Custom User permissions
$custom_perms_stmt = $conn->prepare("SELECT * FROM scs_user_permissions WHERE user_id = ?");
$custom_perms_stmt->bind_param("i", $user_id);
$custom_perms_stmt->execute();
$custom_perms_result = $custom_perms_stmt->get_result();
$custom_permissions = [];
while ($row = $custom_perms_result->fetch_assoc()) {
    $custom_permissions[$row['module_id']] = $row;
}
$custom_perms_stmt->close();

?>

<title><?php echo htmlspecialchars($page_title . " - " . $employee['full_name']); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Employee Profile</h2>
    <div class="flex space-x-2">
        <a href="employees.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to Employee List
        </a>
        <?php if (check_permission('Users', 'edit')): ?>
        <a href="../users/edit-user.php?id=<?php echo $user_id; ?>" class="px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700">
            Edit Profile
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
    <div class="lg:col-span-1">
        <div class="glass-card p-6 text-center">
            <img class="h-32 w-32 rounded-full object-cover mx-auto mb-4" 
                 src="<?php echo htmlspecialchars(!empty($employee['profile_image_url']) ? '../' . $employee['profile_image_url'] : 'https://placehold.co/200x200/6366f1/white?text=' . strtoupper(substr($employee['full_name'], 0, 1))); ?>" 
                 alt="Profile picture">
            <h3 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($employee['full_name']); ?></h3>
            <p class="text-gray-600"><?php echo htmlspecialchars($employee['job_title'] ?? 'Job Title Not Set'); ?></p>
            <p class="text-sm text-gray-500 mt-2"><?php echo htmlspecialchars($employee['company_id']); ?></p>
            
            <div class="mt-4 pt-4 border-t border-gray-200/50 text-left space-y-2 text-sm">
                <p><strong class="text-gray-500">Email:</strong> <span class="text-gray-700"><?php echo htmlspecialchars($employee['email']); ?></span></p>
                <p><strong class="text-gray-500">Role:</strong> <span class="text-gray-700"><?php echo htmlspecialchars($employee['role_name']); ?></span></p>
                <p><strong class="text-gray-500">Location:</strong> <span class="text-gray-700"><?php echo htmlspecialchars($employee['location_name'] ?? 'N/A'); ?></span></p>
                <p><strong class="text-gray-500">Status:</strong> 
                    <?php if ($employee['is_active']): ?>
                        <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Active</span>
                    <?php else: ?>
                        <span class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">Inactive</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="glass-card p-6 mt-6">
            <h4 class="font-semibold text-gray-800 mb-3">Documents</h4>
            <div class="space-y-3">
                <a href="../<?php echo !empty($employee['id_card_url']) ? htmlspecialchars($employee['id_card_url']) : '#'; ?>" target="_blank" class="block text-sm <?php echo !empty($employee['id_card_url']) ? 'text-indigo-600 hover:underline' : 'text-gray-400 cursor-not-allowed'; ?>">View ID Card</a>
                <a href="../<?php echo !empty($employee['cv_url']) ? htmlspecialchars($employee['cv_url']) : '#'; ?>" target="_blank" class="block text-sm <?php echo !empty($employee['cv_url']) ? 'text-indigo-600 hover:underline' : 'text-gray-400 cursor-not-allowed'; ?>">View CV/Resume</a>
                <a href="../<?php echo !empty($employee['educational_certs_url']) ? htmlspecialchars($employee['educational_certs_url']) : '#'; ?>" target="_blank" class="block text-sm <?php echo !empty($employee['educational_certs_url']) ? 'text-indigo-600 hover:underline' : 'text-gray-400 cursor-not-allowed'; ?>">View Educational Certificates</a>
                <a href="../<?php echo !empty($employee['experience_certs_url']) ? htmlspecialchars($employee['experience_certs_url']) : '#'; ?>" target="_blank" class="block text-sm <?php echo !empty($employee['experience_certs_url']) ? 'text-indigo-600 hover:underline' : 'text-gray-400 cursor-not-allowed'; ?>">View Experience Certificates</a>
            </div>
        </div>
    </div>

    <div class="lg:col-span-3">
        <div class="glass-card p-6">
            <div class="border-b border-gray-200/50">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <button onclick="changeTab('details')" class="tab-button border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Details</button>
                    <button onclick="changeTab('activity')" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Activity</button>
                    <button onclick="changeTab('permissions')" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Permissions</button>
                </nav>
            </div>

            <div class="pt-6">
                <div id="details" class="tab-content space-y-8">
                    <fieldset>
                        <legend class="text-lg font-semibold text-gray-800 mb-4">Personal Information</legend>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                            <div><p class="text-gray-500">Father’s Name</p><p class="font-semibold"><?php echo htmlspecialchars($employee['father_name'] ?? 'N/A'); ?></p></div>
                            <div><p class="text-gray-500">Mother’s Name</p><p class="font-semibold"><?php echo htmlspecialchars($employee['mother_name'] ?? 'N/A'); ?></p></div>
                            <div><p class="text-gray-500">Date of Birth</p><p class="font-semibold"><?php echo $employee['date_of_birth'] ? date($app_config['date_format'], strtotime($employee['date_of_birth'])) : 'N/A'; ?></p></div>
                            <div><p class="text-gray-500">Gender</p><p class="font-semibold"><?php echo htmlspecialchars($employee['gender'] ?? 'N/A'); ?></p></div>
                            <div><p class="text-gray-500">Marital Status</p><p class="font-semibold"><?php echo htmlspecialchars($employee['marital_status'] ?? 'N/A'); ?></p></div>
                            <div><p class="text-gray-500">National ID</p><p class="font-semibold"><?php echo htmlspecialchars($employee['national_id'] ?? 'N/A'); ?></p></div>
                            <div><p class="text-gray-500">Blood Group</p><p class="font-semibold"><?php echo htmlspecialchars($employee['blood_group'] ?? 'N/A'); ?></p></div>
                            <div><p class="text-gray-500">Nationality</p><p class="font-semibold"><?php echo htmlspecialchars($employee['nationality'] ?? 'N/A'); ?></p></div>
                        </div>
                    </fieldset>
                    <fieldset class="pt-6 border-t border-gray-200/50">
                        <legend class="text-lg font-semibold text-gray-800 mb-4">Contact Information</legend>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                            <div><p class="text-gray-500">Mobile Number</p><p class="font-semibold"><?php echo htmlspecialchars($employee['phone'] ?? 'N/A'); ?></p></div>
                            <div class="md:col-span-2"><p class="text-gray-500">Present Address</p><p class="font-semibold"><?php echo htmlspecialchars($employee['address'] ?? 'N/A'); ?></p></div>
                            <div class="md:col-span-2"><p class="text-gray-500">Permanent Address</p><p class="font-semibold"><?php echo htmlspecialchars($employee['permanent_address'] ?? 'N/A'); ?></p></div>
                            <div><p class="text-gray-500">Emergency Contact</p><p class="font-semibold"><?php echo htmlspecialchars($employee['emergency_contact_name'] ?? 'N/A'); ?></p></div>
                            <div><p class="text-gray-500">Emergency Phone</p><p class="font-semibold"><?php echo htmlspecialchars($employee['emergency_contact_phone'] ?? 'N/A'); ?></p></div>
                        </div>
                    </fieldset>
                     <fieldset class="pt-6 border-t border-gray-200/50">
                        <legend class="text-lg font-semibold text-gray-800 mb-4">Employment & Salary</legend>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                            <div><p class="text-gray-500">Department</p><p class="font-semibold"><?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?></p></div>
                            <div><p class="text-gray-500">Hire Date</p><p class="font-semibold"><?php echo $employee['hire_date'] ? date($app_config['date_format'], strtotime($employee['hire_date'])) : 'N/A'; ?></p></div>
                            <div><p class="text-gray-500">Employment Type</p><p class="font-semibold"><?php echo htmlspecialchars($employee['employment_type'] ?? 'N/A'); ?></p></div>
                            <div><p class="text-gray-500">Reporting Manager</p><p class="font-semibold"><?php echo htmlspecialchars($employee['manager_name'] ?? 'N/A'); ?></p></div>
                            <div><p class="text-gray-500">Gross Salary</p><p class="font-semibold"><?php echo $employee['salary'] ? $app_config['currency_symbol'] . number_format($employee['salary'], 2) : 'N/A'; ?></p></div>
                        </div>
                    </fieldset>
                </div>
                
                <div id="activity" class="tab-content hidden">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Activity</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-700">
                             <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Date & Time</th>
                                    <th scope="col" class="px-6 py-3">Action</th>
                                    <th scope="col" class="px-6 py-3">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($activity_logs_result->num_rows > 0): ?>
                                    <?php while($log = $activity_logs_result->fetch_assoc()): ?>
                                    <tr class="bg-white/50 border-b border-gray-200/50">
                                        <td class="px-6 py-4"><?php echo date($app_config['date_format'] . " H:i", strtotime($log['timestamp'])); ?></td>
                                        <td class="px-6 py-4"><span class="px-2 py-1 text-xs font-semibold text-indigo-800 bg-indigo-100 rounded-full"><?php echo htmlspecialchars($log['action']); ?></span></td>
                                        <td class="px-6 py-4"><?php echo htmlspecialchars($log['description']); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">No activity recorded for this user.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div id="permissions" class="tab-content hidden">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Effective Permissions</h3>
                    <p class="text-sm text-gray-500 mb-4">This table shows the final permissions for this user, combining their role defaults with any custom overrides. <span class="font-semibold text-blue-600">Blue checkmarks</span> indicate a custom permission.</p>
                     <div class="overflow-x-auto">
                        <table class="w-full text-sm text-center text-gray-700">
                             <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left">Module</th>
                                    <th scope="col" class="px-2 py-3">View</th>
                                    <th scope="col" class="px-2 py-3">Create</th>
                                    <th scope="col" class="px-2 py-3">Edit</th>
                                    <th scope="col" class="px-2 py-3">Delete</th>
                                </tr>
                            </thead>
                             <tbody>
                                <?php foreach($modules as $module): 
                                    $moduleId = $module['id'];
                                    $is_custom = isset($custom_permissions[$moduleId]);
                                    $perms_to_use = $is_custom ? $custom_permissions[$moduleId] : ($role_permissions[$moduleId] ?? []);
                                ?>
                                <tr class="bg-white/50 border-b border-gray-200/50">
                                    <td class="px-6 py-4 font-semibold text-left"><?php echo htmlspecialchars($module['module_name']); ?></td>
                                    <td><?php echo_permission_icon($perms_to_use['can_view'] ?? 0, $is_custom); ?></td>
                                    <td><?php echo_permission_icon($perms_to_use['can_create'] ?? 0, $is_custom); ?></td>
                                    <td><?php echo_permission_icon($perms_to_use['can_edit'] ?? 0, $is_custom); ?></td>
                                    <td><?php echo_permission_icon($perms_to_use['can_delete'] ?? 0, $is_custom); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php
function echo_permission_icon($has_perm, $is_custom) {
    if ($has_perm) {
        $color = $is_custom ? 'text-blue-500' : 'text-green-500';
        echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mx-auto ' . $color . '" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
    } else {
        echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mx-auto text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
    }
}
?>

<script>
function changeTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.add('hidden'));
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('border-indigo-500', 'text-indigo-600');
        button.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
    });
    document.getElementById(tabName).classList.remove('hidden');
    const activeButton = document.querySelector(`button[onclick="changeTab('${tabName}')"]`);
    activeButton.classList.add('border-indigo-500', 'text-indigo-600');
    activeButton.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
}
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>