<?php
// crm/tickets.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Support', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to view support tickets.</div>');
}

$page_title = "Support Tickets - BizManager";

$status_colors = [
    'Open' => 'bg-green-100 text-green-800', 'In Progress' => 'bg-blue-100 text-blue-800',
    'On Hold' => 'bg-yellow-100 text-yellow-800', 'Closed' => 'bg-gray-200 text-gray-800'
];
$priority_colors = [
    'Low' => 'bg-gray-200 text-gray-800', 'Medium' => 'bg-yellow-100 text-yellow-800',
    'High' => 'bg-red-100 text-red-800', 'Urgent' => 'bg-red-500 text-white'
];
$statuses = array_keys($status_colors);
$priorities = array_keys($priority_colors);

// --- FILTERING AND SEARCH LOGIC ---
$search_term = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_priority = $_GET['priority'] ?? '';
$filter_assigned = $_GET['assigned_to'] ?? '';

$sql = "
    SELECT t.*, cust.customer_name, u.full_name as assigned_user_name
    FROM scs_support_tickets t
    JOIN scs_customers cust ON t.customer_id = cust.id
    LEFT JOIN scs_users u ON t.assigned_to = u.id
";

$where_clauses = []; $params = []; $types = '';
if (!empty($search_term)) {
    $where_clauses[] = "(t.ticket_number LIKE ? OR t.subject LIKE ? OR cust.customer_name LIKE ?)";
    $search_param = "%" . $search_term . "%";
    $params[] = $search_param; $params[] = $search_param; $params[] = $search_param;
    $types .= 'sss';
}
if (!empty($filter_status)) { $where_clauses[] = "t.status = ?"; $params[] = $filter_status; $types .= 's'; }
if (!empty($filter_priority)) { $where_clauses[] = "t.priority = ?"; $params[] = $filter_priority; $types .= 's'; }
if (!empty($filter_assigned)) {
    if ($filter_assigned === 'unassigned') { $where_clauses[] = "t.assigned_to IS NULL"; } 
    else { $where_clauses[] = "t.assigned_to = ?"; $params[] = $filter_assigned; $types .= 'i'; }
}
if (!empty($where_clauses)) { $sql .= " WHERE " . implode(" AND ", $where_clauses); }
$sql .= " ORDER BY t.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$tickets_result = $stmt->get_result();

$users_result = $conn->query("SELECT id, full_name FROM scs_users WHERE is_active = 1 ORDER BY full_name ASC");
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div><h2 class="text-2xl font-semibold text-gray-800">Support Tickets</h2><p class="text-gray-600 mt-1">Manage and track all customer support issues.</p></div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">&larr; Back to CRM</a>
        <?php if (check_permission('Support', 'create')): ?>
        <a href="ticket-form.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
            Create New Ticket
        </a>
        <?php endif; ?>
    </div>
</div>
<div class="glass-card p-4 mb-6">
    <form action="tickets.php" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <div class="md:col-span-2"><label for="search" class="block text-sm font-medium text-gray-700">Search</label><input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Ticket #, Subject, or Customer" class="form-input mt-1 block w-full"></div>
        <div><label for="status" class="block text-sm font-medium text-gray-700">Status</label><select name="status" id="status" class="form-input mt-1 block w-full"><option value="">All Statuses</option><?php foreach ($statuses as $status): ?><option value="<?php echo $status; ?>" <?php if ($filter_status == $status) echo 'selected'; ?>><?php echo $status; ?></option><?php endforeach; ?></select></div>
        <div><label for="priority" class="block text-sm font-medium text-gray-700">Priority</label><select name="priority" id="priority" class="form-input mt-1 block w-full"><option value="">All Priorities</option><?php foreach ($priorities as $priority): ?><option value="<?php echo $priority; ?>" <?php if ($filter_priority == $priority) echo 'selected'; ?>><?php echo $priority; ?></option><?php endforeach; ?></select></div>
        <div class="flex space-x-2">
            <button type="submit" class="w-full inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Filter</button>
            <a href="tickets.php" class="w-full inline-flex justify-center py-2 px-4 rounded-md text-gray-700 bg-gray-200 hover:bg-gray-300">Clear</a>
        </div>
    </form>
</div>
<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr><th scope="col" class="px-6 py-3">Ticket #</th><th scope="col" class="px-6 py-3">Subject</th><th scope="col" class="px-6 py-3">Customer</th><th scope="col" class="px-6 py-3">Status</th><th scope="col" class="px-6 py-3">Priority</th><th scope="col" class="px-6 py-3">Assigned To</th><th scope="col" class="px-6 py-3 text-right">Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($tickets_result->num_rows > 0): ?>
                    <?php while($row = $tickets_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4 font-bold text-indigo-600"><?php echo htmlspecialchars($row['ticket_number']); ?></td>
                        <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($row['subject']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                        <td class="px-6 py-4"><span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_colors[$row['status']] ?? 'bg-gray-100 text-gray-800'; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td class="px-6 py-4"><span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $priority_colors[$row['priority']] ?? 'bg-gray-100 text-gray-800'; ?>"><?php echo htmlspecialchars($row['priority']); ?></span></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($row['assigned_user_name'] ?? 'Unassigned'); ?></td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <a href="ticket-details.php?id=<?php echo $row['id']; ?>" class="font-medium text-green-600 hover:underline">View</a>
                            <?php if (check_permission('Support', 'edit')): ?>
                                <a href="ticket-form.php?id=<?php echo $row['id']; ?>" class="font-medium text-indigo-600 hover:underline">Edit</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="px-6 py-4 text-center text-gray-500">No support tickets found matching your criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>