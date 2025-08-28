<?php
// procurement/update-po-status.php

require_once __DIR__ . '/../config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || !check_permission('Procurement', 'edit')) {
    header("Location: ../dashboard.php");
    exit();
}

// Ensure this is a POST request to prevent accidental changes
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $po_id = $_POST['po_id'] ?? 0;
    $new_status = $_POST['new_status'] ?? '';

    // Validate the provided status to ensure it's one of the allowed values
    $allowed_statuses = ['Draft', 'Sent', 'Completed', 'Cancelled'];

    if ($po_id > 0 && in_array($new_status, $allowed_statuses)) {
        
        // Fetch the PO number for logging before we update
        $stmt_fetch = $conn->prepare("SELECT po_number FROM scs_purchase_orders WHERE id = ?");
        $stmt_fetch->bind_param("i", $po_id);
        $stmt_fetch->execute();
        $po = $stmt_fetch->get_result()->fetch_assoc();
        $po_number = $po['po_number'] ?? 'Unknown PO';
        $stmt_fetch->close();

        // Update the status in the database
        $stmt_update = $conn->prepare("UPDATE scs_purchase_orders SET status = ? WHERE id = ?");
        $stmt_update->bind_param("si", $new_status, $po_id);

        if ($stmt_update->execute()) {
            // Log the activity
            $log_description = "Status for Purchase Order #" . htmlspecialchars($po_number) . " was changed to '" . htmlspecialchars($new_status) . "'.";
            log_activity('PO_STATUS_UPDATED', $log_description, $conn);
        }
        $stmt_update->close();
    }
}

// Redirect back to the details page for the PO we just updated
header("Location: po-details.php?id=" . (int)$po_id);
exit();
?>