<?php
// settings/reset_app.php

require_once __DIR__ . '/../config.php';

// --- SUPER ADMIN SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role_id'] != 1) {
    header("Location: ../dashboard.php");
    exit();
}

// --- DOUBLE FORM SUBMISSION CHECK ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === 'RESET') {
    
    // List of tables to truncate (delete all data from).
    // The order is important to avoid foreign key constraint errors.
    // Child tables must be cleared before parent tables.
    $tables_to_truncate = [
        // Payroll, Leave, Attendance first as they depend on users
        'scs_payslips',
        'scs_leave_requests',
        'scs_attendance',
        'scs_employee_details',
        // Payments and items first, then the parent documents
        'scs_invoice_payments',
        'scs_bill_payments',
        'scs_invoice_items',
        'scs_sales_order_items',
        'scs_purchase_order_items',
        'scs_quotation_items',
        'scs_pos_sales',
        // Now the main documents
        'scs_invoices',
        'scs_supplier_bills',
        'scs_purchase_orders',
        'scs_sales_orders',
        'scs_quotations',
        // CRM items
        'scs_ticket_replies',
        'scs_support_tickets',
        'scs_interactions',
        'scs_opportunities',
        'scs_contacts',
        'scs_customers',
        'scs_leads',
        // Inventory and Transfers
        'scs_stock_transfer_items',
        'scs_stock_transfers',
        'scs_inventory',
        // Financials
        'scs_journal_entry_items',
        'scs_journal_entries',
        'scs_bank_statement_lines',
        'scs_bank_reconciliations',
        'scs_asset_depreciation_schedule',
        'scs_fixed_assets',
        'scs_budgets',
        'scs_budget_items',
        // Logs
        'scs_activity_logs'
    ];

    $conn->begin_transaction();
    try {
        // Disable foreign key checks to safely truncate tables
        $conn->query("SET FOREIGN_KEY_CHECKS = 0;");

        // Truncate all specified tables
        foreach ($tables_to_truncate as $table) {
            $conn->query("TRUNCATE TABLE `$table`;");
        }

        // Delete all users EXCEPT Super Admins (role_id = 1)
        $conn->query("DELETE FROM `scs_users` WHERE `role_id` != 1;");

        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
        
        $conn->commit();
        
        // After reset, log out the current user to force a fresh login
        $_SESSION = array();
        session_destroy();
        header("Location: ../index.php?reset=success");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        // Re-enable foreign key checks even if it fails
        $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
        header("Location: system-configuration.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    // If the form was submitted without the correct confirmation
    header("Location: system-configuration.php?error=reset_failed");
    exit();
}