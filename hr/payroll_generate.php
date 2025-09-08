<?php
// hr/payroll_generate.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('HR', 'create')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to generate payslips.</div>');
}

$page_title = "Generate Payslips - BizManager";
$message = '';
$message_type = '';

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pay_month = $_POST['pay_month']; // Format: YYYY-MM
    $pay_period_start = date('Y-m-01', strtotime($pay_month));
    $pay_period_end = date('Y-m-t', strtotime($pay_month));
    
    $conn->begin_transaction();
    try {
        // Find all users with a salary set
        $employees_stmt = $conn->prepare("
            SELECT u.id, ed.salary 
            FROM scs_users u
            JOIN scs_employee_details ed ON u.id = ed.user_id
            WHERE u.is_active = 1 AND ed.salary IS NOT NULL AND ed.salary > 0
        ");
        $employees_stmt->execute();
        $employees = $employees_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($employees)) {
            throw new Exception("No active employees with a salary found.");
        }

        $payslip_stmt = $conn->prepare("
            INSERT INTO scs_payslips (user_id, pay_period_start, pay_period_end, basic_salary, net_pay)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $generated_count = 0;
        foreach($employees as $employee) {
            // Check if a payslip for this user and period already exists
            $check_stmt = $conn->prepare("SELECT id FROM scs_payslips WHERE user_id = ? AND pay_period_start = ?");
            $check_stmt->bind_param("is", $employee['id'], $pay_period_start);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows === 0) {
                // For now, net_pay is the same as basic_salary
                $net_pay = $employee['salary'];
                $payslip_stmt->bind_param("issdd", $employee['id'], $pay_period_start, $pay_period_end, $employee['salary'], $net_pay);
                $payslip_stmt->execute();
                $generated_count++;
            }
        }

        $conn->commit();
        if ($generated_count > 0) {
            log_activity('PAYSLIPS_GENERATED', "Generated {$generated_count} payslips for " . date('F Y', strtotime($pay_month)), $conn);
            header("Location: payroll.php?success=generated");
            exit();
        } else {
            $message = "Payslips for this period have already been generated for all eligible employees.";
            $message_type = 'error';
        }

    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error generating payslips: " . $e->getMessage();
        $message_type = 'error';
    }
}

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Generate Payslips</h2>
    <a href="payroll.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Payroll
    </a>
</div>

<div class="glass-card p-8 max-w-lg mx-auto">
     <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <form action="payroll_generate.php" method="POST" class="space-y-4">
        <div>
            <label for="pay_month" class="block text-sm font-medium text-gray-700">Select Pay Period (Month)</label>
            <input type="month" name="pay_month" id="pay_month" value="<?php echo date('Y-m'); ?>" class="form-input mt-1 block w-full p-2" required>
            <p class="text-xs text-gray-500 mt-2">This will generate draft payslips for all active employees with a salary set for the selected month. It will not generate duplicates.</p>
        </div>
        <div class="flex justify-end pt-2">
            <button type="submit" class="w-full inline-flex justify-center py-3 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700" onclick="return confirm('Are you sure you want to generate payslips for the selected month?');">
                Generate Payslips
            </button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>