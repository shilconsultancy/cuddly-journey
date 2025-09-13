<?php
// api/get_employee_contract_details.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($user_id === 0) {
    echo json_encode(['success' => false, 'message' => 'No user ID provided.']);
    exit();
}

// Updated query to fetch all required details including the full salary breakdown
$stmt = $conn->prepare("
    SELECT 
        u.full_name,
        ed.address,
        ed.father_name,
        ed.department,
        ed.hire_date,
        ed.job_title,
        ed.basic_salary,
        ed.house_rent_allowance,
        ed.medical_allowance,
        ed.transport_allowance,
        ed.other_allowances,
        ed.gross_salary,
        l.location_name,
        mgr.full_name as manager_name,
        jc.contract_title,
        jc.job_type,
        jc.start_date
    FROM scs_users u
    LEFT JOIN scs_employee_details ed ON u.id = ed.user_id
    LEFT JOIN scs_job_contracts jc ON u.id = jc.user_id
    LEFT JOIN scs_locations l ON u.location_id = l.id
    LEFT JOIN scs_users mgr ON ed.reporting_manager_id = mgr.id
    WHERE u.id = ?
");

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error]);
    exit();
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false, 'message' => 'No contract or employee details found for this user.']);
}

$stmt->close();
$conn->close();
?>