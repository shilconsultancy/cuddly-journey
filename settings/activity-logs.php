<?php
// settings/activity-logs.php

require_once __DIR__ . '/../templates/header.php';

$page_title = "Activity Logs - BizManager";

// --- DATA FETCHING for the logs ---
$logs_result = $conn->query("
    SELECT 
        al.*, 
        u.full_name 
    FROM 
        scs_activity_logs al
    LEFT JOIN 
        scs_users u ON al.user_id = u.id
    ORDER BY 
        al.timestamp DESC
    LIMIT 100 -- Limit to the last 100 entries for performance
");
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Page Header -->
<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Activity Logs</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Settings
    </a>
</div>

<div class="glass-card p-6">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                <tr>
                    <th scope="col" class="px-6 py-3">Date & Time</th>
                    <th scope="col" class="px-6 py-3">User</th>
                    <th scope="col" class="px-6 py-3">Action</th>
                    <th scope="col" class="px-6 py-3">Description</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs_result->num_rows > 0): ?>
                    <?php while($log = $logs_result->fetch_assoc()): ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td class="px-6 py-4">
                            <?php 
                            // Use the global date format from settings
                            $date_format = $app_config['date_format'] ?? 'd-m-Y';
                            echo date($date_format . " H:i:s", strtotime($log['timestamp'])); 
                            ?>
                        </td>
                        <td class="px-6 py-4 font-medium">
                            <?php echo htmlspecialchars($log['full_name'] ?? 'System'); ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs font-semibold text-indigo-800 bg-indigo-100 rounded-full">
                                <?php echo htmlspecialchars($log['action']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <?php echo htmlspecialchars($log['description']); ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr class="bg-white/50 border-b border-gray-200/50">
                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">No activity has been logged yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>