<?php
// procurement/purchase-orders.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Procurement', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Purchase Orders - BizManager";

$po_result = $conn->query("
    SELECT 
        po.id, po.po_number, po.order_date, po.total_amount, po.status,
        s.supplier_name
    FROM scs_purchase_orders po
    JOIN scs_suppliers s ON po.supplier_id = s.id
    ORDER BY po.order_date DESC, po.id DESC
");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Page Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Purchase Orders</h2>
        <p class="text-gray-600 mt-1">Track and manage all purchase orders sent to suppliers.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to Procurement
        </a>
        <?php if (check_permission('Procurement', 'create')): ?>
        <a href="add-po.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            New Purchase Order
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th scope="col" class="px-6 py-3">PO Number</th>
                    <th scope="col" class="px-6 py-3">Supplier</th>
                    <th scope="col" class="px-6 py-3">Order Date</th>
                    <th scope="col" class="px-6 py-3">Status</th>
                    <th scope="col" class="px-6 py-3 text-right">Total Amount</th>
                    <th scope="col" class="px-6 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($po_result->num_rows > 0): ?>
                    <?php while($po = $po_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50 hover:bg-gray-50/50">
                        <td class="px-6 py-4 font-semibold text-gray-800"><?php echo htmlspecialchars($po['po_number']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                        <td class="px-6 py-4"><?php echo date($app_config['date_format'], strtotime($po['order_date'])); ?></td>
                        <td class="px-6 py-4">
                            <?php
                                $status = htmlspecialchars($po['status']);
                                $color_class = 'bg-gray-100 text-gray-800'; // Default for Draft
                                if ($status == 'Sent') {
                                    $color_class = 'bg-blue-100 text-blue-800';
                                } elseif ($status == 'Completed') {
                                    $color_class = 'bg-green-100 text-green-800';
                                } elseif ($status == 'Cancelled') {
                                    $color_class = 'bg-red-100 text-red-800';
                                }
                            ?>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $color_class; ?>">
                                <?php echo $status; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right font-semibold"><?php echo htmlspecialchars($app_config['currency_symbol'] . ' ' . number_format($po['total_amount'], 2)); ?></td>
                        <td class="px-6 py-4 text-center">
                            <a href="po-details.php?id=<?php echo $po['id']; ?>" class="font-medium text-indigo-600 hover:underline">View Details</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No purchase orders found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>