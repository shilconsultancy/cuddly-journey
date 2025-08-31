<?php
// pos/session-history.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('POS', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to view session history.</div>');
}

// Security Check for role-based access
$user_role_id = $_SESSION['user_role_id'] ?? 0;
if (!in_array($user_role_id, [1, 2, 6])) { // Super Admin, Admin, Shop Manager
    die('<div class="glass-card p-8 text-center">You do not have permission to view session history.</div>');
}

// Check if the user has editing permissions (Super Admin or Administrator)
$can_edit_sessions = in_array($user_role_id, [1, 2]);

$page_title = "POS Session History - BizManager";

// Data scope variables
$has_global_scope = ($_SESSION['data_scope'] ?? 'Local') === 'Global';
$user_location_id = $_SESSION['location_id'] ?? null;

// UPDATED QUERY to include sales data
$sql = "
    SELECT 
        ps.*,
        u.full_name,
        l.location_name,
        (SELECT COALESCE(SUM(i.total_amount), 0) 
         FROM scs_pos_sales ps_sales 
         JOIN scs_invoices i ON ps_sales.invoice_id = i.id
         WHERE ps_sales.pos_session_id = ps.id) as total_sales,
        (SELECT COALESCE(SUM(i.total_amount), 0) 
         FROM scs_pos_sales ps_sales 
         JOIN scs_invoices i ON ps_sales.invoice_id = i.id
         WHERE ps_sales.pos_session_id = ps.id AND ps_sales.payment_method = 'Cash') as cash_sales
    FROM scs_pos_sessions ps
    JOIN scs_users u ON ps.user_id = u.id
    JOIN scs_locations l ON ps.location_id = l.id
";

if (!$has_global_scope && $user_location_id) {
    $sql .= " WHERE ps.location_id = " . (int)$user_location_id;
}

$sql .= " ORDER BY ps.start_time DESC";
$sessions_result = $conn->query($sql);
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">POS Session History</h2>
    <div class="flex space-x-2">
        <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to POS
        </a>
        <a href="../dashboard.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            Back to Dashboard
        </a>
    </div>
</div>

