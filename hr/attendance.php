<?php
// hr/attendance.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('HR', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Attendance - BizManager";
$message = '';
$message_type = '';
$current_user_id = $_SESSION['user_id'];
$is_manager = check_permission('HR', 'edit');

// --- Check current user's attendance status for today ---
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');
$stmt_today = $conn->prepare("SELECT id, check_in_time, check_out_time FROM scs_attendance WHERE user_id = ? AND check_in_time BETWEEN ? AND ? ORDER BY id DESC LIMIT 1");
$stmt_today->bind_param("iss", $current_user_id, $today_start, $today_end);
$stmt_today->execute();
$today_attendance = $stmt_today->get_result()->fetch_assoc();
$stmt_today->close();

$can_check_in = !$today_attendance;
$can_check_out = $today_attendance && is_null($today_attendance['check_out_time']);

// --- FORM PROCESSING: Check-in / Check-out ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['check_in']) && $can_check_in) {
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("INSERT INTO scs_attendance (user_id, check_in_time) VALUES (?, ?)");
        $stmt->bind_param("is", $current_user_id, $now);
        if ($stmt->execute()) {
            $message = "Successfully checked in at " . date('h:i A');
            $message_type = 'success';
            header("Location: attendance.php"); // Refresh to update status
            exit();
        } else {
            $message = "Error checking in.";
            $message_type = 'error';
        }
    } elseif (isset($_POST['check_out']) && $can_check_out) {
        $now = date('Y-m-d H:i:s');
        $notes = trim($_POST['notes']);
        $stmt = $conn->prepare("UPDATE scs_attendance SET check_out_time = ?, notes = ? WHERE id = ?");
        $stmt->bind_param("ssi", $now, $notes, $today_attendance['id']);
        if ($stmt->execute()) {
            $message = "Successfully checked out at " . date('h:i A');
            $message_type = 'success';
            header("Location: attendance.php"); // Refresh to update status
            exit();
        } else {
            $message = "Error checking out.";
            $message_type = 'error';
        }
    }
}


// --- DATA FETCHING FOR LOGS ---
$log_sql = "
    SELECT a.*, u.full_name
    FROM scs_attendance a
    JOIN scs_users u ON a.user_id = u.id
";
if (!$is_manager) {
    $log_sql .= " WHERE a.user_id = " . $current_user_id;
}
$log_sql .= " ORDER BY a.check_in_time DESC LIMIT 100";
$attendance_log_result = $conn->query($log_sql);

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Attendance</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to HR
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-1">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Time Clock</h3>
            <div class="text-center">
                <p class="text-4xl font-bold text-gray-800" id="current-time"><?php echo date('h:i:s A'); ?></p>
                <p class="text-gray-600"><?php echo date('l, F j, Y'); ?></p>
            </div>
            
            <div class="mt-6">
                <form action="attendance.php" method="POST">
                    <?php if ($can_check_in): ?>
                        <button type="submit" name="check_in" class="w-full py-4 text-lg font-bold rounded-lg bg-green-500 text-white hover:bg-green-600">Check In</button>
                    <?php elseif ($can_check_out): ?>
                        <div class="text-center p-3 bg-green-100/80 rounded-lg">
                            Checked in at <?php echo date('h:i A', strtotime($today_attendance['check_in_time'])); ?>
                        </div>
                        <textarea name="notes" rows="2" class="form-input w-full mt-4 p-2" placeholder="Add a note for your checkout (optional)"></textarea>
                        <button type="submit" name="check_out" class="w-full mt-2 py-4 text-lg font-bold rounded-lg bg-red-500 text-white hover:bg-red-600">Check Out</button>
                    <?php else: ?>
                         <div class="text-center p-4 bg-gray-100/80 rounded-lg">
                            <p class="font-semibold">Attendance for today is complete.</p>
                            <p class="text-sm">In: <?php echo date('h:i A', strtotime($today_attendance['check_in_time'])); ?> | Out: <?php echo date('h:i A', strtotime($today_attendance['check_out_time'])); ?></p>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4"><?php echo $is_manager ? "Today's Attendance Log" : "My Recent Attendance"; ?></h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                        <tr>
                            <?php if ($is_manager): ?><th class="px-6 py-3">Employee</th><?php endif; ?>
                            <th class="px-6 py-3">Date</th>
                            <th class="px-6 py-3">Check In</th>
                            <th class="px-6 py-3">Check Out</th>
                            <th class="px-6 py-3">Total Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                         <?php while($log = $attendance_log_result->fetch_assoc()): 
                            $total_hours = 'N/A';
                            if ($log['check_out_time']) {
                                $check_in = new DateTime($log['check_in_time']);
                                $check_out = new DateTime($log['check_out_time']);
                                $interval = $check_in->diff($check_out);
                                $total_hours = $interval->format('%h h %i m');
                            }
                         ?>
                         <tr class="bg-white/50 border-b border-gray-200/50">
                            <?php if ($is_manager): ?><td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($log['full_name']); ?></td><?php endif; ?>
                            <td class="px-6 py-4"><?php echo date('d M, Y', strtotime($log['check_in_time'])); ?></td>
                            <td class="px-6 py-4 text-green-600 font-semibold"><?php echo date('h:i A', strtotime($log['check_in_time'])); ?></td>
                            <td class="px-6 py-4 text-red-600 font-semibold"><?php echo $log['check_out_time'] ? date('h:i A', strtotime($log['check_out_time'])) : '-'; ?></td>
                            <td class="px-6 py-4 font-bold"><?php echo $total_hours; ?></td>
                         </tr>
                         <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Live clock
setInterval(function() {
    const now = new Date();
    document.getElementById('current-time').innerText = now.toLocaleTimeString('en-US');
}, 1000);
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>