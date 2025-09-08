<?php
// hr/leave.php

require_once __DIR__ . '/../templates/header.php';

// --- SECURITY CHECK ---
if (!check_permission('HR', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Leave Management - BizManager";
$message = '';
$message_type = '';
$current_user_id = $_SESSION['user_id'];
$is_manager = check_permission('HR', 'edit'); // Use 'edit' permission to determine if user is a manager

// --- FORM PROCESSING: ADD NEW LEAVE REQUEST ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_leave'])) {
    $leave_type = trim($_POST['leave_type']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = trim($_POST['reason']);

    if (empty($leave_type) || empty($start_date) || empty($end_date)) {
        $message = "Leave Type, Start Date, and End Date are required.";
        $message_type = 'error';
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $message = "End Date cannot be before the Start Date.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO scs_leave_requests (user_id, leave_type, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $current_user_id, $leave_type, $start_date, $end_date, $reason);
        if ($stmt->execute()) {
            $message = "Your leave request has been submitted successfully.";
            $message_type = 'success';
            log_activity('LEAVE_REQUEST_SUBMITTED', "User ID {$current_user_id} submitted a leave request.", $conn);
        } else {
            $message = "Error submitting request: " . $stmt->error;
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// --- FORM PROCESSING: APPROVE/REJECT LEAVE REQUEST ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status']) && $is_manager) {
    $request_id = (int)$_POST['request_id'];
    $new_status = $_POST['new_status'];
    $approver_id = $current_user_id;

    if ($new_status === 'Approved' || $new_status === 'Rejected') {
        $stmt = $conn->prepare("UPDATE scs_leave_requests SET status = ?, approved_by = ? WHERE id = ?");
        $stmt->bind_param("sii", $new_status, $approver_id, $request_id);
        if ($stmt->execute()) {
            $message = "Leave request status updated successfully.";
            $message_type = 'success';
             log_activity('LEAVE_REQUEST_UPDATED', "Leave request ID {$request_id} was {$new_status}.", $conn);
        } else {
             $message = "Error updating status: " . $stmt->error;
             $message_type = 'error';
        }
        $stmt->close();
    }
}


// --- DATA FETCHING ---
$sql = "
    SELECT 
        lr.id, lr.leave_type, lr.start_date, lr.end_date, lr.status, lr.reason,
        u.full_name as employee_name,
        approver.full_name as approver_name
    FROM scs_leave_requests lr
    JOIN scs_users u ON lr.user_id = u.id
    LEFT JOIN scs_users approver ON lr.approved_by = approver.id
";
// If user is not a manager, only show their own requests
if (!$is_manager) {
    $sql .= " WHERE lr.user_id = " . $current_user_id;
}
$sql .= " ORDER BY lr.created_at DESC";
$leave_requests_result = $conn->query($sql);

$status_colors = [
    'Pending' => 'bg-yellow-100 text-yellow-800',
    'Approved' => 'bg-green-100 text-green-800',
    'Rejected' => 'bg-red-100 text-red-800'
];

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Leave Management</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to HR
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-1">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Request Leave</h3>
             <?php if (!empty($message) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_leave'])): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <form action="leave.php" method="POST" class="space-y-4">
                <div>
                    <label for="leave_type" class="block text-sm font-medium text-gray-700">Leave Type</label>
                    <select name="leave_type" id="leave_type" class="form-input mt-1 block w-full p-2" required>
                        <option value="Vacation">Vacation</option>
                        <option value="Sick Leave">Sick Leave</option>
                        <option value="Personal">Personal</option>
                        <option value="Unpaid">Unpaid</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                        <input type="date" name="start_date" id="start_date" class="form-input mt-1 block w-full p-2" required>
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                        <input type="date" name="end_date" id="end_date" class="form-input mt-1 block w-full p-2" required>
                    </div>
                </div>
                <div>
                    <label for="reason" class="block text-sm font-medium text-gray-700">Reason (Optional)</label>
                    <textarea name="reason" id="reason" rows="3" class="form-input mt-1 block w-full p-2"></textarea>
                </div>
                <div class="flex justify-end pt-2">
                    <button type="submit" name="submit_leave" class="w-full inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Leave Requests History</h3>
             <?php if (!empty($message) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                        <tr>
                            <?php if ($is_manager): ?><th class="px-6 py-3">Employee</th><?php endif; ?>
                            <th class="px-6 py-3">Type</th>
                            <th class="px-6 py-3">Dates</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($leave_requests_result->num_rows > 0): ?>
                            <?php while($req = $leave_requests_result->fetch_assoc()): ?>
                            <tr class="bg-white/50 border-b border-gray-200/50">
                                <?php if ($is_manager): ?><td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($req['employee_name']); ?></td><?php endif; ?>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($req['leave_type']); ?></td>
                                <td class="px-6 py-4"><?php echo date('d M, Y', strtotime($req['start_date'])) . ' - ' . date('d M, Y', strtotime($req['end_date'])); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_colors[$req['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo htmlspecialchars($req['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <?php if ($is_manager && $req['status'] === 'Pending'): ?>
                                        <form action="leave.php" method="POST" class="inline-flex space-x-2">
                                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                            <button type="submit" name="new_status" value="Approved" class="font-medium text-green-600 hover:underline text-xs">Approve</button>
                                            <button type="submit" name="new_status" value="Rejected" class="font-medium text-red-600 hover:underline text-xs">Reject</button>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-500 italic">No actions</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                             <tr><td colspan="<?php echo $is_manager ? '5' : '4'; ?>" class="px-6 py-4 text-center text-gray-500">No leave requests found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>