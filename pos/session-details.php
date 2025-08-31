<?php
// pos/session-details.php

require_once __DIR__ . '/../templates/header.php';

// --- SECURITY CHECKS ---
if (!check_permission('POS', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$session_id = $_GET['id'] ?? 0;
if (!$session_id) {
    die('<div class="glass-card p-8 text-center">No session ID provided.</div>');
}

$user_role_id = $_SESSION['user_role_id'] ?? 0;
if (!in_array($user_role_id, [1, 2, 6])) { // Super Admin, Admin, Shop Manager
    die('<div class="glass-card p-8 text-center">You do not have permission to view session details.</div>');
}

// --- DATA FETCHING ---
// 1. Get main session details
$stmt_session = $conn->prepare("
    SELECT ps.*, u.full_name, l.location_name
    FROM scs_pos_sessions ps
    JOIN scs_users u ON ps.user_id = u.id
    JOIN scs_locations l ON ps.location_id = l.id
    WHERE ps.id = ?
");
$stmt_session->bind_param("i", $session_id);
$stmt_session->execute();
$session = $stmt_session->get_result()->fetch_assoc();
$stmt_session->close();

if (!$session) {
    die('<div class="glass-card p-8 text-center">Session not found.</div>');
}

// 2. Get payment summary
$stmt_payments = $conn->prepare("
    SELECT 
        ps.payment_method, 
        COUNT(ps.id) as transaction_count, 
        SUM(i.total_amount) as total_amount
    FROM scs_pos_sales ps
    JOIN scs_invoices i ON ps.invoice_id = i.id
    WHERE ps.pos_session_id = ?
    GROUP BY ps.payment_method
");
$stmt_payments->bind_param("i", $session_id);
$stmt_payments->execute();
$payments_summary = $stmt_payments->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_payments->close();


// 3. Get item sales summary
$stmt_items = $conn->prepare("
    SELECT p.product_name, p.sku, SUM(ii.quantity) as total_quantity, SUM(ii.line_total) as total_value
    FROM scs_invoice_items ii
    JOIN scs_invoices i ON ii.invoice_id = i.id
    JOIN scs_pos_sales ps ON i.id = ps.invoice_id
    JOIN scs_products p ON ii.product_id = p.id
    WHERE ps.pos_session_id = ?
    GROUP BY ii.product_id
    ORDER BY total_quantity DESC
");
$stmt_items->bind_param("i", $session_id);
$stmt_items->execute();
$items_summary = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_items->close();


$page_title = "Details for POS Session #" . $session['id'];
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">POS Session Details <span class="text-indigo-600">#<?php echo $session['id']; ?></span></h2>
    <a href="session-history.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Session History
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Sales by Product</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                        <tr>
                            <th class="px-4 py-2">Product</th>
                            <th class="px-4 py-2 text-center">Quantity Sold</th>
                            <th class="px-4 py-2 text-right">Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items_summary as $item): ?>
                        <tr class="border-b border-gray-200/50">
                            <td class="px-4 py-3">
                                <div class="font-semibold"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="text-xs text-gray-500">SKU: <?php echo htmlspecialchars($item['sku']); ?></div>
                            </td>
                            <td class="px-4 py-3 text-center font-bold"><?php echo $item['total_quantity']; ?></td>
                            <td class="px-4 py-3 text-right font-semibold"><?php echo number_format($item['total_value'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="glass-card p-6">
             <h3 class="text-lg font-semibold text-gray-800 mb-4">Session Summary</h3>
             <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span>User:</span> <span class="font-semibold"><?php echo htmlspecialchars($session['full_name']); ?></span></div>
                <div class="flex justify-between"><span>Location:</span> <span class="font-semibold"><?php echo htmlspecialchars($session['location_name']); ?></span></div>
                <div class="flex justify-between"><span>Opened:</span> <span class="font-semibold"><?php echo date('d-m-Y H:i', strtotime($session['start_time'])); ?></span></div>
                <div class="flex justify-between"><span>Closed:</span> <span class="font-semibold"><?php echo $session['end_time'] ? date('d-m-Y H:i', strtotime($session['end_time'])) : 'Still Active'; ?></span></div>
             </div>
        </div>
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Payments Received</h3>
             <div class="space-y-3">
                <?php foreach($payments_summary as $payment): ?>
                <div class="flex justify-between items-center text-sm">
                    <span><?php echo htmlspecialchars($payment['payment_method']); ?> (<?php echo $payment['transaction_count']; ?>)</span>
                    <span class="font-semibold"><?php echo number_format($payment['total_amount'], 2); ?></span>
                </div>
                <?php endforeach; ?>
             </div>
        </div>
    </div>
</div>


<?php require_once __DIR__ . '/../templates/footer.php'; ?>