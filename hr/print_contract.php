<?php
// hr/print_contract.php

require_once __DIR__ . '/../config.php';

// --- SECURITY CHECK ---
if (!check_permission('HR', 'view')) {
    die('You do not have permission to access this page.');
}

$contract_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($contract_id === 0) {
    die("No contract ID provided.");
}

// --- DATA FETCHING ---
// Fetch contract, employee, and company data
$stmt = $conn->prepare("
    SELECT
        c.contract_title, c.job_type, c.start_date, c.salary, c.terms_and_conditions,
        u.full_name, u.email,
        ed.father_name, ed.permanent_address, ed.national_id
    FROM scs_job_contracts c
    JOIN scs_users u ON c.user_id = u.id
    LEFT JOIN scs_employee_details ed ON u.id = ed.user_id
    WHERE c.id = ?
");
$stmt->bind_param("i", $contract_id);
$stmt->execute();
$result = $stmt->get_result();
$contract = $result->fetch_assoc();
$stmt->close();

if (!$contract) {
    die("Contract not found.");
}

// --- DYNAMIC CONTENT REPLACEMENT ---
// Array of placeholders to find
$placeholders = [
    '[Start Date]',
    '[Company Name]',
    '[Company Address]',
    '[Employee Name]',
    '[Employee\'s Permanent Address]',
    '[Employee NID]',
    '[Job Title]',
    '[Reporting Manager\'s Title]',
    '[Job Type, e.g., Full-time]',
    '[Initial Salary Amount]',
    '[Permanent Salary Amount]',
    '[Date of Signing]'
];

// Array of actual data to replace with
$replacements = [
    date($app_config['date_format'], strtotime($contract['start_date'])),
    htmlspecialchars($app_config['company_name']),
    htmlspecialchars($app_config['company_address']),
    htmlspecialchars($contract['full_name']),
    htmlspecialchars($contract['permanent_address'] ?? 'N/A'),
    htmlspecialchars($contract['national_id'] ?? 'N/A'),
    htmlspecialchars($contract['contract_title']),
    'Management', // This can be made dynamic later if needed
    htmlspecialchars($contract['job_type']),
    number_format($contract['salary'], 2), // Using the main salary for both for now
    number_format($contract['salary'], 2), // This can be customized further
    date($app_config['date_format'])
];

$final_contract_html = str_replace($placeholders, $replacements, $contract['terms_and_conditions']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Contract - <?php echo htmlspecialchars($contract['full_name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Times+New+Roman&family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f3f4f6;
            font-family: 'Times New Roman', serif;
            color: #111827;
            line-height: 1.6;
        }
        .page-container {
            width: 21cm;
            min-height: 29.7cm;
            padding: 2cm;
            margin: 1cm auto;
            border: 1px #D1D5DB solid;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .controls {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }
        .controls button {
            padding: 8px 16px;
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            background-color: #4f46e5;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        /* Style for the company logo */
        .header-logo {
            text-align: center;
            margin-bottom: 2em;
        }
        .header-logo img {
            max-height: 80px; /* Adjust as needed */
            width: auto;
        }
        h3 {
            font-size: 1.5em;
            text-align: center;
            text-decoration: underline;
            margin-bottom: 1.5em;
        }
        p {
            margin-bottom: 1em;
            text-align: justify;
        }
        strong {
            font-weight: bold;
        }
        hr {
            border: none;
            border-top: 1px solid #ccc;
            margin: 2em 0;
        }

        /* Print-specific styles */
        @media print {
            body {
                background-color: white;
                margin: 0;
                padding: 0;
            }
            .page-container {
                margin: 0;
                border: none;
                box-shadow: none;
                width: auto;
                min-height: auto;
                padding: 0;
            }
            .controls {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="controls">
        <button onclick="window.print()">Print Document</button>
    </div>

    <div class="page-container">
        <?php if (!empty($app_config['company_logo_url'])): ?>
            <div class="header-logo">
                <img src="../<?php echo htmlspecialchars($app_config['company_logo_url']); ?>" alt="<?php echo htmlspecialchars($app_config['company_name']); ?> Logo">
            </div>
        <?php endif; ?>
        
        <?php echo $final_contract_html; ?>
    </div>

</body>
</html>