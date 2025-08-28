<?php
// crm/ticket-details.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Support', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to view this page.</div>');
}

$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ticket_id === 0) {
    die('<div class="glass-card p-8 text-center">Invalid Ticket ID provided.</div>');
}

// --- DATA FETCHING ---
$ticket_stmt = $conn->prepare("
    SELECT 
        t.*,
        c.customer_name,
        ct.contact_name,
        u_creator.full_name as creator_name,
        u_assignee.full_name as assignee_name
    FROM scs_support_tickets t
    JOIN scs_customers c ON t.customer_id = c.id
    LEFT JOIN scs_contacts ct ON t.contact_id = ct.id
    LEFT JOIN scs_users u_creator ON t.created_by = u_creator.id
    LEFT JOIN scs_users u_assignee ON t.assigned_to = u_assignee.id
    WHERE t.id = ?
");
$ticket_stmt->bind_param("i", $ticket_id);
$ticket_stmt->execute();
$ticket = $ticket_stmt->get_result()->fetch_assoc();
$ticket_stmt->close();

if (!$ticket) {
    die('<div class="glass-card p-8 text-center">Support ticket not found.</div>');
}

$page_title = "Ticket: " . htmlspecialchars($ticket['subject']);

$status_colors = [
    'Open' => 'bg-green-100 text-green-800', 'In Progress' => 'bg-blue-100 text-blue-800',
    'On Hold' => 'bg-yellow-100 text-yellow-800', 'Closed' => 'bg-gray-200 text-gray-800'
];
$priority_colors = [
    'Low' => 'bg-gray-200 text-gray-800', 'Medium' => 'bg-yellow-100 text-yellow-800',
    'High' => 'bg-red-100 text-red-800', 'Urgent' => 'bg-red-500 text-white'
];
?>

<title><?php echo $page_title; ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Ticket <span class="text-indigo-600"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span></h2>
        <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($ticket['subject']); ?></p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-2">
        <a href="tickets.php" class="inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">&larr; Back to Ticket List</a>
        <?php if (check_permission('Support', 'edit')): ?>
            <a href="ticket-form.php?id=<?php echo $ticket_id; ?>" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700">Edit Ticket</a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2 space-y-6">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200/50 pb-3 mb-4">Initial Request</h3>
            <div class="prose max-w-none text-gray-700">
                <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
            </div>
        </div>
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Replies & Notes</h3>
            <div class="text-center text-gray-500 p-8 border-2 border-dashed border-gray-300/50 rounded-lg">
                Functionality to add replies and notes will be built next.
            </div>
        </div>
    </div>

    <div>
        <div class="glass-card p-6 space-y-4">
             <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200/50 pb-3 mb-4">Details</h3>
             <div><p class="text-sm text-gray-500">Customer</p><p class="font-semibold text-gray-800"><?php echo htmlspecialchars($ticket['customer_name']); ?></p></div>
             <div><p class="text-sm text-gray-500">Contact Person</p><p class="font-semibold text-gray-800"><?php echo htmlspecialchars($ticket['contact_name'] ?? 'N/A'); ?></p></div>
             <div><p class="text-sm text-gray-500">Status</p><p><span class="px-3 py-1 text-sm font-semibold rounded-full <?php echo $status_colors[$ticket['status']] ?? ''; ?>"><?php echo htmlspecialchars($ticket['status']); ?></span></p></div>
             <div><p class="text-sm text-gray-500">Priority</p><p><span class="px-3 py-1 text-sm font-semibold rounded-full <?php echo $priority_colors[$ticket['priority']] ?? ''; ?>"><?php echo htmlspecialchars($ticket['priority']); ?></span></p></div>
             <div><p class="text-sm text-gray-500">Assigned To</p><p class="font-semibold text-gray-800"><?php echo htmlspecialchars($ticket['assignee_name'] ?? 'Unassigned'); ?></p></div>
             <div><p class="text-sm text-gray-500">Created By</p><p class="font-semibold text-gray-800"><?php echo htmlspecialchars($ticket['creator_name'] ?? 'N/A'); ?></p></div>
             <div><p class="text-sm text-gray-500">Date Created</p><p class="font-semibold text-gray-800"><?php echo date($app_config['date_format'] . ' h:i A', strtotime($ticket['created_at'])); ?></p></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>