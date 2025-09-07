<?php
// accounts/reconcile_account.php
require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Accounts', 'edit')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to perform this action.</div>');
}

$page_title = "Reconcile Account - BizManager";

// --- PARSE INITIAL PARAMETERS ---
$reconciliation_id = $_GET['id'] ?? 0;
$account_id = $_GET['account_id'] ?? 0;
$statement_start_date = $_GET['statement_start_date'] ?? null;
$statement_date = $_GET['statement_date'] ?? null; // This is the end date
$statement_balance = $_GET['statement_balance'] ?? 0.00;

if (!$reconciliation_id && (!$account_id || !$statement_date || !$statement_start_date)) {
    die('<div class="glass-card p-8 text-center">Required parameters are missing. Please start from the Bank Reconciliation page.</div>');
}

// Fetch account details to display in the header
if ($account_id) {
    $account_stmt = $conn->prepare("SELECT account_name FROM scs_chart_of_accounts WHERE id = ?");
    $account_stmt->bind_param("i", $account_id);
} elseif ($reconciliation_id) {
    $account_stmt = $conn->prepare("SELECT coa.account_name FROM scs_chart_of_accounts coa JOIN scs_bank_reconciliations r ON coa.id = r.account_id WHERE r.id = ?");
    $account_stmt->bind_param("i", $reconciliation_id);
}
$account_stmt->execute();
$account = $account_stmt->get_result()->fetch_assoc();
$account_stmt->close();

if (!$account) {
    die('<div class="glass-card p-8 text-center">Account not found.</div>');
}

?>
<title><?php echo htmlspecialchars($page_title); ?></title>

