<?php
// api/get_stock.php

// This file acts as a small API endpoint to fetch stock levels for JavaScript.
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$product_id = $_GET['product_id'] ?? 0;
$location_id = $_GET['location_id'] ?? 0;

if (!$product_id || !$location_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
    exit;
}

$stmt = $conn->prepare("SELECT quantity FROM scs_inventory WHERE product_id = ? AND location_id = ?");
$stmt->bind_param("ii", $product_id, $location_id);
$stmt->execute();
$result = $stmt->get_result();

$quantity = 0;
if ($result->num_rows > 0) {
    $quantity = $result->fetch_assoc()['quantity'];
}

echo json_encode(['success' => true, 'quantity' => $quantity]);
$stmt->close();
$conn->close();
?>