<?php
// api/save_reconciliation.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (!check_permission('Accounts', 'edit')) {
    echo json_encode(['success' => false, 'message' => 'Permission Denied']);
    exit();
}

$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

$reconciliation_id = $data['reconciliation_id'] ?? 0;
$matches = $data['matches'] ?? []; // Array of ['journal_item_id' => x, 'statement_line_id' => y]

if (empty($reconciliation_id) || empty($matches)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
    exit;
}

$conn->begin_transaction();
try {
    // Step 1: Update the bank statement lines to mark them as reconciled
    $stmt_update_line = $conn->prepare("UPDATE scs_bank_statement_lines SET is_reconciled = 1, journal_item_id = ? WHERE id = ? AND reconciliation_id = ?");
    foreach ($matches as $match) {
        $stmt_update_line->bind_param("iii", $match['journal_item_id'], $match['statement_line_id'], $reconciliation_id);
        $stmt_update_line->execute();
    }
    $stmt_update_line->close();

    // Step 2: Update the main reconciliation record status to 'Completed'
    $stmt_update_recon = $conn->prepare("UPDATE scs_bank_reconciliations SET status = 'Completed' WHERE id = ?");
    $stmt_update_recon->bind_param("i", $reconciliation_id);
    $stmt_update_recon->execute();
    $stmt_update_recon->close();

    log_activity('BANK_RECONCILIATION_COMPLETED', "Completed bank reconciliation ID: " . $reconciliation_id, $conn);
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Reconciliation completed successfully!']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>