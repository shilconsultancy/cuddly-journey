<?php
// procurement/print-statement.php

// This page is a dedicated print view. It connects to the DB and fetches data directly.
// The config file now handles starting the session, so we don't need to do it here.
require_once __DIR__ . '/../config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || !check_permission('Procurement', 'view')) {
    die('Access Denied.');
}

$supplier_id = $_GET['id'] ?? 0;
if (!$supplier_id) {
    die("No supplier ID provided.");
}

// --- DATA FETCHING ---
$stmt_supplier = $conn->prepare("SELECT * FROM scs_suppliers WHERE id = ?");
$stmt_supplier->bind_param("i", $supplier_id);
$stmt_supplier->execute();
$supplier = $stmt_supplier->get_result()->fetch_assoc();
$stmt_supplier->close();

if (!$supplier) {
    die("Supplier not found.");
}

$stmt_pos = $conn->prepare("SELECT id, po_number, order_date, total_amount, status FROM scs_purchase_orders WHERE supplier_id = ? ORDER BY order_date DESC");
$stmt_pos->bind_param("i", $supplier_id);
$stmt_pos->execute();
$purchase_orders = $stmt_pos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_pos->close();

// --- CALCULATE FINANCIAL SUMMARY ---
$total_business = 0;
foreach ($purchase_orders as $po) {
    $total_business += $po['total_amount'];
}
$total_paid = 0.00; // Placeholder
$balance_due = $total_business - $total_paid;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statement for <?php echo htmlspecialchars($supplier['supplier_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #fff; /* Plain white background for printing */
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body class="p-8">
    <div class="max-w-4xl mx-auto bg-white">
        <!-- Print Button -->
        <div class="text-right mb-6 no-print">
            <button onclick="window.print()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg shadow-sm hover:bg-indigo-700">Print</button>
        </div>

        <!-- Statement Header -->
        <div class="flex justify-between items-start border-b pb-8 mb-8">
            <div>
                <h1 class="text-4xl font-bold text-gray-800"><?php echo htmlspecialchars($app_config['company_name']); ?></h1>
                <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($app_config['company_address'])); ?></p>
            </div>
            <div class="text-right">
                <h2 class="text-2xl font-semibold text-gray-700">Supplier Statement</h2>
                <p class="text-gray-500">Date: <?php echo date($app_config['date_format']); ?></p>
            </div>
        </div>

        <!-- Supplier Info & Summary -->
        <div class="grid grid-cols-2 gap-8 mb-8">
            <div class="space-y-1">
                <p class="text-sm text-gray-500">STATEMENT FOR:</p>
                <p class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($supplier['supplier_name']); ?></p>
                <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($supplier['address'])); ?></p>
                <p class="text-gray-600"><?php echo htmlspecialchars($supplier['email']); ?></p>
                <p class="text-gray-600"><?php echo htmlspecialchars($supplier['phone']); ?></p>
            </div>
            <div class="border rounded-lg p-6 text-right">
                <p class="text-sm text-gray-500">Total Business</p>
                <p class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($app_config['currency_symbol'] . ' ' . number_format($total_business, 2)); ?></p>
                <hr class="my-2">
                <p class="text-sm text-gray-500">Total Paid</p>
                <p class="text-xl font-semibold text-green-600"><?php echo htmlspecialchars($app_config['currency_symbol'] . ' ' . number_format($total_paid, 2)); ?></p>
                <p class="text-sm text-gray-500 mt-2">Balance Due</p>
                <p class="text-xl font-semibold text-red-600"><?php echo htmlspecialchars($app_config['currency_symbol'] . ' ' . number_format($balance_due, 2)); ?></p>
            </div>
        </div>

        <!-- Transaction List -->
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Transaction History</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="text-xs text-gray-800 uppercase bg-gray-100">
                    <tr>
                        <th scope="col" class="px-6 py-3">Date</th>
                        <th scope="col" class="px-6 py-3">Transaction Details</th>
                        <th scope="col" class="px-6 py-3">Type</th>
                        <th scope="col" class="px-6 py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchase_orders)): ?>
                        <tr class="bg-white border-b">
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">No transactions found for this supplier.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($purchase_orders as $po): ?>
                        <tr class="bg-white border-b">
                            <td class="px-6 py-4"><?php echo date($app_config['date_format'], strtotime($po['order_date'])); ?></td>
                            <td class="px-6 py-4 font-medium">Purchase Order #<?php echo htmlspecialchars($po['po_number']); ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-semibold text-blue-800 bg-blue-100 rounded-full">
                                    Purchase
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right font-semibold"><?php echo htmlspecialchars($app_config['currency_symbol'] . ' ' . number_format($po['total_amount'], 2)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="bg-white border-b">
                            <td colspan="4" class="px-6 py-4 text-center text-gray-400 italic">Payment history will appear here once the Accounts module is built.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="text-center text-xs text-gray-500 mt-8">
            <?php echo htmlspecialchars($app_config['document_footer_text'] ?? ''); ?>
        </div>
    </div>
    <script>
        // Automatically trigger the print dialog when the page loads
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>