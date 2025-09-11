<?php
// api/get_employee_contract_details.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!check_permission('HR', 'view')) {
    echo json_encode(['success' => false, 'message' => 'Permission Denied']);
    exit();
}

$user_id = $_GET['user_id'] ?? 0;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Missing User ID.']);
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        u.full_name,
        ed.address,
        jc.contract_title,
        jc.start_date,
        jc.job_type,
        jc.salary,
        l.location_name,
        mgr.full_name as manager_name
    FROM scs_users u
    LEFT JOIN scs_job_contracts jc ON u.id = jc.user_id
    LEFT JOIN scs_employee_details ed ON u.id = ed.user_id
    LEFT JOIN scs_locations l ON u.location_id = l.id
    LEFT JOIN scs_users mgr ON l.manager_id = mgr.id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$details = $result->fetch_assoc();
$stmt->close();

if ($details) {
    echo json_encode(['success' => true, 'data' => $details]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not find contract details for the selected employee.']);
}

$conn->close();
?>