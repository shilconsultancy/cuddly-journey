<?php
// accounts/delete_account.php

require_once __DIR__ . '/../config.php';

if (!check_permission('Accounts', 'delete')) {
    die('Permission Denied.');
}

$account_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($account_id > 0) {
    // Check for dependencies: an account cannot be deleted if it's used in journal entries.
    $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM scs_journal_entry_items WHERE account_id = ?");
    $stmt_check->bind_param("i", $account_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($result['count'] > 0) {
        // If the account is in use, redirect back with an error message.
        header("Location: chart_of_accounts.php?error=in_use");
        exit();
    } else {
        // No dependencies, so we can proceed with deletion.
        $stmt_delete = $conn->prepare("DELETE FROM scs_chart_of_accounts WHERE id = ?");
        $stmt_delete->bind_param("i", $account_id);
        
        if ($stmt_delete->execute()) {
            log_activity('ACCOUNT_DELETED', "Deleted account with ID: " . $account_id, $conn);
            header("Location: chart_of_accounts.php?success=deleted");
            exit();
        } else {
            header("Location: chart_of_accounts.php?error=delete_failed");
            exit();
        }
        $stmt_delete->close();
    }
} else {
    // If no ID is provided, just redirect back.
    header("Location: chart_of_accounts.php");
    exit();
}

$conn->close();
?>