<?php
// accounts/delete_journal_entry.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (!check_permission('Accounts', 'delete')) {
    die('Permission denied.');
}

$entry_id = $_GET['id'] ?? 0;

if ($entry_id > 0) {
    // Check if it's a manual entry
    $check_stmt = $conn->prepare("SELECT source_document FROM scs_journal_entries WHERE id = ?");
    $check_stmt->bind_param("i", $entry_id);
    $check_stmt->execute();
    $source = $check_stmt->get_result()->fetch_assoc()['source_document'];
    $check_stmt->close();

    if ($source === 'Manual Entry' || !$source) {
        $conn->begin_transaction();
        try {
            // Delete items first
            $delete_items_stmt = $conn->prepare("DELETE FROM scs_journal_entry_items WHERE journal_entry_id = ?");
            $delete_items_stmt->bind_param("i", $entry_id);
            $delete_items_stmt->execute();
            $delete_items_stmt->close();

            // Delete the main entry
            $delete_entry_stmt = $conn->prepare("DELETE FROM scs_journal_entries WHERE id = ?");
            $delete_entry_stmt->bind_param("i", $entry_id);
            $delete_entry_stmt->execute();
            $delete_entry_stmt->close();

            $conn->commit();
            log_activity('JOURNAL_DELETED', "Deleted manual journal entry ID: " . $entry_id, $conn);
            header("Location: journal_entries.php?success=deleted");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            header("Location: journal_entries.php?error=" . urlencode($e->getMessage()));
            exit();
        }
    } else {
        header("Location: journal_entries.php?error=cannot_delete_system_entry");
        exit();
    }
} else {
    header("Location: journal_entries.php");
    exit();
}
?>