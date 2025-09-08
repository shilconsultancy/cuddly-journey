<?php
// hr/payslip_details.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('HR', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Payslip Details - BizManager";
$payslip_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($payslip_id === 0) {
    header("Location: payroll.php");
    exit();
}

$message = '';
$message_type = '';

// --- FORM PROCESSING: MARK AS PAID ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mark_as_paid']) && check_permission('HR', 'edit')) {
    $payment_account_id = (int)$_POST['payment_account_id'];
    $net_pay = (float)$_POST['net_pay'];
    $employee_name = $_POST['employee_name'];
    
    // IMPORTANT: Make sure this account ID exists in your scs_chart_of_accounts
    $salaries_expense_account_id = 10; // The ID of 'Salaries & Wages Expense' we just created

    if (empty($payment_account_id)) {
        $message = "You must select a payment account.";
        $message_type = 'error';
    } else {
        $conn->begin_transaction();
        try {
            // 1. Update payslip status
            $paid_at = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("UPDATE scs_payslips SET status = 'Paid', paid_at = ? WHERE id = ? AND status = 'Draft'");
            $stmt->bind_param("si", $paid_at, $payslip_id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                throw new Exception("Payslip could not be updated. It might already be paid.");
            }
            $stmt->close();

            // 2. Create Journal Entry
            $je_description = "Payroll for {$employee_name} for pay period.";
            $debits = [['account_id' => $salaries_expense_account_id, 'amount' => $net_pay]]; // Debit Salaries Expense
            $credits = [['account_id' => $payment_account_id, 'amount' => $net_pay]]; // Credit Cash/Bank
            create_journal_entry($conn, date('Y-m-d'), $je_description, $debits, $credits, 'Payroll', $payslip_id);

            $conn->commit();
            $message = "Payslip marked as paid and journal entry created successfully!";
            $message_type = 'success';
            log_activity('PAYSLIP_PAID', "Payslip ID {$payslip_id} marked as paid.", $conn);

        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}


// --- DATA FETCHING ---
$stmt = $conn->prepare("
    SELECT p.*, u.full_name, ed.job_title, ed.department
    FROM scs_payslips p
    JOIN scs_users u ON p.user_id = u.id
    LEFT JOIN scs_employee_details ed ON u.id = ed.user_id
    WHERE p.id = ?
");
$stmt->bind_param("i", $payslip_id);
$stmt->execute();
$payslip = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payslip) {
    die("Payslip not found.");
}

// Fetch bank/cash accounts for the payment form
$bank_accounts_result = $conn->query("SELECT id, account_name FROM scs_chart_of_accounts WHERE account_type = 'Asset' AND (account_name LIKE '%Bank%' OR account_name LIKE '%Cash%') AND is_active = 1");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Payslip Details</h2>
    <a href="payroll.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Payroll
    </a>
</div>

<div class="glass-card p-8 max-w-4xl mx-auto">
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="flex justify-between items-start border-b pb-4 mb-6">
        <div>
            <h3 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($payslip['full_name']); ?></h3>
            <p class="text-gray-600"><?php echo htmlspecialchars($payslip['job_title'] ?? 'N/A'); ?></p>
        </div>
        <div class="text-right">
            <h4 class="text-lg font-semibold text-gray-700">Payslip</h4>
            <p class="text-gray-500">For Period: <?php echo date('F Y', strtotime($payslip['pay_period_start'])); ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div>
            <h4 class="font-semibold mb-2">Earnings</h4>
            <div class="flex justify-between py-2 border-b"><span>Basic Salary</span><span class="font-mono"><?php echo number_format($payslip['basic_salary'], 2); ?></span></div>
            <div class="flex justify-between py-2 border-b"><span>Bonuses</span><span class="font-mono"><?php echo number_format($payslip['bonuses'], 2); ?></span></div>
            <div class="flex justify-between py-2 font-bold mt-2"><span>Total Earnings</span><span class="font-mono"><?php echo number_format($payslip['basic_salary'] + $payslip['bonuses'], 2); ?></span></div>
        </div>
        <div>
            <h4 class="font-semibold mb-2">Deductions</h4>
            <div class="flex justify-between py-2 border-b"><span>Tax / Other</span><span class="font-mono"><?php echo number_format($payslip['deductions'], 2); ?></span></div>
            <div class="flex justify-between py-2 font-bold mt-2"><span>Total Deductions</span><span class="font-mono"><?php echo number_format($payslip['deductions'], 2); ?></span></div>
        </div>
    </div>

    <div class="mt-8 pt-4 border-t-2 border-gray-300/50 flex justify-end">
        <div class="w-full md:w-1/2">
            <div class="flex justify-between text-2xl font-bold">
                <span>Net Pay:</span>
                <span class="font-mono text-indigo-600"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($payslip['net_pay'], 2)); ?></span>
            </div>
        </div>
    </div>

    <?php if ($payslip['status'] == 'Draft' && check_permission('HR', 'edit')): ?>
        <div class="mt-8 pt-6 border-t border-gray-200/50">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Process Payment</h3>
            <form action="payslip_details.php?id=<?php echo $payslip_id; ?>" method="POST">
                <input type="hidden" name="net_pay" value="<?php echo $payslip['net_pay']; ?>">
                <input type="hidden" name="employee_name" value="<?php echo htmlspecialchars($payslip['full_name']); ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                    <div>
                        <label for="payment_account_id" class="block text-sm font-medium text-gray-700">Pay From Account</label>
                        <select name="payment_account_id" id="payment_account_id" class="form-input mt-1 block w-full p-2" required>
                            <option value="">Select a bank/cash account...</option>
                            <?php while($account = $bank_accounts_result->fetch_assoc()): ?>
                                <option value="<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['account_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" name="mark_as_paid" class="w-full py-2 px-4 rounded-md text-white bg-green-600 hover:bg-green-700">Mark as Paid</button>
                    </div>
                </div>
            </form>
        </div>
    <?php elseif ($payslip['status'] == 'Paid'): ?>
        <div class="mt-8 pt-6 border-t border-gray-200/50 text-center">
            <p class="text-lg font-semibold text-green-600">Paid on <?php echo date('d M, Y', strtotime($payslip['paid_at'])); ?></p>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>