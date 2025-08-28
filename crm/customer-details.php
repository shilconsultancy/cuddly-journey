<?php
// crm/customer-details.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('CRM', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$customer_id = $_GET['id'] ?? 0;
if (!$customer_id) {
    die("No customer ID provided.");
}

// --- DATA FETCHING ---
$stmt_cust = $conn->prepare("SELECT * FROM scs_customers WHERE id = ?");
$stmt_cust->bind_param("i", $customer_id);
$stmt_cust->execute();
$customer = $stmt_cust->get_result()->fetch_assoc();
$stmt_cust->close();

if (!$customer) { die("Customer not found."); }

$page_title = "Customer: " . htmlspecialchars($customer['customer_name']);

// Fetch contacts
$stmt_contacts = $conn->prepare("SELECT * FROM scs_contacts WHERE customer_id = ? ORDER BY contact_name ASC");
$stmt_contacts->bind_param("i", $customer_id);
$stmt_contacts->execute();
$contacts_result = $stmt_contacts->get_result();

// Fetch opportunities
$stmt_opp = $conn->prepare("SELECT o.*, u.full_name as assigned_user_name FROM scs_opportunities o LEFT JOIN scs_users u ON o.assigned_to = u.id WHERE o.customer_id = ? ORDER BY o.created_at DESC");
$stmt_opp->bind_param("i", $customer_id);
$stmt_opp->execute();
$opportunities_result = $stmt_opp->get_result();

// Fetch interactions
$stmt_interactions = $conn->prepare("SELECT i.*, u.full_name as creator_name, c.contact_name FROM scs_interactions i LEFT JOIN scs_users u ON i.created_by = u.id LEFT JOIN scs_contacts c ON i.contact_id = c.id WHERE i.customer_id = ? ORDER BY i.interaction_date DESC");
$stmt_interactions->bind_param("i", $customer_id);
$stmt_interactions->execute();
$interactions_result = $stmt_interactions->get_result();

