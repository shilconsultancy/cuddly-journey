<?php
// api/update-session.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// --- SECURITY CHECK ---
// Only allow Super Admins (1) and Administrators (2) to perform this action
$user_role_id = $_SESSION['user_role_id'] ?? 0;
if (!in_array($user_role_id, [1, 2])) {
    echo json_encode(['success' => false, 'message' => 'Permission Denied.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$session_id = $_POST['session_id'] ?? 0;
$opening_balance = $_POST['opening_balance'] ?? null;
$closing_balance = $_POST['closing_balance'] ?? null;

if ($session_id === 0 || $opening_balance === null || $closing_balance === null) {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
    exit();
}

$conn->begin_transaction();
try {
    // 1. Fetch the total cash sales for this session to recalculate the expected balance
    $sales_stmt = $conn->prepare("
        SELECT COALESCE(SUM(i.total_amount), 0) as cash_sales
        FROM scs_pos_sales ps_sales 
        JOIN scs_invoices i ON ps_sales.invoice_id = i.id
        WHERE ps_sales.pos_session_id = ? AND ps_sales.payment_method = 'Cash'
    ");
    $sales_stmt->bind_param("i", $session_id);
    $sales_stmt->execute();
    $sales_result = $sales_stmt->get_result()->fetch_assoc();
    $cash_sales = $sales_result['cash_sales'] ?? 0;

    // 2. Recalculate the expected balance
    $expected_balance = (float)$opening_balance + (float)$cash_sales;

    // 3. Update the session record in the database
    $stmt = $conn->prepare("UPDATE scs_pos_sessions SET opening_balance = ?, closing_balance = ?, expected_balance = ? WHERE id = ?");
    $stmt->bind_param("dddi", $opening_balance, $closing_balance, $expected_balance, $session_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Database update failed: " . $stmt->error);
    }
    
    // 4. Log the activity
    $log_description = "Updated financial data for POS Session #" . $session_id . ". Opening: " . $opening_balance . ", Closing: " . $closing_balance;
    log_activity('POS_SESSION_EDITED', $log_description, $conn);
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Session updated successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
}

$conn->close();
?>