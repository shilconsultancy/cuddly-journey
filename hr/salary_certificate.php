<?php
// hr/salary_certificate.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('HR', 'view')) { // Or 'create' if you want it to be more restrictive
    die('<div class="glass-card p-8 text-center">You do not have permission to generate certificates.</div>');
}

$page_title = "Salary Certificate Generator - BizManager";

// Fetch employees who HAVE a contract, as salary info is tied to it.
$employees_with_contracts_result = $conn->query("
    SELECT u.id, u.full_name 
    FROM scs_users u
    JOIN scs_job_contracts jc ON u.id = jc.user_id
    WHERE u.is_active = 1 
    ORDER BY u.full_name ASC
");

?>
<title><?php echo htmlspecialchars($page_title); ?></title>
<style>
    #certificate-preview { 
        font-family: 'Times New Roman', Times, serif; 
        line-height: 1.8; 
        color: #333;
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
    .letter-header .logo-container img {
        max-height: 70px;
        width: auto;
    }
    .letter-header .company-details {
        text-align: right;
        font-size: 0.9rem;
        line-height: 1.5;
    }
    .company-details h1 {
        font-weight: bold;
        font-size: 1.5rem;
        color: #111827;
    }
    .company-details p {
        margin: 0;
    }
    .signature-block {
        margin-top: 5rem;
    }
    .signature-line {
        border-top: 1px solid #333;
        margin-top: 3rem;
        width: 250px;
    }


    @media print {
        body * { visibility: hidden; }
        .print-area, .print-area * { visibility: visible; }
        .print-area { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
        .letter-header {
            display: flex !important;
            justify-content: space-between !important;
            align-items: flex-start !important;
        }
    }
</style>

<div class="flex justify-between items-center mb-6 no-print">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Salary Certificate Generator</h2>
        <p class="text-gray-600 mt-1">Select an employee to generate their salary certificate.</p>
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
                <?php while($emp = $employees_with_contracts_result->fetch_assoc()): ?>
                    <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>
    <div class="lg:col-span-3">
        <div class="glass-card p-8 lg:p-12 print-area" id="certificate-preview">
            <div id="letter-placeholder" class="text-center text-gray-500 py-20">
                Please select an employee to generate the certificate.
            </div>
            <div id="letter-content" class="hidden">
                <div class="letter-header">
                    <div class="logo-container">
                        <img id="company-logo" src="" alt="Company Logo">
                    </div>
                    <div class="company-details">
                        <h1 id="company-name">[Company Name]</h1>
                        <p id="company-address">[Company Address]</p>
                        <p id="company-email">[Company Email]</p>
                        <p id="company-phone">[Company Phone]</p>
                        <p id="company-website">[Company Website]</p>
                    </div>
                </div>

                <p class="mb-8" id="current-date">[Date]</p>

                <h2 class="text-xl font-bold my-8 text-center underline">TO WHOM IT MAY CONCERN</h2>

                <p class="my-6">This is to certify that <strong><span id="employee-name">[Employee Name]</span></strong>, son/daughter of <strong><span id="father-name">[Father's Name]</span></strong>, has been employed with our company, <?php echo htmlspecialchars($app_config['company_name'] ?? ''); ?>, since <strong><span id="hire-date">[Hire Date]</span></strong>.</p>
                
                <p class="my-6">He/She is currently holding the position of <strong><span id="job-title">[Job Title]</span></strong> in the <strong><span id="department">[Department]</span></strong>.</p>

                <p class="my-6">As per our records, his/her current gross monthly salary is <strong><?php echo $app_config['currency_symbol']; ?> <span id="salary-numeric">[Salary Numeric]</span>/-</strong> (<span id="salary-in-words" class="capitalize">[Salary in Words]</span> Taka Only).</p>
                
                <p class="my-6">This certificate is issued upon the request of the employee for whatever legal purpose it may serve.</p>
                
                <div class="signature-block">
                    <p>Sincerely,</p>
                    <div class="signature-line"></div>
                    <p class="font-bold">Saikat Kumar Shil</p> <p>Director</p>
                    <p><?php echo htmlspecialchars($app_config['company_name'] ?? ''); ?></p>
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

        // We can reuse the same API endpoint as it has all the info we need
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
                }
            });
    });

    function populateCertificate(data) {
        // --- Populate Header ---
        const companyName = "<?php echo htmlspecialchars($app_config['company_name'] ?? ''); ?>";
        const companyLogoUrl = "<?php echo htmlspecialchars($app_config['company_logo_url'] ?? ''); ?>";
        
        document.getElementById('company-logo').src = companyLogoUrl ? `../${companyLogoUrl}` : '';
        document.getElementById('company-name').textContent = companyName;
        document.getElementById('company-address').textContent = "<?php echo htmlspecialchars(str_replace(["\r", "\n"], ' ', $app_config['company_address'] ?? '')); ?>";
        document.getElementById('company-email').textContent = "<?php echo htmlspecialchars($app_config['company_email'] ?? ''); ?>";
        document.getElementById('company-phone').textContent = "<?php echo htmlspecialchars($app_config['company_phone'] ?? ''); ?>";
        document.getElementById('company-website').textContent = "<?php echo htmlspecialchars($app_config['company_website'] ?? ''); ?>";

        // --- Populate Certificate Body ---
        document.getElementById('current-date').textContent = "Date: " + new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
        document.getElementById('employee-name').textContent = data.full_name || '[Employee Name]';
        document.getElementById('father-name').textContent = data.father_name || '[Fathers Name]';
        document.getElementById('hire-date').textContent = data.hire_date ? new Date(data.hire_date + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }) : '[Hire Date]';
        document.getElementById('job-title').textContent = data.contract_title || '[Job Title]';
        document.getElementById('department').textContent = data.department || '[Department]';
        document.getElementById('salary-numeric').textContent = parseFloat(data.salary || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('salary-in-words').textContent = numberToWords(parseFloat(data.salary || 0));
    }

    printBtn.addEventListener('click', function() {
        window.print();
    });

    // --- Number to Words Converter ---
    function numberToWords(num) {
        const a = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        const b = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
        
        if ((num = num.toString()).length > 9) return 'overflow';
        let n = ('000000000' + num).substr(-9).match(/^(\d{2})(\d{2})(\d{2})(\d{1})(\d{2})$/);
        if (!n) return;
        
        let str = '';
        str += (n[1] != 0) ? (a[Number(n[1])] || b[n[1][0]] + ' ' + a[n[1][1]]) + ' crore ' : '';
        str += (n[2] != 0) ? (a[Number(n[2])] || b[n[2][0]] + ' ' + a[n[2][1]]) + ' lakh ' : '';
        str += (n[3] != 0) ? (a[Number(n[3])] || b[n[3][0]] + ' ' + a[n[3][1]]) + ' thousand ' : '';
        str += (n[4] != 0) ? (a[Number(n[4])] || b[n[4][0]] + ' ' + a[n[4][1]]) + ' hundred ' : '';
        str += (n[5] != 0) ? ((str != '') ? 'and ' : '') + (a[Number(n[5])] || b[n[5][0]] + ' ' + a[n[5][1]]) : '';
        
        return str.trim();
    }
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>