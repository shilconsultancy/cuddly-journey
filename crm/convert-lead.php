<?php
// crm/convert-lead.php

require_once __DIR__ . '/../config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || !check_permission('CRM', 'create')) {
    header("Location: ../dashboard.php");
    exit();
}

$lead_id = $_GET['id'] ?? 0;

if (!$lead_id) {
    header("Location: leads.php?error=noid");
    exit();
}

// --- CONVERSION LOGIC ---
$conn->begin_transaction();
try {
    // 1. Fetch the lead's data and ensure it's not already converted
    $stmt_lead = $conn->prepare("SELECT * FROM scs_leads WHERE id = ? AND converted_to_customer_id IS NULL");
    $stmt_lead->bind_param("i", $lead_id);
    $stmt_lead->execute();
    $lead = $stmt_lead->get_result()->fetch_assoc();
    $stmt_lead->close();

    if (!$lead) {
        throw new Exception("Lead not found or has already been converted.");
    }

    $created_by = $_SESSION['user_id'];

    // 2. Create a new Customer record
    $customer_name = !empty($lead['company_name']) ? $lead['company_name'] : $lead['lead_name'];
    $stmt_cust = $conn->prepare("INSERT INTO scs_customers (customer_name, customer_type, email, phone, created_by) VALUES (?, 'B2B', ?, ?, ?)");
    $stmt_cust->bind_param("sssi", $customer_name, $lead['email'], $lead['phone'], $created_by);
    $stmt_cust->execute();
    $new_customer_id = $conn->insert_id;
    $stmt_cust->close();

    // 3. Create a new Contact record, linked to the new Customer
    $stmt_cont = $conn->prepare("INSERT INTO scs_contacts (customer_id, contact_name, email, phone, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt_cont->bind_param("isssi", $new_customer_id, $lead['lead_name'], $lead['email'], $lead['phone'], $created_by);
    $stmt_cont->execute();
    $new_contact_id = $conn->insert_id;
    $stmt_cont->close();

    // Update the customer with the primary contact ID
    $stmt_update_cust = $conn->prepare("UPDATE scs_customers SET primary_contact_id = ? WHERE id = ?");
    $stmt_update_cust->bind_param("ii", $new_contact_id, $new_customer_id);
    $stmt_update_cust->execute();
    $stmt_update_cust->close();

    // 4. Create a new Opportunity in the Sales Pipeline
    $opportunity_name = "Opportunity for " . $customer_name;
    $assigned_to = $lead['assigned_to'];
    $stmt_opp = $conn->prepare("INSERT INTO scs_opportunities (opportunity_name, customer_id, contact_id, lead_id, assigned_to, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_opp->bind_param("siiiii", $opportunity_name, $new_customer_id, $new_contact_id, $lead_id, $assigned_to, $created_by);
    $stmt_opp->execute();
    $stmt_opp->close();
    
    // 5. Update the original lead to mark it as converted
    $new_status = 'Converted';
    $stmt_update_lead = $conn->prepare("UPDATE scs_leads SET status = ?, converted_to_customer_id = ? WHERE id = ?");
    $stmt_update_lead->bind_param("sii", $new_status, $new_customer_id, $lead_id);
    $stmt_update_lead->execute();
    $stmt_update_lead->close();

    // If everything was successful, commit the transaction
    $conn->commit();
    log_activity('LEAD_CONVERTED', "Converted lead '" . htmlspecialchars($lead['lead_name']) . "' to customer '" . htmlspecialchars($customer_name) . "'.", $conn);
    
    // Redirect back to the leads list with a success message
    header("Location: leads.php?success=converted");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    // Redirect back with an error message
    header("Location: leads.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>