<?php
// crm/pipeline.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('CRM', 'view')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Sales Pipeline - BizManager";

// --- Define the stages of your sales pipeline ---
$pipeline_stages = ['Qualification', 'Needs Analysis', 'Proposal Sent', 'Negotiation', 'Closed Won', 'Closed Lost'];

// --- DATA FETCHING ---
$opportunities_result = $conn->query("
    SELECT 
        o.*, 
        c.customer_name,
        u.full_name as assigned_user_name
    FROM scs_opportunities o
    JOIN scs_customers c ON o.customer_id = c.id
    LEFT JOIN scs_users u ON o.assigned_to = u.id
    ORDER BY o.closing_date ASC
");

// Organize opportunities into an array keyed by their stage
$opportunities_by_stage = [];
foreach ($pipeline_stages as $stage) {
    $opportunities_by_stage[$stage] = [];
}

while ($row = $opportunities_result->fetch_assoc()) {
    if (isset($opportunities_by_stage[$row['stage']])) {
        $opportunities_by_stage[$row['stage']][] = $row;
    }
}

// --- Calculate totals for each stage ---
$stage_totals = [];
foreach($pipeline_stages as $stage) {
    $stage_totals[$stage] = ['count' => 0, 'value' => 0.00];
}

foreach($opportunities_by_stage as $stage => $opportunities) {
    if (isset($stage_totals[$stage])) {
        $stage_totals[$stage]['count'] = count($opportunities);
        foreach($opportunities as $opp) {
            $stage_totals[$stage]['value'] += (float)$opp['estimated_value'];
        }
    }
}

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h2 class="text-2xl font-semibold text-gray-800">Sales Pipeline</h2>
        <p class="text-gray-600 mt-1">Drag and drop opportunities to update their stage.</p>
    </div>
    <a href="index.php" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to CRM
    </a>
</div>

<div class="w-full overflow-x-auto pb-4">
    <div id="kanban-board" class="flex space-x-6">
        <?php foreach ($pipeline_stages as $stage): ?>
            <div class="w-80 flex-shrink-0">
                <div class="bg-gray-100/50 rounded-xl p-4">
                    <h3 class="font-semibold text-gray-800 mb-1"><?php echo htmlspecialchars($stage); ?></h3>
                    <div class="text-xs text-gray-500 mb-4">
                        <span class="deal-count"><?php echo $stage_totals[$stage]['count']; ?></span> Deals
                        <span class="mx-1">|</span>
                        <?php echo htmlspecialchars($app_config['currency_symbol']); ?><span class="deal-value"><?php echo number_format($stage_totals[$stage]['value'], 2); ?></span>
                    </div>
                    <div class="space-y-4 min-h-[60vh] stage-column" data-stage-name="<?php echo htmlspecialchars($stage); ?>">
                        <?php if (!empty($opportunities_by_stage[$stage])): ?>
                            <?php foreach ($opportunities_by_stage[$stage] as $opp): ?>
                                <div class="glass-card p-4 rounded-lg shadow-sm hover:shadow-md transition-shadow cursor-grab" 
                                     data-opportunity-id="<?php echo $opp['id']; ?>" 
                                     data-value="<?php echo htmlspecialchars($opp['estimated_value']); ?>">
                                     
                                    <h4 class="font-bold text-gray-900"><?php echo htmlspecialchars($opp['opportunity_name']); ?></h4>
                                    <p class="text-sm text-gray-700 mt-1"><?php echo htmlspecialchars($opp['customer_name']); ?></p>
                                    <div class="mt-4 pt-4 border-t border-gray-200/50 flex justify-between items-center">
                                        <div>
                                            <p class="text-xs text-gray-500">Value</p>
                                            <p class="text-sm font-semibold text-green-600"><?php echo htmlspecialchars($app_config['currency_symbol'] . ' ' . number_format($opp['estimated_value'], 2)); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Close Date</p>
                                            <p class="text-sm font-semibold text-gray-800"><?php echo $opp['closing_date'] ? date($app_config['date_format'], strtotime($opp['closing_date'])) : 'N/A'; ?></p>
                                        </div>
                                    </div>
                                    <div class="mt-2 pt-2 border-t border-gray-200/50">
                                         <p class="text-xs text-gray-500">Assigned to: <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($opp['assigned_user_name'] ?? 'Unassigned'); ?></span></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stageColumns = document.querySelectorAll('.stage-column');

    /**
     * Recalculates and updates the deal count and total value for a given pipeline column.
     * @param {HTMLElement} columnEl - The column element (div.stage-column).
     */
    function updateColumnTotals(columnEl) {
        const cards = columnEl.querySelectorAll('.glass-card[data-value]');
        let totalValue = 0;
        cards.forEach(card => {
            totalValue += parseFloat(card.dataset.value) || 0;
        });

        // Find the count and value elements in the column's header
        const header = columnEl.parentElement;
        const countElement = header.querySelector('.deal-count');
        const valueElement = header.querySelector('.deal-value');
        
        if (countElement) {
            countElement.innerText = cards.length;
        }
        if (valueElement) {
            // Format to 2 decimal places with commas for thousands separator
            valueElement.innerText = totalValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }

    stageColumns.forEach(column => {
        new Sortable(column, {
            group: 'pipeline', // set both lists to same group
            animation: 150,
            onEnd: function (evt) {
                const itemEl = evt.item; // dragged HTMLElement
                const fromColumn = evt.from; // column card was dragged from
                const toColumn = evt.to;   // column card was dragged to
                
                const opportunityId = itemEl.dataset.opportunityId;
                const newStage = toColumn.dataset.stageName;

                // Call our API to update the database
                fetch('../api/update-opportunity-stage.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        opportunity_id: opportunityId,
                        new_stage: newStage
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // --- UPDATE TOTALS IN REAL-TIME ---
                        updateColumnTotals(fromColumn);
                        updateColumnTotals(toColumn);
                    } else {
                        // If the update fails, move the item back to its original column
                        fromColumn.appendChild(itemEl);
                        alert('Error updating stage: ' + data.message);
                    }
                })
                .catch(error => {
                    fromColumn.appendChild(itemEl);
                    alert('A network error occurred.');
                });
            },
        });
    });
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>