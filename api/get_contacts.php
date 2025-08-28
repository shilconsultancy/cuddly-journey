<?php
// api/get_contacts.php

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$customer_id = $_GET['customer_id'] ?? 0;

if (!$customer_id) {
    echo json_encode(['success' => false, 'message' => 'Missing customer ID.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, contact_name FROM scs_contacts WHERE customer_id = ? ORDER BY contact_name ASC");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$contacts = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode(['success' => true, 'contacts' => $contacts]);
$stmt->close();
$conn->close();
?>