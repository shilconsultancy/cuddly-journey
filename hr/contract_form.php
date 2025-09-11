<?php
// hr/contract_form.php

require_once __DIR__ . '/../templates/header.php';

$page_title = "Create/Edit Contract - BizManager";
$edit_mode = false;
$message = '';
$message_type = '';
$contract_data = [];

if (isset($_GET['id'])) {
    if (!check_permission('HR', 'edit')) die('Permission Denied.');
    $edit_mode = true;
    $contract_id = (int)$_GET['id'];
    $page_title = "Edit Contract";
} else {
    if (!check_permission('HR', 'create')) die('Permission Denied.');
    $page_title = "Create New Contract";
}

// --- FORM PROCESSING: SAVE/UPDATE CONTRACT ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_contract'])) {
    $user_id = $_POST['user_id'];
    $contract_id_post = $_POST['contract_id'];
    $contract_title = trim($_POST['contract_title']);
    $job_type = $_POST['job_type'];
    $start_date = $_POST['start_date'];
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : NULL;
    $salary = (float)$_POST['salary'];
    $contract_details = trim($_POST['contract_details']);
    $created_by = $_SESSION['user_id'];
    
    $conn->begin_transaction();
    try {
        if ($edit_mode) {
            $stmt = $conn->prepare("UPDATE scs_job_contracts SET contract_title=?, start_date=?, end_date=?, job_type=?, salary=?, contract_details=? WHERE id=?");
            $stmt->bind_param("ssssdsi", $contract_title, $start_date, $end_date, $job_type, $salary, $contract_details, $contract_id_post);
        } else {
            $stmt = $conn->prepare("INSERT INTO scs_job_contracts (user_id, contract_title, start_date, end_date, job_type, salary, contract_details, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssdsdi", $user_id, $contract_title, $start_date, $end_date, $job_type, $salary, $contract_details, $created_by);
        }
        
        if(!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        // Also update the main employee details table with the job title and salary
        $stmt_hr = $conn->prepare("UPDATE scs_employee_details SET job_title = ?, salary = ? WHERE user_id = ?");
        $stmt_hr->bind_param("sdi", $contract_title, $salary, $user_id);
        $stmt_hr->execute();
        
        $conn->commit();
        header("Location: contracts.php?success=saved");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// --- DATA FETCHING for the page ---
if ($edit_mode) {
    $stmt = $conn->prepare("SELECT * FROM scs_job_contracts WHERE id = ?");
    $stmt->bind_param("i", $contract_id);
    $stmt->execute();
    $contract_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$contract_data) die("Contract not found.");
}

$employees_result = $conn->query("SELECT id, full_name FROM scs_users WHERE is_active = 1 AND id NOT IN (SELECT user_id FROM scs_job_contracts) ORDER BY full_name ASC");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800"><?php echo htmlspecialchars($page_title); ?></h2>
    <a href="contracts.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Contracts
    </a>
</div>

<div class="glass-card p-8 max-w-4xl mx-auto">
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'error' ? 'bg-red-100/80 text-red-800' : 'bg-green-100/80 text-green-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <form action="contract_form.php<?php if($edit_mode) echo '?id='.$contract_id; ?>" method="POST" class="space-y-6">
        <input type="hidden" name="contract_id" value="<?php echo htmlspecialchars($contract_data['id'] ?? ''); ?>">

        <div>
            <label for="user_id" class="block text-sm font-medium text-gray-700">Employee</label>
            <select name="user_id" id="user_id" class="form-input mt-1 block w-full p-2" <?php if($edit_mode) echo 'disabled'; ?> required>
                <?php if($edit_mode): 
                    $user_stmt = $conn->prepare("SELECT full_name FROM scs_users WHERE id = ?");
                    $user_stmt->bind_param("i", $contract_data['user_id']);
                    $user_stmt->execute();
                    $user = $user_stmt->get_result()->fetch_assoc();
                ?>
                    <option value="<?php echo $contract_data['user_id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                <?php else: ?>
                    <option value="">Select an employee...</option>
                    <?php while($emp = $employees_result->fetch_assoc()): ?>
                        <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?></option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
            <?php if($edit_mode): ?>
                <input type="hidden" name="user_id" value="<?php echo $contract_data['user_id']; ?>">
                <p class="text-xs text-gray-500 mt-1">Employee cannot be changed once a contract is created.</p>
            <?php endif; ?>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="contract_title" class="block text-sm font-medium text-gray-700">Contract Title / Position</label>
                <input type="text" name="contract_title" id="contract_title" value="<?php echo htmlspecialchars($contract_data['contract_title'] ?? ''); ?>" class="form-input mt-1 block w-full p-2" required>
            </div>
             <div>
                <label for="job_type" class="block text-sm font-medium text-gray-700">Job Type</label>
                <select name="job_type" id="job_type" class="form-input mt-1 block w-full p-2">
                    <option value="Full-time" <?php if(($contract_data['job_type'] ?? '') == 'Full-time') echo 'selected'; ?>>Full-time</option>
                    <option value="Part-time" <?php if(($contract_data['job_type'] ?? '') == 'Part-time') echo 'selected'; ?>>Part-time</option>
                    <option value="Contract" <?php if(($contract_data['job_type'] ?? '') == 'Contract') echo 'selected'; ?>>Contract</option>
                    <option value="Internship" <?php if(($contract_data['job_type'] ?? '') == 'Internship') echo 'selected'; ?>>Internship</option>
                </select>
            </div>
             <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($contract_data['start_date'] ?? date('Y-m-d')); ?>" class="form-input mt-1 block w-full p-2" required>
            </div>
             <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700">End Date (for temporary contracts)</label>
                <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($contract_data['end_date'] ?? ''); ?>" class="form-input mt-1 block w-full p-2">
            </div>
             <div>
                <label for="salary" class="block text-sm font-medium text-gray-700">Salary (<?php echo $app_config['currency_symbol']; ?> per month)</label>
                <input type="number" step="0.01" name="salary" id="salary" value="<?php echo htmlspecialchars($contract_data['salary'] ?? ''); ?>" class="form-input mt-1 block w-full p-2" required>
            </div>
        </div>
        
        <div>
            <label for="contract_details" class="block text-sm font-medium text-gray-700">Contract Details / Terms</label>
            <textarea name="contract_details" id="contract_details" rows="10" class="form-input mt-1 block w-full p-2"><?php echo htmlspecialchars($contract_data['contract_details'] ?? ''); ?></textarea>
        </div>

        <div class="flex justify-end pt-4 border-t">
            <button type="submit" name="save_contract" class="py-2 px-6 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                Save Contract
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const employeeSelect = document.getElementById('user_id');
    const contractTitleInput = document.getElementById('contract_title');
    const salaryInput = document.getElementById('salary');

    employeeSelect.addEventListener('change', function() {
        const userId = this.value;
        if (!userId) {
            contractTitleInput.value = '';
            salaryInput.value = '';
            return;
        }

        // We can reuse the same API from the appointment letter
        fetch(`../api/get_employee_contract_details.php?user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    contractTitleInput.value = data.data.job_title || '';
                    salaryInput.value = data.data.salary || '';
                }
            });
    });
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>