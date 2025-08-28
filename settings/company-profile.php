<?php
// settings/company-profile.php

// Go up one level to include the global header.
require_once __DIR__ . '/../templates/header.php';

// Set the title for this specific page.
$page_title = "Company Profile - BizManager";

// --- List of Currencies for the Dropdown ---
// An array to hold common currencies [CODE => Symbol, Name]
$currencies = [
    'BDT' => ['৳', 'Bangladeshi Taka'],
    'USD' => ['$', 'United States Dollar'],
    'EUR' => ['€', 'Euro'],
    'GBP' => ['£', 'British Pound Sterling'],
    'INR' => ['₹', 'Indian Rupee'],
    'JPY' => ['¥', 'Japanese Yen'],
    'AUD' => ['$', 'Australian Dollar'],
    'CAD' => ['$', 'Canadian Dollar'],
    'CHF' => ['Fr', 'Swiss Franc'],
    'CNY' => ['¥', 'Chinese Yuan'],
];


// Initialize variables
$message = '';
$message_type = '';
$settings = [];

// --- FETCH CURRENT SETTINGS ---
$result = $conn->query("SELECT setting_key, setting_value FROM scs_settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Prepare the update statement once.
    $stmt = $conn->prepare("UPDATE scs_settings SET setting_value = ? WHERE setting_key = ?");
    
    $update_success = true;
    
    // Loop through the submitted POST data and update each setting.
    foreach ($_POST as $key => $value) {
        // Skip non-setting fields (like the submit button).
        if ($key === 'save_settings') continue;
        
        $stmt->bind_param("ss", $value, $key);
        if (!$stmt->execute()) {
            $update_success = false;
            $message = "Error updating setting: " . $stmt->error;
            $message_type = 'error';
            break; // Exit the loop on first error.
        }
    }
    
    if ($update_success) {
        $message = "Settings updated successfully!";
        $message_type = 'success';
        
        // --- RE-FETCH SETTINGS to show updated values ---
        $result = $conn->query("SELECT setting_key, setting_value FROM scs_settings");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
    
    $stmt->close();
}
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Page Header -->
<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Company Profile</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Settings
    </a>
</div>

<!-- Company Profile Form -->
<div class="glass-card p-8">

    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="company-profile.php" method="POST" class="space-y-6">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="company_name" class="block text-sm font-medium text-gray-700">Company Name</label>
                <input type="text" name="company_name" id="company_name" value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md shadow-sm p-3" required>
            </div>
            <div>
                <label for="company_slogan" class="block text-sm font-medium text-gray-700">Company Slogan / Tagline</label>
                <input type="text" name="company_slogan" id="company_slogan" value="<?php echo htmlspecialchars($settings['company_slogan'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md shadow-sm p-3">
            </div>
        </div>

        <div>
            <label for="company_logo_url" class="block text-sm font-medium text-gray-700">Company Logo URL</label>
            <input type="text" name="company_logo_url" id="company_logo_url" placeholder="https://example.com/logo.png" value="<?php echo htmlspecialchars($settings['company_logo_url'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md shadow-sm p-3">
        </div>

        <div>
            <label for="company_address" class="block text-sm font-medium text-gray-700">Company Address</label>
            <textarea name="company_address" id="company_address" rows="3" class="form-input mt-1 block w-full rounded-md shadow-sm p-3"><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="company_email" class="block text-sm font-medium text-gray-700">Company Email</label>
                <input type="email" name="company_email" id="company_email" value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md shadow-sm p-3">
            </div>
            <div>
                <label for="company_phone" class="block text-sm font-medium text-gray-700">Company Phone</label>
                <input type="text" name="company_phone" id="company_phone" value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md shadow-sm p-3">
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="company_website" class="block text-sm font-medium text-gray-700">Website</label>
                <input type="text" name="company_website" id="company_website" placeholder="https://example.com" value="<?php echo htmlspecialchars($settings['company_website'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md shadow-sm p-3">
            </div>
            <div>
                <label for="company_tax_id" class="block text-sm font-medium text-gray-700">Tax ID / BIN</label>
                <input type="text" name="company_tax_id" id="company_tax_id" value="<?php echo htmlspecialchars($settings['company_tax_id'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md shadow-sm p-3">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2">
                <label for="system_currency" class="block text-sm font-medium text-gray-700">System Currency</label>
                <select id="system_currency" name="system_currency" class="form-input mt-1 block w-full pl-3 pr-10 py-3 text-base focus:outline-none focus:border-indigo-500 sm:text-sm rounded-md" required>
                    <?php
                    $current_currency = $settings['system_currency'] ?? 'BDT';
                    foreach ($currencies as $code => $details) {
                        $selected = ($code === $current_currency) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($code) . "' $selected>" . htmlspecialchars($details[1] . " ($code)") . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="currency_symbol" class="block text-sm font-medium text-gray-700">Currency Symbol</label>
                <input type="text" name="currency_symbol" id="currency_symbol" value="<?php echo htmlspecialchars($settings['currency_symbol'] ?? '৳'); ?>" class="form-input mt-1 block w-full rounded-md shadow-sm p-3" required>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end pt-4">
            <button type="submit" name="save_settings" class="inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Save Changes
            </button>
        </div>
    </form>
</div>

<?php
// Go up one level to include the global footer.
require_once __DIR__ . '/../templates/footer.php';
?>