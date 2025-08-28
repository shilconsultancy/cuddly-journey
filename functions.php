<?php
// functions.php

// Use PHPMailer classes
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

// Require the PHPMailer files
require __DIR__ . '/lib/PHPMailer/Exception.php';
require __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require __DIR__ . '/lib/PHPMailer/SMTP.php';

/**
 * Checks if the currently logged-in user has a specific permission.
 *
 * @param string $module_name The name of the module (e.g., 'Sales', 'Users').
 * @param string $action The action to check (e.g., 'view', 'create', 'edit', 'delete').
 * @return bool True if the user has permission, false otherwise.
 */
function check_permission($module_name, $action) {
    // Super Admins (role_id 1) always have permission.
    if (isset($_SESSION['user_role_id']) && $_SESSION['user_role_id'] == 1) {
        return true;
    }

    // Check if the permission is set in the session array.
    $permission_key = 'can_' . $action;
    if (isset($_SESSION['permissions'][$module_name][$permission_key]) && $_SESSION['permissions'][$module_name][$permission_key] == 1) {
        return true;
    }

    return false;
}


function send_email($to, $subject, $body, $app_config) {
    if (empty($app_config['email_notifications_enabled']) || $app_config['email_notifications_enabled'] != '1') {
        return true;
    }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $app_config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $app_config['smtp_user'];
        $mail->Password   = $app_config['smtp_pass'];
        $mail->SMTPSecure = $app_config['smtp_encryption'];
        $mail->Port       = $app_config['smtp_port'];
        $mail->setFrom($app_config['smtp_user'], $app_config['company_name']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function log_activity($action, $description, $conn) {
    $user_id = $_SESSION['user_id'] ?? null;
    $stmt = $conn->prepare("INSERT INTO scs_activity_logs (user_id, action, description) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $action, $description);
    $stmt->execute();
    $stmt->close();
}

/**
 * Fetches all company details from the scs_settings table.
 * @param mysqli $conn The database connection object.
 * @return array An associative array of settings (e.g., ['company_name' => 'BizManager Inc.']).
 */
function get_company_details($conn) {
    $settings = [];
    $result = $conn->query("SELECT setting_key, setting_value FROM scs_settings");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings;
}