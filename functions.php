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
 * This function now checks for custom user permissions first, then falls back to role-based permissions.
 *
 * @param string $module_name The name of the module (e.g., 'Sales', 'Users').
 * @param string $action The action to check (e.g., 'view', 'create', 'edit', 'delete').
 * @return bool True if the user has permission, false otherwise.
 */
function check_permission($module_name, $action) {
    // Super Admins (role_id 1) always have all permissions.
    if (isset($_SESSION['user_role_id']) && $_SESSION['user_role_id'] == 1) {
        return true;
    }

    $permission_key = 'can_' . $action;

    // 1. Check for a specific user override first.
    if (isset($_SESSION['custom_permissions'][$module_name][$permission_key])) {
        return $_SESSION['custom_permissions'][$module_name][$permission_key] == 1;
    }

    // 2. If no user-specific override, fall back to the role's default permissions.
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

/**
 * Creates a balanced two-sided journal entry.
 *
 * @param mysqli $conn The database connection object.
 * @param string $date The date of the entry (Y-m-d).
 * @param string $description A brief description of the transaction.
 * @param array $debits An array of debit entries, each ['account_id' => id, 'amount' => amount].
 * @param array $credits An array of credit entries, each ['account_id' => id, 'amount' => amount].
 * @param string|null $source_document The name of the source module (e.g., 'Invoice', 'Payment').
 * @param int|null $source_document_id The ID of the source document (e.g., invoice_id).
 * @param string|null $reference_number An optional reference number.
 * @return bool True on success, false on failure.
 * @throws Exception If debits do not equal credits.
 */
function create_journal_entry($conn, $date, $description, $debits, $credits, $source_document = null, $source_document_id = null, $reference_number = null) {
    $total_debits = array_sum(array_column($debits, 'amount'));
    $total_credits = array_sum(array_column($credits, 'amount'));

    if (abs($total_debits - $total_credits) > 0.001) {
        throw new Exception("Journal entry is unbalanced. Debits ($total_debits) do not equal Credits ($total_credits).");
    }

    $created_by = $_SESSION['user_id'];

    $stmt_journal = $conn->prepare("INSERT INTO scs_journal_entries (entry_date, reference_number, description, source_document, source_document_id, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_journal->bind_param("ssssii", $date, $reference_number, $description, $source_document, $source_document_id, $created_by);
    if (!$stmt_journal->execute()) {
        throw new Exception("Failed to create journal entry header: " . $stmt_journal->error);
    }
    $journal_entry_id = $conn->insert_id;
    $stmt_journal->close();

    $stmt_items = $conn->prepare("INSERT INTO scs_journal_entry_items (journal_entry_id, account_id, debit_amount, credit_amount) VALUES (?, ?, ?, ?)");
    foreach ($debits as $debit) {
        $credit_amount = 0.00;
        $stmt_items->bind_param("iidd", $journal_entry_id, $debit['account_id'], $debit['amount'], $credit_amount);
        if (!$stmt_items->execute()) {
            throw new Exception("Failed to insert debit item: " . $stmt_items->error);
        }
    }
    foreach ($credits as $credit) {
        $debit_amount = 0.00;
        $stmt_items->bind_param("iidd", $journal_entry_id, $credit['account_id'], $debit_amount, $credit['amount']);
        if (!$stmt_items->execute()) {
            throw new Exception("Failed to insert credit item: " . $stmt_items->error);
        }
    }
    $stmt_items->close();

    return true;
}