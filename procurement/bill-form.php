<?php
// procurement/bill-form.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Procurement', 'create')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Enter Supplier Bill - BizManager";
$message = '';
$message_type = '';

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    try {
        $supplier_id = $_POST['supplier_id'];
        $po_id = !empty($_POST['po_id']) ? $_POST['po_id'] : NULL;
        $bill_number = trim($_POST['bill_number']);
        $bill_date = $_POST['bill_date'];
        $due_date = $_POST['due_date'];
        $total_amount = (float)$_POST['total_amount'];
        $created_by = $_SESSION['user_id'];

        if (empty($supplier_id) || empty($bill_number) || empty($bill_date) || empty($due_date) || $total_amount <= 0) {
            throw new Exception("Please fill all required fields.");
        }

        // 1. Insert the bill record
        $stmt = $conn->prepare("INSERT INTO scs_supplier_bills (supplier_id, po_id, bill_number, bill_date, due_date, total_amount, status, created_by) VALUES (?, ?, ?, ?, ?, ?, 'Unpaid', ?)");
        $stmt->bind_param("iisssdi", $supplier_id, $po_id, $bill_number, $bill_date, $due_date, $total_amount, $created_by);
        $stmt->execute();
        $bill_id = $conn->insert_id;

        // 2. Create the corresponding journal entry
        $je_description = "Record supplier bill #" . $bill_number;
        
        // **FIXED LOGIC**: If linked to a PO, it's an inventory cost. Otherwise, it's a general purchase expense.
        // Assumes Account IDs from our setup: 4 = Inventory Asset, 7 = Accounts Payable, 8 = Purchases
        if ($po_id) {
            $debit_account_id = 4; // Debit Inventory Asset
        } else {
            $debit_account_id = 8; // Debit Purchases (Expense)
        }
        
        $debits = [
            ['account_id' => $debit_account_id, 'amount' => $total_amount]
        ];
        $credits = [
            ['account_id' => 7, 'amount' => $total_amount] // Credit Accounts Payable
        ];
        create_journal_entry($conn, $bill_date, $je_description, $debits, $credits, 'Supplier Bill', $bill_id);
        
        $conn->commit();
        log_activity('SUPPLIER_BILL_CREATED', "Created supplier bill: " . $bill_number, $conn);
        header("Location: bills.php?success=created");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error creating bill: " . $e->getMessage();
        $message_type = 'error';
    }
}


// --- DATA FETCHING ---
$suppliers_result = $conn->query("SELECT id, supplier_name FROM scs_suppliers ORDER BY supplier_name ASC");
// Fetch only SENT or COMPLETED purchase orders that haven't been billed yet
$po_result = $conn->query("
    SELECT po.id, po.po_number, po.total_amount 
    FROM scs_purchase_orders po
    LEFT JOIN scs_supplier_bills b ON po.id = b.po_id
    WHERE po.status IN ('Sent', 'Completed') AND b.id IS NULL
");
$purchase_orders = $po_result->fetch_all(MYSQLI_ASSOC);

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Enter New Supplier Bill</h2>
    <a href="bills.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Bill List
    </a>
</div>

<div class="glass-card p-8 max-w-2xl mx-auto">
    <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'error' ? 'bg-red-100/80 text-red-800' : 'bg-green-100/80 text-green-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="bill-form.php" method="POST" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="supplier_id" class="block text-sm font-medium text-gray-700">Supplier</label>
                <select name="supplier_id" id="supplier_id" class="form-input mt-1 block w-full rounded-md p-3" required>
                    <option value="">Select a supplier...</option>
                    <?php while($supplier = $suppliers_result->fetch_assoc()): ?>
                        <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['supplier_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label for="po_id" class="block text-sm font-medium text-gray-700">Link to PO (Optional)</label>
                <select name="po_id" id="po_id" class="form-input mt-1 block w-full rounded-md p-3">
                    <option value="">None</option>
                    <?php foreach($purchase_orders as $po): ?>
                         <option value="<?php echo $po['id']; ?>" data-amount="<?php echo $po['total_amount']; ?>"><?php echo htmlspecialchars($po['po_number']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div>
            <label for="bill_number" class="block text-sm font-medium text-gray-700">Supplier's Bill/Invoice #</label>
            <input type="text" name="bill_number" id="bill_number" class="form-input mt-1 block w-full rounded-md p-3" required>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="bill_date" class="block text-sm font-medium text-gray-700">Bill Date</label>
                <input type="date" name="bill_date" id="bill_date" value="<?php echo date('Y-m-d'); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
            <div>
                <label for="due_date" class="block text-sm font-medium text-gray-700">Due Date</label>
                <input type="date" name="due_date" id="due_date" class="form-input mt-1 block w-full rounded-md p-3" required>
            </div>
        </div>
        <div>
            <label for="total_amount" class="block text-sm font-medium text-gray-700">Total Amount</label>
            <input type="number" step="0.01" name="total_amount" id="total_amount" class="form-input mt-1 block w-full rounded-md p-3" required>
        </div>
        <div class="flex justify-end pt-6 border-t border-gray-200/50">
            <button type="submit" class="w-full md:w-auto inline-flex justify-center py-3 px-6 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                Save Bill
            </button>
        </div>
    </form>
</div>
<script>
document.getElementById('po_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const amount = selectedOption.dataset.amount;
    if (amount) {
        document.getElementById('total_amount').value = parseFloat(amount).toFixed(2);
    }
});
</script>
<?php
require_once __DIR__ . '/../templates/footer.php';
?>