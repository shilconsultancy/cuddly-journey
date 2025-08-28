<?php
// pos/print-receipt.php

require_once __DIR__ . '/../config.php';

if (!check_permission('POS', 'view')) { die('Permission Denied.'); }

$invoice_id = $_GET['invoice_id'] ?? 0;
if (!$invoice_id) { die('Invalid Invoice ID.'); }

$company_details = get_company_details($conn);
$currency_symbol = htmlspecialchars($company_details['currency_symbol'] ?? '$');

// Fetch invoice, items, and payment details
$stmt = $conn->prepare("
    SELECT i.*, c.customer_name, p.payment_method, p.amount_tendered, p.change_given
    FROM scs_invoices i
    JOIN scs_customers c ON i.customer_id = c.id
    LEFT JOIN scs_pos_sales p ON i.id = p.invoice_id
    WHERE i.id = ?
");
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

$items_stmt = $conn->prepare("
    SELECT ii.quantity, ii.unit_price, ii.line_total, p.product_name
    FROM scs_invoice_items ii
    JOIN scs_products p ON ii.product_id = p.id
    WHERE ii.invoice_id = ?
");
$items_stmt->bind_param("i", $invoice_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.4; color: #000; background: #fff; width: 300px; margin: 0 auto; }
        .receipt-container { padding: 10px; }
        .header { text-align: center; }
        .header h1 { font-size: 16px; margin: 0; }
        .header p { margin: 2px 0; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .items-table th, .items-table td { padding: 4px 0; }
        .items-table thead { border-top: 1px dashed #000; border-bottom: 1px dashed #000; }
        .items-table .qty { text-align: left; }
        .items-table .price { text-align: right; }
        .totals { margin-top: 10px; border-top: 1px dashed #000; padding-top: 5px; }
        .totals .row { display: flex; justify-content: space-between; }
        .totals .row.total { font-weight: bold; font-size: 14px; }
        .footer { text-align: center; margin-top: 15px; }
        @media print {
            body { width: 100%; }
        }
    </style>
</head>
<body onload="window.print(); setTimeout(window.close, 500);">
    <div class="receipt-container">
        <div class="header">
            <h1><?php echo htmlspecialchars($company_details['company_name']); ?></h1>
            <p><?php echo nl2br(htmlspecialchars($company_details['company_address'])); ?></p>
            <p>Phone: <?php echo htmlspecialchars($company_details['company_phone']); ?></p>
            <p>Date: <?php echo date($app_config['date_format'] . ' H:i', strtotime($invoice['created_at'])); ?></p>
            <p>Receipt: <?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="qty">Item</th>
                    <th class="price">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php while($item = $items_result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($item['product_name']); ?><br>
                        <?php echo $item['quantity']; ?> x <?php echo number_format($item['unit_price'], 2); ?>
                    </td>
                    <td class="price"><?php echo number_format($item['line_total'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="row"><span>Subtotal:</span> <span><?php echo $currency_symbol; ?><?php echo number_format($invoice['total_amount'], 2); ?></span></div>
            <div class="row"><span>Tax (0%):</span> <span><?php echo $currency_symbol; ?>0.00</span></div>
            <div class="row total"><span>TOTAL:</span> <span><?php echo $currency_symbol; ?><?php echo number_format($invoice['total_amount'], 2); ?></span></div>
            <div class="row"><span><?php echo htmlspecialchars($invoice['payment_method']); ?>:</span> <span><?php echo $currency_symbol; ?><?php echo number_format($invoice['amount_tendered'], 2); ?></span></div>
            <div class="row"><span>Change:</span> <span><?php echo $currency_symbol; ?><?php echo number_format($invoice['change_given'], 2); ?></span></div>
        </div>

        <div class="footer">
            <p><?php echo htmlspecialchars($company_details['document_footer_text'] ?? 'Thank you for your purchase!'); ?></p>
        </div>
    </div>
</body>
</html>