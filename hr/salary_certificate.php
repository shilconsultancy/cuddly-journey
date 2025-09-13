<?php
// hr/salary_certificate.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('HR', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to generate certificates.</div>');
}

$page_title = "Salary Certificate Generator - BizManager";

// Fetch employees who have salary details
$employees_result = $conn->query("
    SELECT u.id, u.full_name, u.company_id 
    FROM scs_users u
    JOIN scs_employee_details ed ON u.id = ed.user_id
    WHERE u.is_active = 1 AND ed.gross_salary > 0
    ORDER BY u.full_name ASC
");

?>
<title><?php echo htmlspecialchars($page_title); ?></title>
<style>
    /* --- Styles for a compact, single-page, professional layout --- */
    #certificate-preview { 
        font-family: 'Times New Roman', Times, serif; 
        line-height: 1.6; 
        color: #111827;
        font-size: 12pt;
    }
    .letter-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 1rem;
        margin-bottom: 2rem;
    }
    .letter-header .logo-container img { max-height: 70px; width: auto; }
    .letter-header .company-details { text-align: right; font-size: 0.9em; line-height: 1.5; }
    .company-details h1 { font-weight: bold; font-size: 1.5em; }
    .company-details p { margin: 0; }
    
    .salary-table {
        width: 100%;
        margin: 1.5rem 0;
        border-collapse: collapse;
        border: 1px solid #ccc;
    }
    .salary-table th, .salary-table td {
        border: 1px solid #ccc;
        padding: 0.6rem;
        text-align: left;
    }
    .salary-table th { background-color: #f3f4f6; font-weight: bold; }
    .salary-table td.amount { text-align: right; }
    .salary-table .total-row { font-weight: bold; background-color: #e5e7eb; }
    
    .signature-block { margin-top: 4rem; }
    .signature-line { border-top: 1px solid #333; margin-top: 3rem; width: 250px; }
    
    p.cert-body { margin: 1.2rem 0; text-align: justify; }
    h2.cert-title { font-size: 1.5em; font-weight: bold; margin: 2rem 0; text-align: center; text-decoration: underline; }

    @media print {
        body { background-color: white !important; margin: 0; padding: 0; font-size: 12pt; }
        .no-print { display: none !important; }
        .glass-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .print-area, .print-area * { visibility: visible; }
        .print-area { position: absolute; left: 0; top: 0; width: 100%; }
        .letter-header { display: flex !important; justify-content: space-between !important; }
    }
</style>

<div class="flex justify-between items-center mb-6 no-print">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Salary Certificate Generator</h2>
        <p class="text-gray-600 mt-1">Select an employee to generate their official salary certificate.</p>
    </div>
    <div class="flex space-x-2">
        <button id="print-btn" class="px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 disabled:bg-gray-400" disabled>Print Certificate</button>
        <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">&larr; Back to HR</a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
    <div class="lg:col-span-1 no-print">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Select Employee</h3>
            <select id="employee-select" class="form-input w-full p-2">
                <option value="">Choose an employee...</option>
                <?php while($emp = $employees_result->fetch_assoc()): ?>
                    <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name'] . ' (' . $emp['company_id'] . ')'); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>
    <div class="lg:col-span-3">
        <div class="glass-card p-8 lg:p-12">
            <div id="letter-placeholder" class="text-center text-gray-500 py-20">
                Please select an employee to generate the certificate.
            </div>
            <div id="letter-content" class="print-area hidden">
                <div id="certificate-preview">
                    <div class="letter-header">
                        <div class="logo-container"><img id="company-logo" src="" alt="Logo"></div>
                        <div class="company-details">
                            <h1 id="company-name">[Company Name]</h1>
                            <p id="company-address">[Company Address]</p>
                            <p id="company-email">[Company Email]</p>
                            <p id="company-phone">[Company Phone]</p>
                        </div>
                    </div>
                    <p id="current-date">[Date]</p>
                    <h2 class="cert-title">TO WHOM IT MAY CONCERN</h2>
                    <p class="cert-body">This is to certify that <strong><span id="employee-name">[Employee Name]</span></strong>, holding National ID No. <strong><span id="national-id">[NID]</span></strong>, has been employed with our company since <strong><span id="hire-date">[Hire Date]</span></strong>. He/She is currently holding the position of <strong><span id="job-title">[Job Title]</span></strong>.</p>
                    <p class="cert-body">His/Her current monthly salary structure is as follows:</p>
                    <table class="salary-table">
                        <thead><tr><th>Earnings Description</th><th class="amount">Amount</th></tr></thead>
                        <tbody>
                            <tr><td>Basic Salary</td><td id="basic-salary" class="amount">0.00</td></tr>
                            <tr><td>Total Allowances (Lunch, Transport, etc.)</td><td id="total-allowances" class="amount">0.00</td></tr>
                            <tr class="total-row"><td>Gross Salary</td><td id="gross" class="amount">0.00</td></tr>
                        </tbody>
                    </table>
                    <p class="cert-body">This certificate is issued upon the request of the employee for any official purpose it may serve.</p>
                    <div class="signature-block">
                        <div class="signature-line"></div>
                        <p><strong>Authorized Signatory</strong></p>
                        <p><?php echo htmlspecialchars($app_config['company_name'] ?? ''); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const employeeSelect = document.getElementById('employee-select');
    const letterPlaceholder = document.getElementById('letter-placeholder');
    const letterContent = document.getElementById('letter-content');
    const printBtn = document.getElementById('print-btn');

    employeeSelect.addEventListener('change', function() {
        const userId = this.value;
        if (!userId) {
            letterContent.classList.add('hidden');
            letterPlaceholder.classList.remove('hidden');
            printBtn.disabled = true;
            return;
        }

        fetch(`../api/get_employee_contract_details.php?user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateCertificate(data.data);
                    letterPlaceholder.classList.add('hidden');
                    letterContent.classList.remove('hidden');
                    printBtn.disabled = false;
                } else {
                    alert('Error: ' + data.message);
                    letterContent.classList.add('hidden');
                    letterPlaceholder.classList.remove('hidden');
                    printBtn.disabled = true;
                }
            });
    });
    
    function formatCurrency(num) {
        let currencySymbol = "<?php echo $app_config['currency_symbol'] ?? ''; ?> ";
        return currencySymbol + parseFloat(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function populateCertificate(data) {
        // --- Populate Header ---
        document.getElementById('company-logo').src = `../${"<?php echo htmlspecialchars($app_config['company_logo_url'] ?? ''); ?>"}`;
        document.getElementById('company-name').textContent = "<?php echo htmlspecialchars($app_config['company_name'] ?? ''); ?>";
        document.getElementById('company-address').textContent = "<?php echo htmlspecialchars(str_replace(["\r", "\n"], ' ', $app_config['company_address'] ?? '')); ?>";
        document.getElementById('company-email').textContent = "<?php echo htmlspecialchars($app_config['company_email'] ?? ''); ?>";
        document.getElementById('company-phone').textContent = "<?php echo htmlspecialchars($app_config['company_phone'] ?? ''); ?>";

        // --- Populate Certificate Body ---
        document.getElementById('current-date').textContent = "Date: " + new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
        document.getElementById('employee-name').textContent = data.full_name || '[Employee Name]';
        document.getElementById('national-id').textContent = data.national_id || '[NID]';
        document.getElementById('hire-date').textContent = data.hire_date ? new Date(data.hire_date + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }) : '[Hire Date]';
        document.getElementById('job-title').textContent = data.job_title || '[Job Title]';

        // --- Populate Salary Table ---
        const totalAllowances = parseFloat(data.house_rent_allowance || 0) + parseFloat(data.medical_allowance || 0) + parseFloat(data.transport_allowance || 0) + parseFloat(data.other_allowances || 0);
        
        document.getElementById('basic-salary').textContent = formatCurrency(data.basic_salary);
        document.getElementById('total-allowances').textContent = formatCurrency(totalAllowances);
        document.getElementById('gross').textContent = formatCurrency(data.gross_salary);
    }

    printBtn.addEventListener('click', () => window.print());
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>