<?php
// api/update-opportunity-stage.php

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || !check_permission('CRM', 'edit')) {
    echo json_encode(['success' => false, 'message' => 'Permission Denied.']);
    exit();
}

// Get the raw POST data
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

$opportunity_id = $data['opportunity_id'] ?? 0;
$new_stage = $data['new_stage'] ?? '';

// Validate the stage to ensure it's one of the allowed values
$allowed_stages = ['Qualification', 'Needs Analysis', 'Proposal Sent', 'Negotiation', 'Closed Won', 'Closed Lost'];

if ($opportunity_id > 0 && in_array($new_stage, $allowed_stages)) {
    try {
        $stmt = $conn->prepare("UPDATE scs_opportunities SET stage = ? WHERE id = ?");
        $stmt->bind_param("si", $new_stage, $opportunity_id);
        
        if ($stmt->execute()) {
            // Fetch opportunity name for logging
            $opp_name_res = $conn->query("SELECT opportunity_name FROM scs_opportunities WHERE id = $opportunity_id")->fetch_assoc();
            $opp_name = $opp_name_res['opportunity_name'] ?? 'Unknown Opportunity';

            log_activity('PIPELINE_STAGE_CHANGED', "Moved opportunity '" . htmlspecialchars($opp_name) . "' to stage '" . htmlspecialchars($new_stage) . "'.", $conn);
            echo json_encode(['success' => true, 'message' => 'Stage updated successfully.']);
        } else {
            throw new Exception("Failed to update stage.");
        }
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
}

$conn->close();
?>