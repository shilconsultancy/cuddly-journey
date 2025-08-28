<?php
// procurement/receive-po.php

// STEP 1: Include the core config and start the session first.
require_once __DIR__ . '/../config.php';

// --- SECURITY CHECK ---
if (!check_permission('Procurement', 'create')) {
    // We can't include the header here, so we die with a simple message.
    die('Access Denied.');
}

$po_id = $_GET['po_id'] ?? 0;
if (!$po_id) {
    die("No Purchase Order ID provided.");
}

// Initialize variables
$message = '';
$message_type = '';

// STEP 2: Perform ALL form processing and potential redirects BEFORE any HTML is sent.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['receive_stock'])) {
    // We need to fetch the PO details again inside the POST block to process it.
    $stmt_po_check = $conn->prepare("SELECT location_id, po_number FROM scs_purchase_orders WHERE id = ?");
    $stmt_po_check->bind_param("i", $po_id);
    $stmt_po_check->execute();
    $po_for_processing = $stmt_po_check->get_result()->fetch_assoc();
    $stmt_po_check->close();

    $received_products = $_POST['products'] ?? [];
    $location_id = $po_for_processing['location_id'];
    
    if (empty($received_products)) {
        $message = "No products were marked as received.";
        $message_type = 'error';
    } else {
        $conn->begin_transaction();
        try {
            $created_by = $_SESSION['user_id'];
            
            $stmt_inventory_update = $conn->prepare("
                INSERT INTO scs_inventory (product_id, location_id, quantity, last_updated_by) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity + ?, last_updated_by = ?
            ");

            $total_items_received = 0;
            foreach ($received_products as $product_id => $details) {
                $quantity_received = (int)$details['qty'];
                if ($quantity_received > 0) {
                    $stmt_inventory_update->bind_param("iiiiii", $product_id, $location_id, $quantity_received, $created_by, $quantity_received, $created_by);
                    $stmt_inventory_update->execute();
                    $total_items_received += $quantity_received;
                }
            }
            
            // Update PO status to 'Completed'
            $stmt_po_status = $conn->prepare("UPDATE scs_purchase_orders SET status = 'Completed' WHERE id = ?");
            $stmt_po_status->bind_param("i", $po_id);
            $stmt_po_status->execute();

            log_activity('PO_RECEIVED', "Received " . $total_items_received . " items against PO #" . htmlspecialchars($po_for_processing['po_number']), $conn);

            $conn->commit();
            
            // This redirect will now work because no HTML has been sent.
            header("Location: po-details.php?id=" . $po_id . "&success=received");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error receiving stock: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// STEP 3: Now that all logic is done, include the header to start outputting the page.
require_once __DIR__ . '/../templates/header.php';

$page_title = "Receive Stock from PO - BizManager";

// --- DATA FETCHING for displaying the page ---
$stmt_po = $conn->prepare("
    SELECT po.*, s.supplier_name, l.location_name
    FROM scs_purchase_orders po
    JOIN scs_suppliers s ON po.supplier_id = s.id
    JOIN scs_locations l ON po.location_id = l.id
    WHERE po.id = ? AND po.status = 'Sent'
");
$stmt_po->bind_param("i", $po_id);
$stmt_po->execute();
$po = $stmt_po->get_result()->fetch_assoc();
$stmt_po->close();

if (!$po) {
    die("Purchase Order not found or is not in 'Sent' status.");
}

$items_stmt = $conn->prepare("
    SELECT poi.quantity, poi.unit_price, p.id as product_id, p.product_name, p.sku
    FROM scs_purchase_order_items poi
    JOIN scs_products p ON poi.product_id = p.id
    WHERE poi.po_id = ?
");
$items_stmt->bind_param("i", $po_id);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Page Header -->
<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Receive Stock Against PO #<?php echo htmlspecialchars($po['po_number']); ?></h2>
        <p class="text-gray-600 mt-1">Confirm quantities received from <span class="font-semibold"><?php echo htmlspecialchars($po['supplier_name']); ?></span> for delivery to <span class="font-semibold"><?php echo htmlspecialchars($po['location_name']); ?></span>.</p>
    </div>
    <a href="po-details.php?id=<?php echo $po_id; ?>" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to PO Details
    </a>
</div>

<div class="glass-card p-8 max-w-4xl mx-auto">
    
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="receive-po.php?po_id=<?php echo $po_id; ?>" method="POST">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="text-xs text-gray-800 uppercase bg-gray-100">
                    <tr>
                        <th scope="col" class="px-6 py-3">Product</th>
                        <th scope="col" class="px-6 py-3 text-center">Quantity Ordered</th>
                        <th scope="col" class="px-6 py-3 text-center w-48">Quantity Received</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $item): ?>
                    <tr class="bg-white border-b">
                        <td class="px-6 py-4">
                            <p class="font-semibold"><?php echo htmlspecialchars($item['product_name']); ?></p>
                            <p class="text-xs text-gray-500">SKU: <?php echo htmlspecialchars($item['sku']); ?></p>
                        </td>
                        <td class="px-6 py-4 text-center font-semibold text-lg">
                            <?php echo htmlspecialchars($item['quantity']); ?>
                        </td>
                        <td class="px-6 py-4">
                            <input type="number" name="products[<?php echo $item['product_id']; ?>][qty]" 
                                   class="form-input w-full p-2 rounded-md text-center" 
                                   value="<?php echo htmlspecialchars($item['quantity']); ?>" min="0" required>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end pt-6 mt-6 border-t border-gray-200/50">
            <button type="submit" name="receive_stock" class="w-full md:w-auto inline-flex justify-center py-3 px-6 rounded-md text-white bg-green-600 hover:bg-green-700" onclick="return confirm('Are you sure you want to receive these quantities? This will update your inventory and complete the PO.');">
                Confirm & Receive Stock
            </button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>