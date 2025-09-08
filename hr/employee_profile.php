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

// Initialize variables
$message = '';
$message_type = '';

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && check_permission('HR', 'edit')) {
    $job_title = trim($_POST['job_title']);
    $department = trim($_POST['department']);
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : NULL;
    $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : NULL;
    $salary = !empty($_POST['salary']) ? (float)$_POST['salary'] : NULL;
    $emergency_contact_name = trim($_POST['emergency_contact_name']);
    $emergency_contact_phone = trim($_POST['emergency_contact_phone']);

    $conn->begin_transaction();
    try {
        // Using INSERT ... ON DUPLICATE KEY UPDATE is an efficient way to handle both creating and updating
        $stmt = $conn->prepare("
            INSERT INTO scs_employee_details 
                (user_id, job_title, department, date_of_birth, hire_date, salary, emergency_contact_name, emergency_contact_phone)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                job_title = VALUES(job_title),
                department = VALUES(department),
                date_of_birth = VALUES(date_of_birth),
                hire_date = VALUES(hire_date),
                salary = VALUES(salary),
                emergency_contact_name = VALUES(emergency_contact_name),
                emergency_contact_phone = VALUES(emergency_contact_phone)
        ");
        $stmt->bind_param("issssdss", $user_id, $job_title, $department, $date_of_birth, $hire_date, $salary, $emergency_contact_name, $emergency_contact_phone);
        
        if ($stmt->execute()) {
            $conn->commit();
            $message = "Employee details updated successfully!";
            $message_type = 'success';
            log_activity('EMPLOYEE_UPDATED', "Updated HR details for user ID: " . $user_id, $conn);
        } else {
            throw new Exception("Error updating details: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $message = $e->getMessage();
        $message_type = 'error';
    }
}


// --- DATA FETCHING ---
$stmt = $conn->prepare("
    SELECT 
        u.id, u.full_name, u.email, u.company_id, u.profile_image_url, u.is_active,
        r.role_name,
        l.location_name,
        ed.*
    FROM scs_users u
    LEFT JOIN scs_roles r ON u.role_id = r.id
    LEFT JOIN scs_locations l ON u.location_id = l.id
    LEFT JOIN scs_employee_details ed ON u.id = ed.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$employee) {
    die("Employee not found.");
}
?>

<title><?php echo htmlspecialchars($page_title . " - " . $employee['full_name']); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Employee Profile</h2>
    <a href="employees.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Employee List
    </a>
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
    </div>

    <div class="lg:col-span-3">
        <div class="glass-card p-6">
            <?php if (!empty($message)): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <form action="employee_profile.php?id=<?php echo $user_id; ?>" method="POST">
                <fieldset>
                    <legend class="text-lg font-semibold text-gray-800 mb-4">HR & Employment Details</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="job_title" class="block text-sm font-medium text-gray-700">Job Title</label>
                            <input type="text" name="job_title" id="job_title" value="<?php echo htmlspecialchars($employee['job_title'] ?? ''); ?>" class="form-input mt-1 block w-full p-2">
                        </div>
                         <div>
                            <label for="department" class="block text-sm font-medium text-gray-700">Department</label>
                            <input type="text" name="department" id="department" value="<?php echo htmlspecialchars($employee['department'] ?? ''); ?>" class="form-input mt-1 block w-full p-2">
                        </div>
                        <div>
                            <label for="hire_date" class="block text-sm font-medium text-gray-700">Hire Date</label>
                            <input type="date" name="hire_date" id="hire_date" value="<?php echo htmlspecialchars($employee['hire_date'] ?? ''); ?>" class="form-input mt-1 block w-full p-2">
                        </div>
                        <div>
                            <label for="salary" class="block text-sm font-medium text-gray-700">Salary (<?php echo $app_config['currency_symbol']; ?>)</label>
                            <input type="number" step="0.01" name="salary" id="salary" value="<?php echo htmlspecialchars($employee['salary'] ?? ''); ?>" class="form-input mt-1 block w-full p-2">
                        </div>
                    </div>
                </fieldset>
                
                <fieldset class="mt-8 pt-6 border-t border-gray-200/50">
                     <legend class="text-lg font-semibold text-gray-800 mb-4">Personal & Emergency Contact</legend>
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                         <div>
                            <label for="date_of_birth" class="block text-sm font-medium text-gray-700">Date of Birth</label>
                            <input type="date" name="date_of_birth" id="date_of_birth" value="<?php echo htmlspecialchars($employee['date_of_birth'] ?? ''); ?>" class="form-input mt-1 block w-full p-2">
                        </div>
                        <div></div>
                        <div>
                            <label for="emergency_contact_name" class="block text-sm font-medium text-gray-700">Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" id="emergency_contact_name" value="<?php echo htmlspecialchars($employee['emergency_contact_name'] ?? ''); ?>" class="form-input mt-1 block w-full p-2">
                        </div>
                        <div>
                            <label for="emergency_contact_phone" class="block text-sm font-medium text-gray-700">Emergency Contact Phone</label>
                            <input type="text" name="emergency_contact_phone" id="emergency_contact_phone" value="<?php echo htmlspecialchars($employee['emergency_contact_phone'] ?? ''); ?>" class="form-input mt-1 block w-full p-2">
                        </div>
                     </div>
                </fieldset>
                
                <?php if (check_permission('HR', 'edit')): ?>
                <div class="flex justify-end pt-6 mt-6 border-t border-gray-200/50">
                    <button type="submit" class="inline-flex justify-center py-2 px-6 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Save Changes
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>