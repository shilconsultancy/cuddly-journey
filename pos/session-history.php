<?php
// pos/session-history.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('POS', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to view session history.</div>');
}

$page_title = "POS Session History - BizManager";

// Data scope variables
$has_global_scope = ($_SESSION['data_scope'] ?? 'Local') === 'Global';
$user_location_id = $_SESSION['location_id'] ?? null;

$sql = "
    SELECT 
        ps.*,
        u.full_name,
        l.location_name
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
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to POS
    </a>
</div>

<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th scope="col" class="px-6 py-3">Session ID</th>
                    <th scope="col" class="px-6 py-3">User</th>
                    <th scope="col" class="px-6 py-3">Location</th>
                    <th scope="col" class="px-6 py-3">Start Time</th>
                    <th scope="col" class="px-6 py-3">End Time</th>
                    <th scope="col" class="px-6 py-3 text-right">Opening Balance</th>
                    <th scope="col" class="px-6 py-3 text-right">Closing Balance</th>
                    <th scope="col" class="px-6 py-3 text-right">Expected Balance</th>
                    <th scope="col" class="px-6 py-3">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($sessions_result->num_rows > 0): ?>
                    <?php while($session = $sessions_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4 font-bold"><?php echo $session['id']; ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($session['full_name']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($session['location_name']); ?></td>
                        <td class="px-6 py-4"><?php echo date('d-m-Y H:i:s', strtotime($session['start_time'])); ?></td>
                        <td class="px-6 py-4"><?php echo $session['end_time'] ? date('d-m-Y H:i:s', strtotime($session['end_time'])) : 'N/A'; ?></td>
                        <td class="px-6 py-4 text-right"><?php echo number_format($session['opening_balance'], 2); ?></td>
                        <td class="px-6 py-4 text-right"><?php echo number_format($session['closing_balance'], 2); ?></td>
                        <td class="px-6 py-4 text-right"><?php echo number_format($session['expected_balance'], 2); ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $session['status'] === 'Closed' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                <?php echo htmlspecialchars($session['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-6 py-4 text-center text-gray-500">No session history found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>