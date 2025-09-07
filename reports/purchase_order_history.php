<?php
// reports/purchase_order_history.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Reports', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Purchase Order History Report - BizManager";

// --- FILTERING & DATA FETCHING ---
$supplier_filter = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$sql = "
    SELECT 
        po.id,
        po.po_number,
        po.order_date,
        po.total_amount,
        po.status,
        s.supplier_name
    FROM scs_purchase_orders po
    JOIN scs_suppliers s ON po.supplier_id = s.id
    WHERE 1=1
";

$params = [];
$types = '';

if ($supplier_filter > 0) {
    $sql .= " AND po.supplier_id = ?";
    $params[] = $supplier_filter;
    $types .= 'i';
}
if (!empty($status_filter)) {
    $sql .= " AND po.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}
if (!empty($start_date)) {
    $sql .= " AND po.order_date >= ?";
    $params[] = $start_date;
    $types .= 's';
}
if (!empty($end_date)) {
    $sql .= " AND po.order_date <= ?";
    $params[] = $end_date;
    $types .= 's';
}

$sql .= " ORDER BY po.order_date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$po_result = $stmt->get_result();

$suppliers_result = $conn->query("SELECT id, supplier_name FROM scs_suppliers ORDER BY supplier_name ASC");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Purchase Order History</h2>
        <p class="text-gray-600 mt-1">Review all purchase orders with advanced filters.</p>
    </div>
    <a href="procurement_reports.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Procurement Reports
    </a>
</div>

<div class="glass-card p-4 mb-6">
    <form action="purchase_order_history.php" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <div>
             <label for="supplier_id" class="block text-sm font-medium text-gray-700">Supplier</label>
             <select name="supplier_id" id="supplier_id" class="form-input mt-1 block w-full">
                <option value="0">All Suppliers</option>
                <?php while($supplier = $suppliers_result->fetch_assoc()): ?>
                    <option value="<?php echo $supplier['id']; ?>" <?php if($supplier_filter == $supplier['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                    </option>
                <?php endwhile; ?>
             </select>
        </div>
        <div>
             <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
             <select name="status" id="status" class="form-input mt-1 block w-full">
                <option value="">All Statuses</option>
                <?php $statuses = ['Draft', 'Sent', 'Completed', 'Cancelled']; ?>
                <?php foreach($statuses as $status): ?>
                    <option value="<?php echo $status; ?>" <?php if($status_filter == $status) echo 'selected'; ?>>
                        <?php echo $status; ?>
                    </option>
                <?php endforeach; ?>
             </select>
        </div>
        <div>
             <label for="start_date" class="block text-sm font-medium text-gray-700">From</label>
             <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="form-input mt-1 block w-full">
        </div>
        <div>
            <label for="end_date" class="block text-sm font-medium text-gray-700">To</label>
            <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="form-input mt-1 block w-full">
        </div>
        <div>
            <button type="submit" class="w-full inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Filter</button>
        </div>
    </form>
</div>

<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th class="px-6 py-3">PO Number</th>
                    <th class="px-6 py-3">Supplier</th>
                    <th class="px-6 py-3">Order Date</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3 text-right">Total Amount</th>
                    <th class="px-6 py-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($po_result->num_rows > 0): ?>
                    <?php while($row = $po_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4 font-semibold text-indigo-600"><a href="../procurement/po-details.php?id=<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['po_number']); ?></a></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                        <td class="px-6 py-4"><?php echo date($app_config['date_format'], strtotime($row['order_date'])); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($row['status']); ?></td>
                        <td class="px-6 py-4 text-right font-mono"><?php echo number_format($row['total_amount'], 2); ?></td>
                        <td class="px-6 py-4 text-center">
                            <a href="../procurement/po-details.php?id=<?php echo $row['id']; ?>" class="font-medium text-indigo-600 hover:underline">
                                View Details
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No purchase orders found for the selected criteria.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<?php
require_once __DIR__ . '/../templates/footer.php';
?>