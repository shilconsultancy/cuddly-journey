<?php
// hr/contract_form.php

// The entire form processing block has been moved to the top of the file.
// This ensures that the header() redirect can execute before any HTML is sent.
require_once __DIR__ . '/../config.php'; // Using config.php for session_start() and db connection.

// --- FORM PROCESSING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // We need to re-establish some variables for the POST request context
    $contract_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $is_editing = $contract_id > 0;
    
    // Check permissions for the action
    $can_create = check_permission('HR', 'create');
    $can_edit = check_permission('HR', 'edit');

    if (($is_editing && !$can_edit) || (!$is_editing && !$can_create)) {
         // Redirect or die if they don't have permission for the action they are trying.
         header("Location: contracts.php?error=permission");
         exit();
    }

    // Sanitize input
    $employee_id = (int)$_POST['employee_id'];
    $contract_title = trim($_POST['contract_title']);
    $job_type = $_POST['job_type'];
    $start_date = $_POST['start_date'];
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : NULL;
    $salary = (float)$_POST['salary'];
    $terms = $_POST['terms_and_conditions'];

    // Validation
    if (empty($employee_id) || empty($contract_title) || empty($start_date) || empty($salary)) {
        // In a real app, you would redirect back with an error message
        // For simplicity, we'll just stop. A more robust solution involves sessions for flash messages.
        die("Validation failed. Please go back and fill all required fields.");
    } else {
        $conn->begin_transaction();
        try {
            if ($is_editing) {
                $stmt = $conn->prepare("UPDATE scs_job_contracts SET user_id=?, contract_title=?, job_type=?, start_date=?, end_date=?, salary=?, terms_and_conditions=? WHERE id=?");
                $stmt->bind_param("isssdssi", $employee_id, $contract_title, $job_type, $start_date, $end_date, $salary, $terms, $contract_id);
                $action = 'CONTRACT_UPDATED';
                $log_description = "Updated contract for user ID: $employee_id";
            } else {
                $stmt = $conn->prepare("INSERT INTO scs_job_contracts (user_id, contract_title, job_type, start_date, end_date, salary, terms_and_conditions) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssdss", $employee_id, $contract_title, $job_type, $start_date, $end_date, $salary, $terms);
                $action = 'CONTRACT_CREATED';
                $log_description = "Created a new contract for user ID: $employee_id";
            }
            
            $stmt->execute();
            $stmt->close();

            $stmt_update_emp = $conn->prepare("UPDATE scs_employee_details SET job_title = ?, salary = ? WHERE user_id = ?");
            $stmt_update_emp->bind_param("sdi", $contract_title, $salary, $employee_id);
            $stmt_update_emp->execute();
            $stmt_update_emp->close();
            
            log_activity($action, $log_description, $conn);
            $conn->commit();
            
            // This is the line that caused the error. Now it will work correctly.
            header("Location: contracts.php?success=1");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            // In a real app, you would store this error in a session and redirect.
            die("An error occurred: " . $e->getMessage());
        }
    }
}

// --- PAGE DISPLAY LOGIC (for GET requests) ---
// Now we include the header, as we are certain no more headers will be sent.
require_once __DIR__ . '/../templates/header.php';

$page_title = "Create/Edit Job Contract";

// Security check for viewing the page
$can_create = check_permission('HR', 'create');
$can_edit = check_permission('HR', 'edit');
if (!$can_create && !$can_edit) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

// Initialize variables for the form display
$contract_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$employee_id = '';
$contract_title = '';
$job_type = 'Full-time';
$start_date = date('Y-m-d');
$end_date = '';
$salary = '';
$terms = '';
$is_editing = false;
$message = '';
$message_type = '';

