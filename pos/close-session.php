<?php
// pos/close-session.php

// STEP 1: All PHP logic, session checks, and redirects go here, before any HTML.
require_once __DIR__ . '/../config.php';

if (!check_permission('POS', 'create')) {
    die('You do not have permission to access the POS system.');
}

$page_title = "Close POS Session - BizManager";
$user_id = $_SESSION['user_id'];
$session_id = $_SESSION['pos_session_id'] ?? 0;

if ($session_id === 0) {
    header("Location: start-session.php");
    exit();
}

// --- DATA FETCHING for summary calculation ---
$session_stmt = $conn->prepare("SELECT * FROM scs_pos_sessions WHERE id = ? AND user_id = ?");
$session_stmt->bind_param("ii", $session_id, $user_id);
$session_stmt->execute();
$session = $session_stmt->get_result()->fetch_assoc();
$session_stmt->close();

$sales_stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN payment_method = 'Cash' THEN total_amount ELSE 0 END) as cash_sales,
        SUM(CASE WHEN payment_method != 'Cash' THEN total_amount ELSE 0 END) as other_sales,
        SUM(total_amount) as total_sales
    FROM scs_pos_sales ps
    JOIN scs_invoices i ON ps.invoice_id = i.id
    WHERE ps.pos_session_id = ?
");
$sales_stmt->bind_param("i", $session_id);
$sales_stmt->execute();
$sales_summary = $sales_stmt->get_result()->fetch_assoc();
$sales_stmt->close();

$opening_balance = $session['opening_balance'] ?? 0;
$cash_sales = $sales_summary['cash_sales'] ?? 0;
$expected_balance = $opening_balance + $cash_sales;

$message = '';
$message_type = '';

// --- FORM PROCESSING TO CLOSE SESSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $closing_balance = (float)$_POST['closing_balance'];
    $end_time = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("UPDATE scs_pos_sessions SET end_time = ?, closing_balance = ?, expected_balance = ?, status = 'Closed' WHERE id = ?");
    $stmt->bind_param("sddi", $end_time, $closing_balance, $expected_balance, $session_id);
    if ($stmt->execute()) {
        log_activity('POS_SESSION_END', "User ended POS session #" . $session_id, $conn);
        unset($_SESSION['pos_session_id']);
        // This redirect will now work perfectly.
        header("Location: start-session.php?success=closed");
        exit();
    } else {
        $message = "Failed to close the session. Please try again.";
        $message_type = 'error';
    }
    $stmt->close();
}


// STEP 2: Now that all logic is complete, it's safe to output HTML.
require_once __DIR__ . '/../templates/header.php';
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col items-center justify-center h-full">
    <div class="glass-card p-8 md:p-12 w-full max-w-lg">
        <h2 class="text-3xl font-bold text-gray-800 mb-2 text-center">End of Session Summary</h2>
        <p class="text-gray-600 mb-8 text-center">Review your sales and enter the final cash amount.</p>

        <?php if (!empty($message)): ?>
            <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'error' ? 'bg-red-100/80 text-red-800' : 'bg-green-100/80 text-green-800'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="space-y-4 text-lg p-6 bg-gray-50/50 rounded-lg mb-6">
            <div class="flex justify-between"><span>Opening Balance:</span> <strong><?php echo number_format($opening_balance, 2); ?></strong></div>
            <div class="flex justify-between"><span>Total Cash Sales:</span> <strong class="text-green-600">+ <?php echo number_format($cash_sales, 2); ?></strong></div>
            <div class="flex justify-between border-t pt-2 mt-2"><span>Other Payments:</span> <strong><?php echo number_format($sales_summary['other_sales'] ?? 0, 2); ?></strong></div>
            <hr>
            <div class="flex justify-between text-xl font-bold"><span>Expected in Drawer:</span> <span><?php echo number_format($expected_balance, 2); ?></span></div>
        </div>

        <form action="close-session.php" method="POST">
             <div>
                <label for="closing_balance" class="block text-sm font-medium text-gray-700 mb-2">Counted Cash Amount</label>
                <div class="relative rounded-md shadow-sm">
                     <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <span class="text-gray-500 sm:text-sm"><?php echo htmlspecialchars($app_config['currency_symbol']); ?></span>
                    </div>
                    <input type="number" step="0.01" name="closing_balance" id="closing_balance" class="form-input block w-full rounded-md border-gray-300 pl-7 pr-12 text-center text-lg p-4" placeholder="0.00" required>
                </div>
            </div>
            <button type="submit" class="mt-6 w-full inline-block px-6 py-4 bg-red-600 text-white font-semibold rounded-lg shadow-md hover:bg-red-700 transition-colors" onclick="return confirm('Are you sure you want to close this session? This action cannot be undone.')">
                End Session
            </button>
        </form>
         <a href="../dashboard.php" class="mt-4 inline-block text-sm text-gray-600 hover:text-indigo-600 text-center w-full">
            &larr; Back to Dashboard
        </a>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>