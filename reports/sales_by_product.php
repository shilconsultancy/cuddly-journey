<?php
// reports/sales_by_product.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Reports', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Sales by Product Report - BizManager";

// --- FILTERING & DATA FETCHING ---
$product_filter = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$sql = "
    SELECT 
        p.id as product_id,
        p.product_name,
        p.sku,
        SUM(ii.quantity) as total_quantity_sold,
        SUM(ii.line_total) as total_revenue
    FROM scs_invoice_items ii
    JOIN scs_products p ON ii.product_id = p.id
    JOIN scs_invoices i ON ii.invoice_id = i.id
    WHERE i.status IN ('Paid', 'Partially Paid', 'Sent', 'Overdue')
";

$params = [];
$types = '';

if ($product_filter > 0) {
    $sql .= " AND p.id = ?";
    $params[] = $product_filter;
    $types .= 'i';
}
if (!empty($start_date)) {
    $sql .= " AND i.invoice_date >= ?";
    $params[] = $start_date;
    $types .= 's';
}
if (!empty($end_date)) {
    $sql .= " AND i.invoice_date <= ?";
    $params[] = $end_date;
    $types .= 's';
}

$sql .= " GROUP BY p.id ORDER BY total_revenue DESC, p.product_name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$report_result = $stmt->get_result();

$products_result = $conn->query("SELECT id, product_name, sku FROM scs_products ORDER BY product_name ASC");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Sales by Product Report</h2>
        <p class="text-gray-600 mt-1">Analyze sales volume and revenue for each product.</p>
    </div>
    <a href="sales_reports.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Sales Reports
    </a>
</div>

<div class="glass-card p-4 mb-6">
    <form action="sales_by_product.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
             <label for="product_id" class="block text-sm font-medium text-gray-700">Product</label>
             <select name="product_id" id="product_id" class="form-input mt-1 block w-full">
                <option value="0">All Products</option>
                <?php while($product = $products_result->fetch_assoc()): ?>
                    <option value="<?php echo $product['id']; ?>" <?php if($product_filter == $product['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($product['product_name'] . ' (' . $product['sku'] . ')'); ?>
                    </option>
                <?php endwhile; ?>
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
                    <th class="px-6 py-3">Product</th>
                    <th class="px-6 py-3">SKU</th>
                    <th class="px-6 py-3 text-center">Units Sold</th>
                    <th class="px-6 py-3 text-right">Total Revenue</th>
                    <th class="px-6 py-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($report_result->num_rows > 0): ?>
                    <?php while($row = $report_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($row['product_name']); ?></td>
                        <td class="px-6 py-4 font-mono"><?php echo htmlspecialchars($row['sku']); ?></td>
                        <td class="px-6 py-4 text-center font-bold text-lg"><?php echo $row['total_quantity_sold']; ?></td>
                        <td class="px-6 py-4 text-right font-mono font-semibold text-green-700">
                            <?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($row['total_revenue'], 2)); ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <a href="../products/product-details.php?id=<?php echo $row['product_id']; ?>" class="font-medium text-indigo-600 hover:underline">
                                View Product
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No sales data found for the selected criteria.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<?php
require_once __DIR__ . '/../templates/footer.php';
?>