$default_terms_template = <<<HTML
<h3>SHIL EMPLOYMENT CONTRACT</h3>
<p>This Employment Contract ("Contract") is made and entered into on this <strong>[Start Date]</strong>, by and between:</p>
<p><strong>Employer:</strong> SHIL Consultancy, having its principal place of business at [Company Address] (hereinafter referred to as the "Company"),</p>
<p><strong>AND</strong></p>
<p><strong>Employee:</strong> [Employee Name], residing at [Employee's Permanent Address], NID: [Employee NID] (hereinafter referred to as the "Employee").</p>
<p>Both parties agree to the terms and conditions outlined below:</p>
<hr>
<p><strong>1. Position and Duties</strong></p>
<p><strong>Position:</strong> The Employee is employed as <strong>[Job Title]</strong>, reporting to the [Reporting Manager's Title] or any other person designated by the Company.</p>
<p><strong>Duties:</strong> The Employee agrees to perform the duties and responsibilities as assigned and outlined in the job description provided by the Employer. The Employee may also be required to perform additional duties as needed.</p>
<p><strong>2. Employment Type</strong></p>
<p><strong>Employment Status:</strong> [Job Type, e.g., Full-time] with flexibility provided to undertake academic commitments if applicable.</p>
<p><strong>Work Location:</strong> The primary place of work will be in-office. The Employee may be required to travel as necessary for the role.</p>
<p><strong>3. Compensation and Benefits</strong></p>
<p><strong>Salary:</strong> The Employer agrees that this position is a paid position. The Employee will receive <strong>BDT [Initial Salary Amount]</strong> for the first month and then <strong>BDT [Permanent Salary Amount]</strong> after that as compensation for their services.</p>
<p><strong>Payment Schedule:</strong> Salary will be paid on a monthly basis, on or before the 15th of the following month.</p>
<p><strong>Benefits:</strong> The Employee is eligible for benefits, including sick leave and vacation days, as per the Company’s policies.</p>
<p><strong>Yearly / Festival Bonus:</strong> The Employee will get two yearly festival bonuses, each equivalent to 15% of the gross salary. This will be applicable after the Employee is permanently hired and completes one (1) year of continuous employment (including probation).</p>
<p><strong>4. Probationary Period</strong></p>
<p>The first <strong>180 days</strong> of employment will be considered a probationary period. During this time, the Employer may terminate employment without notice or severance pay if the Employee’s performance is deemed unsatisfactory.</p>
<p><strong>5. Working Hours</strong></p>
<p><strong>Work Hours:</strong> The Employee’s standard working hours are from <strong>10:00 AM to 7:00 PM, 6 days per week (Saturday to Thursday)</strong>.</p>
<p><strong>6. Leave and Time Off</strong></p>
<p><strong>Annual Leave:</strong> After one (1) year of continuous employment, the Employee is entitled to a half-month (equal to 10 working days + weekend) of annual leave. Half of this leave will be paid, and the other half will be unpaid. Requests for annual leave must be submitted 15 days in advance. If no leave is requested by the employee, mandatory unpaid leave may be enforced.</p>
<p><strong>Sick Leave:</strong> The Employee is entitled to 15 days of paid sick leave per year. Additional leave may be granted upon assessment of a submitted medical report.</p>
<p><strong>Public Holidays:</strong> The Company will observe all government-mandated public holidays.</p>
<p><strong>7. Confidentiality and Non-Disclosure</strong></p>
<p>The Employee agrees to maintain strict confidentiality regarding the Company’s proprietary information, trade secrets, client data, and any other confidential information both during and after the term of employment.</p>
<p><strong>8. Non-Compete and Non-Solicitation</strong></p>
<p>For a period of one (1) year following the termination of employment, the Employee agrees not to engage in any business that directly competes with the Company and agrees not to solicit employees or clients of the Company.</p>
<p><strong>9. Termination of Employment</strong></p>
<p><strong>Termination by Employer:</strong> After the probationary period, the Company may terminate this agreement with one (1) month’s written notice or payment in lieu of notice.</p>
<p><strong>Termination by Employee:</strong> The Employee may resign by providing two (2) months’ written notice to the Company.</p>
<p><strong>Grounds for Immediate Termination:</strong> The Company reserves the right to terminate employment immediately and without notice for serious misconduct, gross negligence, breach of contract, or any other reason justifiable by company policy or the laws of Bangladesh.</p>
<p><strong>10. Intellectual Property</strong></p>
<p>Any work, inventions, designs, software, documents, or other materials created by the Employee within the scope of their employment shall be the sole and exclusive property of the Company.</p>
<p><strong>11. Governing Law</strong></p>
<p>This Contract shall be governed by and construed in accordance with the laws of the People’s Republic of Bangladesh.</p>
<p><strong>12. Entire Agreement</strong></p>
<p>This Contract constitutes the entire agreement between the parties. Any amendments must be made in writing and signed by both parties.</p>
<p><strong>13. Acceptance of Terms</strong></p>
<p>By signing below, both parties confirm that they have read, understood, and agreed to the terms and conditions of this employment contract.</p>
<hr>
<p><strong>Employer Signature:</strong></p>
<p>______________________________</p>
<p>Saikat Kumar Shil</p>
<p>Date: [Date of Signing]</p>
<br>
<p><strong>Employee Signature:</strong></p>
<p>______________________________</p>
<p>[Employee Name]</p>
<p>Date: [Date of Signing]</p>
HTML;

// If editing, fetch existing data
if ($contract_id > 0) {
    if (!$can_edit) die("You do not have permission to edit contracts.");
    $is_editing = true;
    $stmt = $conn->prepare("SELECT * FROM scs_job_contracts WHERE id = ?");
    $stmt->bind_param("i", $contract_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $contract = $result->fetch_assoc();
        $employee_id = $contract['user_id'];
        $contract_title = $contract['contract_title'];
        $job_type = $contract['job_type'];
        $start_date = $contract['start_date'];
        $end_date = $contract['end_date'];
        $salary = $contract['salary'];
        $terms = $contract['terms_and_conditions'];
    } else {
        // Redirect if contract not found
        header("Location: contracts.php");
        exit();
    }
    $stmt->close();
} else {
    if (!$can_create) die("You do not have permission to create contracts.");
    // For a new contract, set the default terms
    $terms = $default_terms_template;
}

// Fetch employees for dropdown
$employees_result = $conn->query("SELECT id, full_name, company_id FROM scs_users WHERE is_active = 1 ORDER BY full_name ASC");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800"><?php echo $is_editing ? 'Edit Job Contract' : 'Create New Job Contract'; ?></h2>
    <a href="contracts.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Contracts List
    </a>
</div>

<div class="glass-card p-8">
    
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="contract_form.php<?php echo $is_editing ? '?id=' . $contract_id : ''; ?>" method="POST" class="space-y-6">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="employee_id" class="block text-sm font-medium text-gray-700">Employee *</label>
                <select id="employee_id" name="employee_id" class="form-input mt-1 block w-full p-3 rounded-md" required>
                    <option value="">Select an Employee</option>
                    <?php while($emp = $employees_result->fetch_assoc()): ?>
                        <option value="<?php echo $emp['id']; ?>" <?php if ($emp['id'] == $employee_id) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($emp['full_name'] . ' (' . $emp['company_id'] . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label for="contract_title" class="block text-sm font-medium text-gray-700">Contract Title / Job Title *</label>
                <input type="text" name="contract_title" id="contract_title" value="<?php echo htmlspecialchars($contract_title); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <label for="job_type" class="block text-sm font-medium text-gray-700">Job Type *</label>
                <select id="job_type" name="job_type" class="form-input mt-1 block w-full p-3 rounded-md" required>
                    <option value="Full-time" <?php if ($job_type == 'Full-time') echo 'selected'; ?>>Full-time</option>
                    <option value="Part-time" <?php if ($job_type == 'Part-time') echo 'selected'; ?>>Part-time</option>
                    <option value="Contract" <?php if ($job_type == 'Contract') echo 'selected'; ?>>Contract</option>
                    <option value="Internship" <?php if ($job_type == 'Internship') echo 'selected'; ?>>Internship</option>
                </select>
            </div>
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date *</label>
                <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700">End Date (Optional)</label>
                <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="form-input mt-1 block w-full rounded-md p-3">
            </div>
            <div>
                <label for="salary" class="block text-sm font-medium text-gray-700">Gross Salary (Monthly) *</label>
                <input type="number" step="0.01" name="salary" id="salary" value="<?php echo htmlspecialchars($salary); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
        </div>
        
        <div>
            <label for="terms_and_conditions" class="block text-sm font-medium text-gray-700">Terms and Conditions</label>
            <textarea id="terms_and_conditions" name="terms_and_conditions" rows="20" class="form-input mt-1 block w-full rounded-md p-3"><?php echo htmlspecialchars($terms); ?></textarea>
        </div>

        <div class="flex justify-end pt-6 border-t border-gray-200/50">
            <a href="contracts.php" class="bg-white/80 py-2 px-4 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50/50">Cancel</a>
            <button type="submit" class="ml-3 inline-flex justify-center py-2 px-6 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                <?php echo $is_editing ? 'Save Changes' : 'Create Contract'; ?>
            </button>
        </div>
    </form>
</div>

<script>
    ClassicEditor
        .create( document.querySelector( '#terms_and_conditions' ), {
            toolbar: [ 'heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', '|', 'undo', 'redo' ]
        } )
        .catch( error => {
            console.error( error );
        } );
</script>

<style>
/* Basic styling for CKEditor to fit the UI */
.ck-editor__editable_inline {
    min-height: 400px;
}
</style>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>