<div id="reconciliation-container" 
     data-reconciliation-id="<?php echo $reconciliation_id; ?>"
     data-account-id="<?php echo $account_id; ?>"
     data-statement-start-date="<?php echo htmlspecialchars($statement_start_date ?? ''); ?>"
     data-statement-date="<?php echo htmlspecialchars($statement_date ?? ''); ?>"
     data-statement-balance="<?php echo htmlspecialchars($statement_balance); ?>">

    <div class="flex justify-between items-center mb-4">
        <div>
            <h2 class="text-2xl font-semibold text-gray-800">Reconcile: <?php echo htmlspecialchars($account['account_name']); ?></h2>
            <p id="statement-date-header" class="text-gray-600 mt-1">Statement Period: ...</p>
        </div>
        <a href="bank_reconciliation.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
            &larr; Back to Reconciliations
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="glass-card p-4 text-center"><h4 class="text-sm text-gray-600">Statement Balance</h4><p id="summary-statement-balance" class="text-2xl font-bold">0.00</p></div>
        <div class="glass-card p-4 text-center"><h4 class="text-sm text-gray-600">Cleared Balance</h4><p id="summary-cleared-balance" class="text-2xl font-bold text-green-600">0.00</p></div>
        <div class="glass-card p-4 text-center"><h4 class="text-sm text-gray-600">Difference</h4><p id="summary-difference" class="text-2xl font-bold text-red-600">0.00</p></div>
        <div class="p-4 flex items-center justify-center">
            <button id="finish-button" class="w-full py-3 px-6 rounded-md text-white bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-400" disabled>Finish Now</button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="glass-card p-4">
            <h3 class="text-lg font-semibold mb-4 text-center">System Transactions</h3>
            <div class="overflow-y-auto h-[60vh]"><table class="w-full text-sm"><tbody id="system-transactions-table"></tbody></table></div>
        </div>
        <div class="glass-card p-4">
            <h3 class="text-lg font-semibold mb-4 text-center">Bank Statement</h3>
            <div class="overflow-y-auto h-[60vh]">
                 <div id="upload-box" class="text-center p-8 border-2 border-dashed border-gray-300/50 rounded-lg">
                    <p class="font-semibold">Import Bank Statement</p>
                    <p class="text-xs text-gray-500 mb-4">Upload a CSV file with columns: Date, Description, Amount</p>
                    <input type="file" id="csv-upload" accept=".csv" class="text-sm">
                </div>
                <table class="w-full text-sm mt-4 hidden" id="bank-statement-table"><tbody></tbody></table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('reconciliation-container');
    const reconData = {
        id: container.dataset.reconciliationId,
        accountId: container.dataset.accountId,
        statementStartDate: container.dataset.statementStartDate,
        statementDate: container.dataset.statementDate,
        statementBalance: parseFloat(container.dataset.statementBalance)
    };

    const systemTable = document.getElementById('system-transactions-table');
    const bankTable = document.getElementById('bank-statement-table').querySelector('tbody');
    const csvUpload = document.getElementById('csv-upload');
    const uploadBox = document.getElementById('upload-box');
    const finishButton = document.getElementById('finish-button');

    let systemTransactions = [];
    let bankTransactions = [];
    let matchedPairs = [];
    let clearedBalance = 0;

    function formatCurrency(amount) {
        return '<?php echo $app_config['currency_symbol'] ?? '$'; ?>' + parseFloat(amount).toFixed(2);
    }

    function renderTables() {
        systemTable.innerHTML = '';
        systemTransactions.forEach(item => {
            const amount = parseFloat(item.debit_amount) > 0 ? parseFloat(item.debit_amount) : -parseFloat(item.credit_amount);
            const row = `<tr class="border-b border-gray-200/50 hover:bg-indigo-50/50 cursor-pointer system-row" data-id="${item.id}" data-amount="${amount}"><td class="p-2">${new Date(item.entry_date + 'T00:00:00').toLocaleDateString()}</td><td class="p-2">${item.description}</td><td class="p-2 text-right font-mono ${amount >= 0 ? 'text-green-700' : 'text-red-700'}">${formatCurrency(amount)}</td></tr>`;
            systemTable.innerHTML += row;
        });

        bankTable.innerHTML = '';
        bankTransactions.forEach((item, index) => {
             const row = `<tr class="border-b border-gray-200/50 hover:bg-indigo-50/50 cursor-pointer bank-row" data-index="${index}" data-id="${item.id}" data-amount="${item.amount}"><td class="p-2">${item.date}</td><td class="p-2">${item.description}</td><td class="p-2 text-right font-mono ${item.amount >= 0 ? 'text-green-700' : 'text-red-700'}">${formatCurrency(item.amount)}</td></tr>`;
            bankTable.innerHTML += row;
        });
    }
    
    function updateSummary() {
        const difference = reconData.statementBalance - clearedBalance;
        document.getElementById('summary-statement-balance').textContent = formatCurrency(reconData.statementBalance);
        document.getElementById('summary-cleared-balance').textContent = formatCurrency(clearedBalance);
        document.getElementById('summary-difference').textContent = formatCurrency(difference);

        if (Math.abs(difference) < 0.01 && bankTransactions.length === 0 && systemTransactions.length === 0) {
            document.getElementById('summary-difference').classList.remove('text-red-600');
            document.getElementById('summary-difference').classList.add('text-green-600');
            finishButton.disabled = false;
        } else {
            document.getElementById('summary-difference').classList.remove('text-green-600');
            document.getElementById('summary-difference').classList.add('text-red-600');
            finishButton.disabled = true;
        }
    }
    
    let selectedSystem = null;
    let selectedBank = null;

    systemTable.addEventListener('click', e => {
        const row = e.target.closest('.system-row');
        if (row) {
            if (selectedSystem) selectedSystem.classList.remove('bg-yellow-200');
            selectedSystem = row;
            selectedSystem.classList.add('bg-yellow-200');
            tryMatch();
        }
    });

    bankTable.addEventListener('click', e => {
        const row = e.target.closest('.bank-row');
        if (row) {
            if (selectedBank) selectedBank.classList.remove('bg-yellow-200');
            selectedBank = row;
            selectedBank.classList.add('bg-yellow-200');
            tryMatch();
        }
    });

    function tryMatch() {
        if (selectedSystem && selectedBank) {
            const systemAmount = parseFloat(selectedSystem.dataset.amount);
            const bankAmount = parseFloat(selectedBank.dataset.amount);

            if (Math.abs(systemAmount - bankAmount) < 0.01 ) {
                const systemId = selectedSystem.dataset.id;
                const bankId = selectedBank.dataset.id;
                
                matchedPairs.push({ journal_item_id: systemId, statement_line_id: bankId });
                
                systemTransactions = systemTransactions.filter(item => item.id != systemId);
                bankTransactions = bankTransactions.filter(item => item.id != bankId);

                clearedBalance += systemAmount;
                selectedSystem = null;
                selectedBank = null;
                renderTables();
                updateSummary();
            } else {
                 alert("Amounts do not match!");
                 selectedSystem.classList.remove('bg-yellow-200');
                 selectedBank.classList.remove('bg-yellow-200');
                 selectedSystem = null;
                 selectedBank = null;
            }
        }
    }

    csvUpload.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const csv = event.target.result;
                const lines = csv.split(/\\r\\n|\\n/).slice(1);
                bankTransactions = lines.map((line, index) => {
                    const [date, description, amount] = line.split(',');
                    return { id: `csv-${index}`, date, description, amount: parseFloat(amount || 0) };
                }).filter(item => item && item.amount);
                
                uploadBox.classList.add('hidden');
                document.getElementById('bank-statement-table').classList.remove('hidden');
                renderTables();
            };
            reader.readAsText(file);
        }
    });

    finishButton.addEventListener('click', function() {
        if (confirm('Are you sure you want to finalize this reconciliation?')) {
            fetch('../api/save_reconciliation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    reconciliation_id: reconData.id,
                    matches: matchedPairs
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.href = 'bank_reconciliation.php';
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
    });

    let url = `../api/get_reconciliation_data.php?`;
    if(reconData.id > 0) {
        url += `id=${reconData.id}`;
    } else {
        url += `account_id=${reconData.accountId}&statement_start_date=${reconData.statementStartDate}&statement_date=${reconData.statementDate}&statement_balance=${reconData.statementBalance}`;
    }

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                reconData.id = data.reconciliation.id;
                container.dataset.reconciliationId = data.reconciliation.id;
                reconData.statementBalance = parseFloat(data.reconciliation.statement_balance);
                
                let startDate = reconData.statementStartDate || new Date(data.reconciliation.statement_date).toLocaleDateString();
                document.getElementById('statement-date-header').textContent = `Statement Period: ${startDate} to ${new Date(data.reconciliation.statement_date + 'T00:00:00').toLocaleDateString()}`;

                systemTransactions = data.unreconciled_items;
                renderTables();
                updateSummary();
            } else {
                alert('Error loading data: ' + data.message);
            }
        });
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>