<?php
// accounts/pay_bill.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'create')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to record payments.</div>');
}

$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($bill_id === 0) {
    die('<div class="glass-card p-8 text-center">Invalid Bill ID provided.</div>');
}

$message = '';
$message_type = '';

// Fetch the bill to get details
$bill_stmt = $conn->prepare("SELECT b.*, s.supplier_name FROM scs_supplier_bills b JOIN scs_suppliers s ON b.supplier_id = s.id WHERE b.id = ?");
$bill_stmt->bind_param("i", $bill_id);
$bill_stmt->execute();
$bill = $bill_stmt->get_result()->fetch_assoc();
$bill_stmt->close();

if (!$bill) {
    die('<div class="glass-card p-8 text-center">Bill not found.</div>');
}

$balance_due = $bill['total_amount'] - $bill['amount_paid'];
$page_title = "Pay Bill: " . htmlspecialchars($bill['bill_number']);

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $payment_amount = (float)$_POST['amount'];
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];
    $payment_account_id = (int)$_POST['payment_account_id'];
    $notes = $_POST['notes'];
    $recorded_by = $_SESSION['user_id'];

    if ($payment_amount <= 0) {
        $message = "Payment amount must be greater than zero.";
        $message_type = 'error';
    } elseif ($payment_amount > $balance_due + 0.001) { // Add tolerance for float comparison
        $message = "Payment amount cannot be greater than the balance due.";
        $message_type = 'error';
    } elseif (empty($payment_account_id)) {
        $message = "Please select a 'Payment From' account.";
        $message_type = 'error';
    } else {
        $conn->begin_transaction();
        try {
            // Step 1: Insert the payment record
            $stmt_payment = $conn->prepare("INSERT INTO scs_bill_payments (bill_id, payment_date, amount, payment_method, payment_account_id, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_payment->bind_param("isdsisi", $bill_id, $payment_date, $payment_amount, $payment_method, $payment_account_id, $notes, $recorded_by);
            $stmt_payment->execute();
            $payment_id = $conn->insert_id;

            // Step 2: Update the amount_paid on the bill
            $new_amount_paid = $bill['amount_paid'] + $payment_amount;
            $new_status = ($new_amount_paid >= ($bill['total_amount'] - 0.001)) ? 'Paid' : 'Partially Paid';
            $stmt_update_bill = $conn->prepare("UPDATE scs_supplier_bills SET amount_paid = ?, status = ? WHERE id = ?");
            $stmt_update_bill->bind_param("dsi", $new_amount_paid, $new_status, $bill_id);
            $stmt_update_bill->execute();

            // Step 3: Create the journal entry for the payment
            $je_description = "Payment for supplier bill #" . $bill['bill_number'];
            // Account IDs: 7 = Accounts Payable, from your CoA
            $debits = [ ['account_id' => 7, 'amount' => $payment_amount] ]; // Debit Accounts Payable to reduce liability
            $credits = [ ['account_id' => $payment_account_id, 'amount' => $payment_amount] ]; // Credit Cash/Bank to reduce asset
            create_journal_entry($conn, $payment_date, $je_description, $debits, $credits, 'Bill Payment', $payment_id);

            $conn->commit();
            log_activity('BILL_PAID', "Recorded payment of {$payment_amount} for bill {$bill['bill_number']}", $conn);
            header("Location: accounts_payable.php?success=payment_recorded");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error recording payment: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Data fetching for the form dropdown
$cash_asset_accounts = $conn->query("SELECT id, account_name FROM scs_chart_of_accounts WHERE account_type = 'Asset' AND (account_name LIKE '%Cash%' OR account_name LIKE '%Bank%')");

?>

<title><?php echo $page_title; ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800"><?php echo $page_title; ?></h2>
    <a href="accounts_payable.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Payables
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-8">
    <div class="md:col-span-2">
        <div class="glass-card p-6">
             <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Details</h3>
             <?php if (!empty($message)): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'error' ? 'bg-red-100/80 text-red-800' : 'bg-green-100/80 text-green-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <form action="pay_bill.php?id=<?php echo $bill_id; ?>" method="POST" class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700">Amount to Pay</label>
                        <input type="number" step="0.01" name="amount" id="amount" value="<?php echo number_format($balance_due, 2, '.', ''); ?>" class="form-input mt-1 block w-full" required>
                    </div>
                     <div>
                        <label for="payment_date" class="block text-sm font-medium text-gray-700">Payment Date</label>
                        <input type="date" name="payment_date" id="payment_date" value="<?php echo date('Y-m-d'); ?>" class="form-input mt-1 block w-full" required>
                    </div>
                </div>
                 <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="payment_method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="form-input mt-1 block w-full" required>
                            <option>Bank Transfer</option>
                            <option>Cash</option>
                            <option>Check</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div>
                        <label for="payment_account_id" class="block text-sm font-medium text-gray-700">Payment From Account</label>
                        <select name="payment_account_id" id="payment_account_id" class="form-input mt-1 block w-full" required>
                            <option value="">Select account...</option>
                            <?php while($account = $cash_asset_accounts->fetch_assoc()): ?>
                                <option value="<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['account_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes (e.g., Transaction ID, Check #)</label>
                    <textarea name="notes" id="notes" rows="4" class="form-input mt-1 block w-full"></textarea>
                </div>
                <div class="flex justify-end pt-4">
                    <button type="submit" class="inline-flex justify-center py-2 px-6 rounded-md text-white bg-green-600 hover:bg-green-700">
                        Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div>
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Bill Summary</h3>
            <div class="space-y-3 text-sm">
                 <div class="flex justify-between"><span>Supplier:</span> <span class="font-semibold"><?php echo htmlspecialchars($bill['supplier_name']); ?></span></div>
                 <div class="flex justify-between"><span>Total Amount:</span> <span class="font-semibold"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($bill['total_amount'], 2)); ?></span></div>
                 <div class="flex justify-between"><span>Already Paid:</span> <span class="font-semibold"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($bill['amount_paid'], 2)); ?></span></div>
                 <div class="flex justify-between font-bold text-base pt-2 border-t mt-2 text-red-600"><span>Balance Due:</span> <span><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($balance_due, 2)); ?></span></div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>