// UPDATE: Fetch support tickets with assigned user's name
$stmt_tickets = $conn->prepare("
    SELECT t.*, u.full_name as assigned_user_name
    FROM scs_support_tickets t
    LEFT JOIN scs_users u ON t.assigned_to = u.id
    WHERE t.customer_id = ? 
    ORDER BY t.created_at DESC
");
$stmt_tickets->bind_param("i", $customer_id);
$stmt_tickets->execute();
$tickets_result = $stmt_tickets->get_result();

// Fetch combined sales history
$stmt_sales_history = $conn->prepare("
    (SELECT id, 'Quotation' as type, quote_number as doc_number, quote_date as doc_date, total_amount, status FROM scs_quotations WHERE customer_id = ?)
    UNION ALL
    (SELECT id, 'Sales Order' as type, order_number as doc_number, order_date as doc_date, total_amount, status FROM scs_sales_orders WHERE customer_id = ?)
    UNION ALL
    (SELECT id, 'Invoice' as type, invoice_number as doc_number, invoice_date as doc_date, total_amount, status FROM scs_invoices WHERE customer_id = ?)
    ORDER BY doc_date DESC
");
$stmt_sales_history->bind_param("iii", $customer_id, $customer_id, $customer_id);
$stmt_sales_history->execute();
$sales_history_result = $stmt_sales_history->get_result();

// --- Combined status colors for all document types ---
$status_colors = [
    'Qualification' => 'bg-gray-200 text-gray-800', 'Needs Analysis' => 'bg-yellow-100 text-yellow-800',
    'Proposal Sent' => 'bg-blue-100 text-blue-800', 'Negotiation' => 'bg-purple-100 text-purple-800',
    'Closed Won' => 'bg-green-100 text-green-800', 'Closed Lost' => 'bg-red-100 text-red-800',
    'Draft' => 'bg-gray-200 text-gray-800', 'Sent' => 'bg-blue-100 text-blue-800',
    'Accepted' => 'bg-green-100 text-green-800', 'Rejected' => 'bg-red-100 text-red-800',
    'Confirmed' => 'bg-blue-100 text-blue-800', 'Processing' => 'bg-yellow-100 text-yellow-800',
    'Shipped' => 'bg-indigo-100 text-indigo-800', 'Completed' => 'bg-green-100 text-green-800',
    'Cancelled' => 'bg-red-100 text-red-800', 'Partially Paid' => 'bg-yellow-100 text-yellow-800',
    'Paid' => 'bg-green-100 text-green-800', 'Overdue' => 'bg-red-100 text-red-800', 'Void' => 'bg-gray-500 text-white',
    'Open' => 'bg-green-100 text-green-800', 'In Progress' => 'bg-blue-100 text-blue-800',
    'On Hold' => 'bg-yellow-100 text-yellow-800', 'Closed' => 'bg-gray-200 text-gray-800'
];
$priority_colors = [
    'Low' => 'bg-gray-200 text-gray-800', 'Medium' => 'bg-yellow-100 text-yellow-800',
    'High' => 'bg-red-100 text-red-800', 'Urgent' => 'bg-red-500 text-white'
];
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div><h2 class="text-2xl font-semibold text-gray-800"><?php echo htmlspecialchars($customer['customer_name']); ?></h2><p class="text-gray-600 mt-1">Viewing the complete profile and history for this customer.</p></div>
    <a href="customers.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">&larr; Back to Customer List</a>
</div>

<div class="glass-card p-6">
    <div class="border-b border-gray-200/50">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button onclick="changeTab('details')" class="tab-button border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Details</button>
            <button onclick="changeTab('contacts')" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Contacts</button>
            <button onclick="changeTab('opportunities')" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Sales Pipeline</button>
            <button onclick="changeTab('sales-history')" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Sales History</button>
            <button onclick="changeTab('interactions')" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Interaction Log</button>
            <button onclick="changeTab('tickets')" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Support Tickets</button>
        </nav>
    </div>

    <div class="pt-6">
        <div id="details" class="tab-content"><h3 class="text-lg font-semibold text-gray-800 mb-4">Customer Information</h3><div class="grid grid-cols-1 md:grid-cols-2 gap-6"><div><p class="text-sm text-gray-500">Customer Name</p><p class="font-semibold"><?php echo htmlspecialchars($customer['customer_name']); ?></p></div><div><p class="text-sm text-gray-500">Customer Type</p><p class="font-semibold"><?php echo htmlspecialchars($customer['customer_type']); ?></p></div><div><p class="text-sm text-gray-500">Email</p><p class="font-semibold"><?php echo htmlspecialchars($customer['email'] ?? 'N/A'); ?></p></div><div><p class="text-sm text-gray-500">Phone</p><p class="font-semibold"><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></p></div><div class="md:col-span-2"><p class="text-sm text-gray-500">Address</p><p class="font-semibold"><?php echo nl2br(htmlspecialchars($customer['address'] ?? 'N/A')); ?></p></div></div></div>

        <div id="contacts" class="tab-content hidden"><h3 class="text-lg font-semibold text-gray-800 mb-4">Associated Contacts</h3><div class="overflow-x-auto"><table class="w-full text-sm text-left text-gray-700"><thead class="text-xs text-gray-800 uppercase bg-gray-50/50"><tr><th scope="col" class="px-6 py-3">Name</th><th scope="col" class="px-6 py-3">Job Title</th><th scope="col" class="px-6 py-3">Contact Info</th><th scope="col" class="px-6 py-3">Notes</th></tr></thead><tbody><?php if ($contacts_result && $contacts_result->num_rows > 0): mysqli_data_seek($contacts_result, 0); ?><?php while($contact = $contacts_result->fetch_assoc()): ?><tr class="bg-white/50 border-b border-gray-200/50"><td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($contact['contact_name']); ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($contact['job_title'] ?? 'N/A'); ?></td><td class="px-6 py-4"><div class="font-semibold"><?php echo htmlspecialchars($contact['email'] ?? ''); ?></div><div class="text-xs text-gray-500"><?php echo htmlspecialchars($contact['phone'] ?? ''); ?></div></td><td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($contact['notes'] ?? ''); ?></td></tr><?php endwhile; ?><?php else: ?><tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No contacts found for this customer.</td></tr><?php endif; ?></tbody></table></div></div>

        <div id="opportunities" class="tab-content hidden"><h3 class="text-lg font-semibold text-gray-800 mb-4">Sales Pipeline Opportunities</h3><div class="overflow-x-auto"><table class="w-full text-sm text-left text-gray-700"><thead class="text-xs text-gray-800 uppercase bg-gray-50/50"><tr><th scope="col" class="px-6 py-3">Opportunity</th><th scope="col" class="px-6 py-3">Stage</th><th scope="col" class="px-6 py-3 text-right">Value</th><th scope="col" class="px-6 py-3">Close Date</th><th scope="col" class="px-6 py-3">Assigned To</th></tr></thead><tbody><?php if ($opportunities_result && $opportunities_result->num_rows > 0): mysqli_data_seek($opportunities_result, 0); ?><?php while($opp = $opportunities_result->fetch_assoc()): ?><tr class="bg-white/50 border-b border-gray-200/50"><td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($opp['opportunity_name'] ?? ''); ?></td><td class="px-6 py-4"><span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_colors[$opp['stage']] ?? 'bg-gray-100 text-gray-800'; ?>"><?php echo htmlspecialchars($opp['stage'] ?? ''); ?></span></td><td class="px-6 py-4 text-right"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($opp['estimated_value'] ?? 0, 2)); ?></td><td class="px-6 py-4"><?php echo $opp['closing_date'] ? date($app_config['date_format'], strtotime($opp['closing_date'])) : 'N/A'; ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($opp['assigned_user_name'] ?? 'Unassigned'); ?></td></tr><?php endwhile; ?><?php else: ?><tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No opportunities found for this customer.</td></tr><?php endif; ?></tbody></table></div></div>

        <div id="sales-history" class="tab-content hidden"><h3 class="text-lg font-semibold text-gray-800 mb-4">Sales History</h3><div class="overflow-x-auto"><table class="w-full text-sm text-left text-gray-700"><thead class="text-xs text-gray-800 uppercase bg-gray-50/50"><tr><th scope="col" class="px-6 py-3">Type</th><th scope="col" class="px-6 py-3">Number</th><th scope="col" class="px-6 py-3">Date</th><th scope="col" class="px-6 py-3 text-right">Amount</th><th scope="col" class="px-6 py-3">Status</th></tr></thead><tbody><?php if ($sales_history_result && $sales_history_result->num_rows > 0): ?><?php while($doc = $sales_history_result->fetch_assoc()): ?><tr class="bg-white/50 border-b border-gray-200/50"><td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($doc['type']); ?></td><td class="px-6 py-4"><?php $link = '#'; if ($doc['type'] === 'Quotation') $link = "../sales/quotation-details.php?id=" . $doc['id']; if ($doc['type'] === 'Sales Order') $link = "../sales/sales-order-details.php?id=" . $doc['id']; if ($doc['type'] === 'Invoice') $link = "../sales/invoice-details.php?id=" . $doc['id']; ?><a href="<?php echo $link; ?>" class="text-indigo-600 hover:underline font-bold"><?php echo htmlspecialchars($doc['doc_number']); ?></a></td><td class="px-6 py-4"><?php echo date($app_config['date_format'], strtotime($doc['doc_date'])); ?></td><td class="px-6 py-4 text-right"><?php echo htmlspecialchars($app_config['currency_symbol'] . number_format($doc['total_amount'], 2)); ?></td><td class="px-6 py-4"><span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_colors[$doc['status']] ?? 'bg-gray-100 text-gray-800'; ?>"><?php echo htmlspecialchars($doc['status']); ?></span></td></tr><?php endwhile; ?><?php else: ?><tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No sales history found for this customer.</td></tr><?php endif; ?></tbody></table></div></div>

        <div id="interactions" class="tab-content hidden"><h3 class="text-lg font-semibold text-gray-800 mb-4">Interaction History</h3><div class="space-y-4"><?php if ($interactions_result && $interactions_result->num_rows > 0): mysqli_data_seek($interactions_result, 0); ?><?php while($row = $interactions_result->fetch_assoc()): ?><div class="bg-white/60 p-4 rounded-lg border border-gray-200/50"><div class="flex justify-between items-start"><div><p class="font-bold text-gray-800"><?php echo htmlspecialchars($row['interaction_type']); ?></p><?php if (!empty($row['contact_name'])): ?><p class="text-sm text-gray-600">with <?php echo htmlspecialchars($row['contact_name']); ?></p><?php endif; ?></div><span class="text-sm text-gray-600"><?php echo date($app_config['date_format'] . ' h:i A', strtotime($row['interaction_date'])); ?></span></div><p class="text-gray-700 my-3"><?php echo nl2br(htmlspecialchars($row['summary'] ?? '')); ?></p><div class="text-xs text-gray-500 pt-2 border-t border-gray-200/50"><span>Logged by: <?php echo htmlspecialchars($row['creator_name'] ?? 'System'); ?></span></div></div><?php endwhile; ?><?php else: ?><p class="text-center text-gray-500">No interactions have been logged for this customer.</p><?php endif; ?></div></div>

        <div id="tickets" class="tab-content hidden">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Support Tickets</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Ticket #</th>
                            <th scope="col" class="px-6 py-3">Subject</th>
                            <th scope="col" class="px-6 py-3">Status</th>
                            <th scope="col" class="px-6 py-3">Priority</th>
                            <th scope="col" class="px-6 py-3">Assigned To</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($tickets_result && $tickets_result->num_rows > 0): mysqli_data_seek($tickets_result, 0); ?>
                            <?php while($ticket = $tickets_result->fetch_assoc()): ?>
                            <tr class="bg-white/50 border-b border-gray-200/50">
                                <td class="px-6 py-4">
                                    <a href="ticket-details.php?id=<?php echo $ticket['id']; ?>" class="font-bold text-indigo-600 hover:underline">
                                        <?php echo htmlspecialchars($ticket['ticket_number'] ?? ''); ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($ticket['subject'] ?? ''); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_colors[$ticket['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo htmlspecialchars($ticket['status'] ?? ''); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $priority_colors[$ticket['priority']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo htmlspecialchars($ticket['priority'] ?? ''); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($ticket['assigned_user_name'] ?? 'Unassigned'); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No support tickets found for this customer.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
    </div>
</div>

<script>
function changeTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.add('hidden'));
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('border-indigo-500', 'text-indigo-600');
        button.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
    });
    document.getElementById(tabName).classList.remove('hidden');
    const activeButton = document.querySelector(`button[onclick="changeTab('${tabName}')"]`);
    activeButton.classList.add('border-indigo-500', 'text-indigo-600');
    activeButton.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>