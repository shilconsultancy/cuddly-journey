<?php
// api/get_reconciliation_data.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (!check_permission('Accounts', 'view')) {
    echo json_encode(['success' => false, 'message' => 'Permission Denied']);
    exit();
}

$account_id = $_GET['account_id'] ?? 0;
$statement_start_date = $_GET['statement_start_date'] ?? null;
$statement_date = $_GET['statement_date'] ?? null; // This is the end date
$statement_balance = $_GET['statement_balance'] ?? 0.00;
$reconciliation_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

if ((!$account_id || !$statement_date || !$statement_start_date) && !$reconciliation_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

$conn->begin_transaction();
try {
    if (!$reconciliation_id) {
        // This is a new reconciliation
        // Note: We need to add `statement_start_date` to the table in a future step. For now, it's just used for querying.
        $stmt = $conn->prepare("INSERT INTO scs_bank_reconciliations (account_id, statement_date, statement_balance, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isdi", $account_id, $statement_date, $statement_balance, $user_id);
        $stmt->execute();
        $reconciliation_id = $conn->insert_id;
    }

    // Fetch reconciliation details
    $recon_stmt = $conn->prepare("SELECT * FROM scs_bank_reconciliations WHERE id = ?");
    $recon_stmt->bind_param("i", $reconciliation_id);
    $recon_stmt->execute();
    $reconciliation = $recon_stmt->get_result()->fetch_assoc();

    // --- FIX: UPDATED QUERY WITH DATE RANGE ---
    $items_stmt = $conn->prepare("
        SELECT 
            jei.id, 
            jei.debit_amount, 
            jei.credit_amount,
            je.entry_date,
            je.description
        FROM scs_journal_entry_items jei
        JOIN scs_journal_entries je ON jei.journal_entry_id = je.id
        WHERE jei.account_id = ? 
        AND je.entry_date BETWEEN ? AND ?
        AND NOT EXISTS (
            SELECT 1 FROM scs_bank_statement_lines bsl 
            WHERE bsl.journal_item_id = jei.id
        )
        ORDER BY je.entry_date ASC
    ");
    // We use the start date from the GET parameter for new reconciliations, or from the DB for existing ones.
    $start_date_for_query = $statement_start_date ?: date('Y-m-d', strtotime($reconciliation['statement_date'] . ' -1 month +1 day'));
    $items_stmt->bind_param("iss", $reconciliation['account_id'], $start_date_for_query, $reconciliation['statement_date']);
    $items_stmt->execute();
    $unreconciled_items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'reconciliation' => $reconciliation,
        'unreconciled_items' => $unreconciled_items
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

$conn->close();
?>