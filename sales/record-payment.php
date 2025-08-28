<?php
// sales/record-payment.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (!check_permission('Sales', 'create')) { // Create permission to record a payment
    die('<div class="glass-card p-8 text-center">You do not have permission to record payments.</div>');
}

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($invoice_id === 0) {
    die('<div class="glass-card p-8 text-center">Invalid Invoice ID provided.</div>');
}

$message = '';
$message_type = '';

// Fetch the invoice to get details like balance due
$invoice_stmt = $conn->prepare("SELECT * FROM scs_invoices WHERE id = ?");
$invoice_stmt->bind_param("i", $invoice_id);
$invoice_stmt->execute();
$invoice = $invoice_stmt->get_result()->fetch_assoc();
$invoice_stmt->close();

if (!$invoice) {
    die('<div class="glass-card p-8 text-center">Invoice not found.</div>');
}

$balance_due = $invoice['total_amount'] - $invoice['amount_paid'];
$page_title = "Record Payment for " . htmlspecialchars($invoice['invoice_number']);


// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $payment_amount = (float)$_POST['amount'];
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];
    $notes = $_POST['notes'];
    $recorded_by = $_SESSION['user_id'];

    if ($payment_amount <= 0) {
        $message = "Payment amount must be greater than zero.";
        $message_type = 'error';
    } elseif ($payment_amount > $balance_due + 0.001) { // Add a small tolerance for floating point issues
        $message = "Payment amount cannot be greater than the balance due.";
        $message_type = 'error';
    } else {
        $conn->begin_transaction();
        try {
            // Step 1: Insert the payment record
            $stmt_payment = $conn->prepare("INSERT INTO scs_invoice_payments (invoice_id, payment_date, amount, payment_method, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_payment->bind_param("isdssi", $invoice_id, $payment_date, $payment_amount, $payment_method, $notes, $recorded_by);
            $stmt_payment->execute();

            // Step 2: Update the amount_paid on the invoice
            $new_amount_paid = $invoice['amount_paid'] + $payment_amount;
            $stmt_update_invoice = $conn->prepare("UPDATE scs_invoices SET amount_paid = ? WHERE id = ?");
            $stmt_update_invoice->bind_param("di", $new_amount_paid, $invoice_id);
            $stmt_update_invoice->execute();

            // Step 3: Update the status of the invoice
            $new_status = 'Partially Paid';
            // Use a small tolerance for floating point comparison
            if ($new_amount_paid >= ($invoice['total_amount'] - 0.001)) {
                $new_status = 'Paid';
            }
            $stmt_status = $conn->prepare("UPDATE scs_invoices SET status = ? WHERE id = ?");
            $stmt_status->bind_param("si", $new_status, $invoice_id);
            $stmt_status->execute();
            
            $conn->commit();
            log_activity('PAYMENT_RECORDED', "Recorded payment of {$payment_amount} for invoice {$invoice['invoice_number']}", $conn);
            header("Location: invoice-details.php?id=" . $invoice_id . "&success=payment_recorded");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error recording payment: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
?>

<title><?php echo $page_title; ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800"><?php echo $page_title; ?></h2>
    <a href="invoice-details.php?id=<?php echo $invoice_id; ?>" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Invoice
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
            <form action="record-payment.php?id=<?php echo $invoice_id; ?>" method="POST" class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700">Amount</label>
                        <input type="number" step="0.01" name="amount" id="amount" value="<?php echo number_format($balance_due, 2, '.', ''); ?>" class="form-input mt-1 block w-full" required>
                    </div>
                     <div>
                        <label for="payment_date" class="block text-sm font-medium text-gray-700">Payment Date</label>
                        <input type="date" name="payment_date" id="payment_date" value="<?php echo date('Y-m-d'); ?>" class="form-input mt-1 block w-full" required>
                    </div>
                </div>
                 <div>
                    <label for="payment_method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                    <select name="payment_method" id="payment_method" class="form-input mt-1 block w-full" required>
                        <option>Bank Transfer</option>
                        <option>Cash</option>
                        <option>Credit Card</option>
                        <option>Check</option>
                        <option>Other</option>
                    </select>
                </div>
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes (e.g., Transaction ID)</label>
                    <textarea name="notes" id="notes" rows="4" class="form-input mt-1 block w-full"></textarea>
                </div>
                <div class="flex justify-end pt-4">
                    <button type="submit" class="inline-flex justify-center py-2 px-6 rounded-md text-white bg-green-600 hover:bg-green-700">
                        Save Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div>
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Invoice Summary</h3>
            <div class="space-y-3 text-sm">
                 <div class="flex justify-between"><span>Total Amount:</span> <span class="font-semibold"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($invoice['total_amount'], 2)); ?></span></div>
                 <div class="flex justify-between"><span>Already Paid:</span> <span class="font-semibold"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($invoice['amount_paid'], 2)); ?></span></div>
                 <div class="flex justify-between font-bold text-base pt-2 border-t mt-2"><span>Balance Due:</span> <span><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($balance_due, 2)); ?></span></div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>