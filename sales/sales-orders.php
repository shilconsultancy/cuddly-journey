<?php
// sales/sales-orders.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Sales', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to view sales orders.</div>');
}

$page_title = "Sales Orders - BizManager";

// --- Color mapping for order statuses ---
$status_colors = [
    'Draft' => 'bg-gray-200 text-gray-800',
    'Confirmed' => 'bg-blue-100 text-blue-800',
    'Processing' => 'bg-yellow-100 text-yellow-800',
    'Shipped' => 'bg-indigo-100 text-indigo-800',
    'Completed' => 'bg-green-100 text-green-800',
    'Cancelled' => 'bg-red-100 text-red-800'
];
$statuses = array_keys($status_colors);

// --- START: FILTERING AND SEARCH LOGIC ---
$search_term = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Data scope variables
$has_global_scope = ($_SESSION['data_scope'] ?? 'Local') === 'Global';
$user_location_id = $_SESSION['location_id'] ?? null;


$sql = "
    SELECT 
        so.id,
        so.order_number,
        so.order_date,
        so.status,
        so.total_amount,
        cust.customer_name
    FROM 
        scs_sales_orders so
    JOIN 
        scs_customers cust ON so.customer_id = cust.id
";

$where_clauses = [];
$params = [];
$types = '';

// Apply location-based filtering for users with 'Local' scope
if (!$has_global_scope && $user_location_id) {
    $where_clauses[] = "so.location_id = ?";
    $params[] = $user_location_id;
    $types .= 'i';
}

if (!empty($search_term)) {
    $where_clauses[] = "(so.order_number LIKE ? OR cust.customer_name LIKE ?)";
    $search_param = "%" . $search_term . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if (!empty($filter_status)) {
    $where_clauses[] = "so.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if (!empty($start_date)) {
    $where_clauses[] = "so.order_date >= ?";
    $params[] = $start_date;
    $types .= 's';
}

if (!empty($end_date)) {
    $where_clauses[] = "so.order_date <= ?";
    $params[] = $end_date;
    $types .= 's';
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY so.order_date DESC, so.id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$orders_result = $stmt->get_result();
// --- END: FILTERING AND SEARCH LOGIC ---

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Sales Orders</h2>
        <p class="text-gray-600 mt-1">View and manage all customer sales orders.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to Sales
        </a>
        <?php if (check_permission('Sales', 'create')): ?>
        <a href="sales-order-form.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Create New Order
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="glass-card p-4 mb-6">
    <form action="sales-orders.php" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <div class="md:col-span-2">
            <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
            <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Order # or Customer Name" class="form-input mt-1 block w-full">
        </div>
        <div>
            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
            <select name="status" id="status" class="form-input mt-1 block w-full">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?php echo $status; ?>" <?php if ($filter_status == $status) echo 'selected'; ?>>
                        <?php echo $status; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                 <label for="start_date" class="block text-sm font-medium text-gray-700">From</label>
                 <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="form-input mt-1 block w-full">
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700">To</label>
                <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="form-input mt-1 block w-full">
            </div>
        </div>
        <div class="flex space-x-2">
            <button type="submit" class="w-full inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Filter</button>
            <a href="sales-orders.php" class="w-full inline-flex justify-center py-2 px-4 rounded-md text-gray-700 bg-gray-200 hover:bg-gray-300">Clear</a>
        </div>
    </form>
</div>


<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th scope="col" class="px-6 py-3">Order #</th>
                    <th scope="col" class="px-6 py-3">Customer</th>
                    <th scope="col" class="px-6 py-3">Date</th>
                    <th scope="col" class="px-6 py-3">Status</th>
                    <th scope="col" class="px-6 py-3 text-right">Total</th>
                    <th scope="col" class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($orders_result->num_rows > 0): ?>
                    <?php while($row = $orders_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4 font-bold text-indigo-600"><?php echo htmlspecialchars($row['order_number']); ?></td>
                        <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                        <td class="px-6 py-4"><?php echo date($app_config['date_format'], strtotime($row['order_date'])); ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_colors[$row['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right font-semibold"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($row['total_amount'], 2)); ?></td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <a href="sales-order-details.php?id=<?php echo $row['id']; ?>" class="font-medium text-green-600 hover:underline">View</a>
                            <?php if (check_permission('Sales', 'edit')): ?>
                                <a href="sales-order-form.php?id=<?php echo $row['id']; ?>" class="font-medium text-indigo-600 hover:underline">Edit</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No sales orders found matching your criteria.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>