<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th class="px-4 py-3 w-1/4">Session Details</th>
                    <th class="px-4 py-3 text-right">Total Sales</th>
                    <th class="px-4 py-3 text-right">Cash Sales</th>
                    <th class="px-4 py-3 text-right">Other Payments</th>
                    <th class="px-4 py-3 text-right">Opening Balance</th>
                    <th class="px-4 py-3 text-right">Expected in Drawer</th>
                    <th class="px-4 py-3 text-right">Closing Balance</th>
                    <th class="px-4 py-3 text-right">Discrepancy</th>
                    <?php if ($can_edit_sessions): ?>
                        <th class="px-4 py-3 text-center">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($sessions_result->num_rows > 0): ?>
                    <?php while($session = $sessions_result->fetch_assoc()): 
                        $other_sales = $session['total_sales'] - $session['cash_sales'];
                        $discrepancy = $session['closing_balance'] - $session['expected_balance'];
                    ?>
                    <tr class="bg-white/50 border-b border-gray-200/50" id="session-row-<?php echo $session['id']; ?>">
                        <td class="px-4 py-4 align-top">
                            <div class="font-bold">Session #<?php echo $session['id']; ?> (<?php echo htmlspecialchars($session['status']); ?>)</div>
                            <div class="text-xs text-gray-600"><?php echo htmlspecialchars($session['full_name']); ?> @ <?php echo htmlspecialchars($session['location_name']); ?></div>
                            <div class="text-xs text-gray-500">
                                <?php echo date('d-m-Y H:i', strtotime($session['start_time'])); ?> - 
                                <?php echo $session['end_time'] ? date('H:i', strtotime($session['end_time'])) : 'Active'; ?>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-right align-top font-semibold"><?php echo number_format($session['total_sales'], 2); ?></td>
                        <td class="px-4 py-4 text-right align-top" data-cash-sales="<?php echo $session['cash_sales']; ?>"><?php echo number_format($session['cash_sales'], 2); ?></td>
                        <td class="px-4 py-4 text-right align-top"><?php echo number_format($other_sales, 2); ?></td>
                        <td class="px-4 py-4 text-right align-top">
                            <input type="number" step="0.01" value="<?php echo number_format($session['opening_balance'], 2, '.', ''); ?>" 
                                   class="form-input w-28 p-1 rounded-md text-right bg-gray-50/50 border-gray-300/50"
                                   oninput="calculateTotals(<?php echo $session['id']; ?>)"
                                   id="opening-<?php echo $session['id']; ?>"
                                   <?php if (!$can_edit_sessions || $session['status'] !== 'Closed') echo 'disabled'; ?>>
                        </td>
                        <td class="px-4 py-4 text-right align-top font-semibold" id="expected-<?php echo $session['id']; ?>">
                            <?php echo number_format($session['expected_balance'], 2); ?>
                        </td>
                        <td class="px-4 py-4 text-right align-top">
                             <input type="number" step="0.01" value="<?php echo number_format($session['closing_balance'], 2, '.', ''); ?>" 
                                   class="form-input w-28 p-1 rounded-md text-right bg-gray-50/50 border-gray-300/50"
                                   oninput="calculateTotals(<?php echo $session['id']; ?>)"
                                   id="closing-<?php echo $session['id']; ?>"
                                   <?php if (!$can_edit_sessions || $session['status'] !== 'Closed') echo 'disabled'; ?>>
                        </td>
                        <td class="px-4 py-4 text-right align-top font-bold <?php echo (abs($discrepancy) > 0.001) ? 'text-red-500' : 'text-green-600'; ?>" id="discrepancy-<?php echo $session['id']; ?>">
                            <?php echo number_format($discrepancy, 2); ?>
                        </td>
                        <td class="px-4 py-4 text-center align-top">
                            <?php if ($can_edit_sessions && $session['status'] === 'Closed'): ?>
                                <button onclick="saveSession(<?php echo $session['id']; ?>)" class="bg-indigo-600 text-white px-3 py-1 text-xs font-semibold rounded-lg hover:bg-indigo-700">
                                    Save
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $can_edit_sessions ? '9' : '8'; ?>" class="px-6 py-4 text-center text-gray-500">No session history found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function calculateTotals(sessionId) {
    const openingEl = document.getElementById(`opening-${sessionId}`);
    const closingEl = document.getElementById(`closing-${sessionId}`);
    const expectedEl = document.getElementById(`expected-${sessionId}`);
    const discrepancyEl = document.getElementById(`discrepancy-${sessionId}`);
    const row = document.getElementById(`session-row-${sessionId}`);
    const cashSalesCell = row.querySelector('td[data-cash-sales]');
    
    const cashSales = parseFloat(cashSalesCell.dataset.cashSales) || 0;
    const opening = parseFloat(openingEl.value) || 0;
    const closing = parseFloat(closingEl.value) || 0;

    const expected = opening + cashSales;
    const discrepancy = closing - expected;

    expectedEl.textContent = expected.toFixed(2);
    discrepancyEl.textContent = discrepancy.toFixed(2);

    // Update color based on discrepancy
    discrepancyEl.classList.remove('text-red-500', 'text-green-600');
    if (Math.abs(discrepancy) > 0.001) {
        discrepancyEl.classList.add('text-red-500');
    } else {
        discrepancyEl.classList.add('text-green-600');
    }
}

function saveSession(sessionId) {
    const opening = document.getElementById(`opening-${sessionId}`).value;
    const closing = document.getElementById(`closing-${sessionId}`).value;

    const formData = new FormData();
    formData.append('session_id', sessionId);
    formData.append('opening_balance', opening);
    formData.append('closing_balance', closing);

    fetch('../api/update-session.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Session updated successfully!');
            const row = document.getElementById(`session-row-${sessionId}`);
            row.style.transition = 'background-color 0.5s ease';
            row.style.backgroundColor = '#d1fae5';
            setTimeout(() => { row.style.backgroundColor = ''; }, 2000);
        } else {
            alert('Error updating session: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An unexpected error occurred.');
    });
}
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>