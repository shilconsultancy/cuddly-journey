<?php
// hr/appointment_letter_generator.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('HR', 'create')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to generate letters.</div>');
}

$page_title = "Appointment Letter Generator - BizManager";

// Fetch employees who HAVE a contract
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
    #letter-preview { font-family: 'Times New Roman', Times, serif; line-height: 1.6; }
    @media print {
        body * { visibility: hidden; }
        .print-area, .print-area * { visibility: visible; }
        .print-area { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
    }
</style>

<div class="flex justify-between items-center mb-6 no-print">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Appointment Letter Generator</h2>
        <p class="text-gray-600 mt-1">Select an employee to generate their appointment letter.</p>
    </div>
    <div class="flex space-x-2">
        <button id="print-btn" class="px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 disabled:bg-gray-400" disabled>Print Letter</button>
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
        <div class="glass-card p-8 lg:p-12 print-area" id="letter-preview">
            <div id="letter-placeholder" class="text-center text-gray-500 py-20">
                Please select an employee to generate the letter.
            </div>
            <div id="letter-content" class="hidden">
                <div class="text-center mb-12">
                    <h1 class="text-3xl font-bold uppercase" id="company-name">[Company Name]</h1>
                    <p class="text-sm" id="company-address">[Company Address]</p>
                </div>

                <p class="mb-4" id="current-date">[Date]</p>

                <p class="font-bold" id="employee-name">[Employee Name]</p>
                <p id="employee-address">[Employee Address]</p>

                <h2 class="text-xl font-bold my-8 text-center underline">APPOINTMENT LETTER</h2>

                <p class="mb-4">Dear <span id="employee-firstname">[Employee First Name]</span>,</p>
                
                <p class="mb-4">Further to your application and the subsequent interview, we are pleased to offer you the position of <strong id="job-title">[Job Title]</strong> at <strong class="company-name-inline">[Company Name]</strong>. We are excited to have you join our team.</p>

                <p class="mb-4">Your employment will commence on <strong id="start-date">[Start Date]</strong>. This is a <strong id="job-type">[Job Type]</strong> position.</p>

                <p class="mb-4">Your initial monthly salary will be <strong id="salary-text">[Salary in Text]</strong> (<?php echo $app_config['currency_symbol']; ?><strong id="salary-numeric">[Salary Numeric]</strong>), subject to statutory deductions.</p>
                
                <p class="mb-4">You will be based at our <strong id="location">[Location]</strong> office and will be reporting to <strong id="manager-name">[Manager Name]</strong>.</p>
                
                <p class="mb-4">We look forward to a long and successful association with you. Please sign and return a copy of this letter as a token of your acceptance of this offer.</p>

                <div class="mt-20">
                    <p>Sincerely,</p>
                    <br><br><br>
                    <p class="font-bold">[Your Name/HR Manager]</p>
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

        fetch(`../api/get_employee_contract_details.php?user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateLetter(data.data);
                    letterPlaceholder.classList.add('hidden');
                    letterContent.classList.remove('hidden');
                    printBtn.disabled = false;
                } else {
                    alert('Error: ' + data.message);
                }
            });
    });

    function populateLetter(data) {
        const companyName = "<?php echo htmlspecialchars($app_config['company_name'] ?? ''); ?>";
        document.getElementById('company-name').textContent = companyName;
        document.querySelectorAll('.company-name-inline').forEach(el => el.textContent = companyName);
        document.getElementById('company-address').textContent = "<?php echo htmlspecialchars(str_replace(["\r", "\n"], ' ', $app_config['company_address'] ?? '')); ?>";
        document.getElementById('current-date').textContent = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
        
        document.getElementById('employee-name').textContent = data.full_name || '[Employee Name]';
        document.getElementById('employee-address').textContent = data.address || '[Employee Address]';
        document.getElementById('employee-firstname').textContent = data.full_name ? data.full_name.split(' ')[0] : '[Employee First Name]';
        document.getElementById('job-title').textContent = data.contract_title || '[Job Title]';
        document.getElementById('start-date').textContent = data.start_date ? new Date(data.start_date + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }) : '[Start Date]';
        document.getElementById('job-type').textContent = data.job_type || '[Job Type]';
        document.getElementById('salary-numeric').textContent = parseFloat(data.salary || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('location').textContent = data.location_name || '[Location]';
        document.getElementById('manager-name').textContent = data.manager_name || '[Manager Name]';

        // A simple number to words function for salary could be added here if needed
        document.getElementById('salary-text').textContent = "As per contract";
    }

    printBtn.addEventListener('click', function() {
        window.print();
    });
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>