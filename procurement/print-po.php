<?php
// procurement/print-po.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (!check_permission('Procurement', 'view')) { die('Permission denied.'); }

$po_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($po_id === 0) { die('Invalid Purchase Order ID.'); }

// --- DATA FETCHING ---
$company_details = get_company_details($conn);

$po_stmt = $conn->prepare("
    SELECT 
        po.*, 
        s.supplier_name, s.address as supplier_address,
        l.location_name as delivery_location_name, l.address as delivery_location_address
    FROM scs_purchase_orders po
    JOIN scs_suppliers s ON po.supplier_id = s.id
    JOIN scs_locations l ON po.location_id = l.id
    WHERE po.id = ?
");
$po_stmt->bind_param("i", $po_id);
$po_stmt->execute();
$po = $po_stmt->get_result()->fetch_assoc();
$po_stmt->close();

if (!$po) { die('Purchase Order not found.'); }

$items_stmt = $conn->prepare("
    SELECT poi.*, p.product_name, p.sku
    FROM scs_purchase_order_items poi
    JOIN scs_products p ON poi.product_id = p.id
    WHERE poi.po_id = ?
");
$items_stmt->bind_param("i", $po_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

$page_title = "Print PO: " . htmlspecialchars($po['po_number']);
$currency_symbol = htmlspecialchars($company_details['currency_symbol'] ?? '$');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        body { font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333; background-color: #f1f5f9; margin: 0; padding: 0; padding-top: 80px; }
        .top-bar { position: fixed; top: 0; left: 0; width: 100%; padding: 12px 20px; background-color: #ffffff; border-bottom: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: right; z-index: 100; box-sizing: border-box; }
        .top-bar button { padding: 8px 18px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; margin-left: 12px; transition: background-color 0.2s ease; }
        .btn-print { background-color: #4f46e5; color: white; }
        .btn-print:hover { background-color: #4338ca; }
        .btn-close { background-color: #e5e7eb; color: #374151; }
        .btn-close:hover { background-color: #d1d5db; }
        .invoice-box { max-width: 800px; margin: 20px auto; padding: 30px; border: 1px solid #e2e8f0; box-shadow: 0 0 15px rgba(0, 0, 0, 0.05); background-color: #fff; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 20px; border-bottom: 2px solid #eee; }
        .header-left .logo { max-width: 180px; max-height: 90px; }
        .header-left .company-name { font-size: 24px; font-weight: bold; color: #333; }
        .header-right { text-align: right; }
        .header-right h1 { margin: 0; font-size: 28px; color: #888; font-weight: 300; }
        .addresses { display: flex; justify-content: space-between; margin-top: 30px; margin-bottom: 40px; }
        .address-block { width: 48%; }
        .address-block h3 { margin-top: 0; font-size: 14px; font-weight: bold; color: #555; }
        table.items-table { width: 100%; border-collapse: collapse; }
        .items-table thead tr { background-color: #f9f9f9; font-weight: bold; }
        .items-table th, .items-table td { padding: 12px; border-bottom: 1px solid #eee; }
        .items-table .text-right { text-align: right; }
        .totals-section { margin-top: 20px; display: flex; justify-content: flex-end; }
        .totals-table { width: 50%; }
        .totals-table td { padding: 8px 12px; }
        .totals-table .grand-total td { border-top: 2px solid #333; font-weight: bold; font-size: 16px; }
        .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #777; border-top: 1px solid #eee; padding-top: 10px; }
        @media print { .no-print { display: none !important; } body { background-color: #fff; padding-top: 0; } .invoice-box { box-shadow: none; border: none; margin: 0; padding: 0; } }
    </style>
</head>
<body>
    <div class="top-bar no-print">
        <button onclick="window.close()" class="btn-close">Close</button>
        <button onclick="window.print()" class="btn-print">Print</button>
    </div>
    <div class="invoice-box">
        <header class="header">
            <div class="header-left">
                <?php if (!empty($company_details['company_logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($company_details['company_logo_url']); ?>" alt="<?php echo htmlspecialchars($company_details['company_name'] ?? ''); ?>" class="logo">
                <?php else: ?>
                    <div class="company-name"><?php echo htmlspecialchars($company_details['company_name'] ?? 'Your Company'); ?></div>
                <?php endif; ?>
            </div>
            <div class="header-right">
                <h1>PURCHASE ORDER</h1>
                <p>
                    <strong>PO #:</strong> <?php echo htmlspecialchars($po['po_number'] ?? ''); ?><br>
                    <strong>Date:</strong> <?php echo date($app_config['date_format'], strtotime($po['order_date'])); ?><br>
                    <?php if(!empty($po['expected_delivery_date'])): ?>
                        <strong>Expected Delivery:</strong> <?php echo date($app_config['date_format'], strtotime($po['expected_delivery_date'])); ?>
                    <?php endif; ?>
                </p>
            </div>
        </header>

        <section class="addresses">
            <div class="address-block">
                <h3>Supplier:</h3>
                <p>
                    <strong><?php echo htmlspecialchars($po['supplier_name'] ?? ''); ?></strong><br>
                    <?php echo nl2br(htmlspecialchars($po['supplier_address'] ?? '')); ?>
                </p>
            </div>
            <div class="address-block">
                <h3>Deliver To:</h3>
                <p>
                    <strong><?php echo htmlspecialchars($po['delivery_location_name'] ?? ''); ?></strong><br>
                    <?php echo nl2br(htmlspecialchars($po['delivery_location_address'] ?? '')); ?>
                </p>
            </div>
        </section>

        <table class="items-table">
            <thead>
                <tr>
                    <th>#</th><th>Product / Service</th><th class="text-right">Quantity</th><th class="text-right">Unit Price</th><th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $item_number = 1; while($item = $items_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $item_number++; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($item['product_name'] ?? ''); ?></strong><br>
                        <small>SKU: <?php echo htmlspecialchars($item['sku'] ?? ''); ?></small>
                    </td>
                    <td class="text-right"><?php echo $item['quantity']; ?></td>
                    <td class="text-right"><?php echo $currency_symbol . htmlspecialchars(number_format($item['unit_price'], 2)); ?></td>
                    <td class="text-right"><?php echo $currency_symbol . htmlspecialchars(number_format($item['quantity'] * $item['unit_price'], 2)); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <section class="totals-section">
            <table class="totals-table">
                <tr class="grand-total"><td>Grand Total:</td><td class="text-right"><?php echo $currency_symbol . htmlspecialchars(number_format($po['total_amount'], 2)); ?></td></tr>
            </table>
        </section>
        
        <footer class="footer">
            <p><?php echo htmlspecialchars($company_details['document_footer_text'] ?? 'Thank you for your business!'); ?></p>
        </footer>
    </div>

    <script>
        window.onload = function() { window.print(); };
    </script>
</body>
</html>