<?php
// users/toggle-status.php

// Include the global config file to get the database connection and start the session.
require_once __DIR__ . '/../config.php';

// --- SECURITY CHECK ---
// Ensure the user is logged in and has permission to manage users.
if (!isset($_SESSION['user_id']) || !check_permission('Users', 'edit')) {
    // Redirect to dashboard if not authorized.
    header("Location: ../dashboard.php");
    exit();
}

// Check if a user ID is provided in the URL.
if (isset($_GET['id'])) {
    $user_id_to_toggle = $_GET['id'];

    // To prevent a user from disabling their own account, especially the last admin.
    if ($user_id_to_toggle == $_SESSION['user_id']) {
        // Redirect back with an error message (optional).
        header("Location: index.php?error=self_disable");
        exit();
    }

    // --- First, get the current status of the user ---
    $stmt_check = $conn->prepare("SELECT is_active, full_name FROM scs_users WHERE id = ?");
    $stmt_check->bind_param("i", $user_id_to_toggle);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        // Determine the new status (if current is 1, new is 0, and vice-versa).
        $new_status = $user['is_active'] ? 0 : 1;
        $status_text = $new_status ? "enabled" : "disabled";

        // --- Update the user's status ---
        $stmt_update = $conn->prepare("UPDATE scs_users SET is_active = ? WHERE id = ?");
        $stmt_update->bind_param("ii", $new_status, $user_id_to_toggle);
        
        if ($stmt_update->execute()) {
            // Log the activity
            $log_description = "User account for '" . htmlspecialchars($user['full_name']) . "' was " . $status_text . ".";
            log_activity('USER_STATUS_CHANGED', $log_description, $conn);
        }
        $stmt_update->close();
    }
    $stmt_check->close();
}

// Redirect back to the user list page.
header("Location: index.php");
exit();

?>