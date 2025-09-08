<?php
// settings/system-configuration.php

// STEP 1: Include the core config and start the session first.
require_once __DIR__ . '/../config.php';

// STEP 2: Perform ALL form processing and potential redirects BEFORE any HTML is sent.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_settings'])) {
    // Handle checkboxes, as they won't be sent if unchecked
    $_POST['email_notifications_enabled'] = isset($_POST['email_notifications_enabled']) ? '1' : '0';
    $_POST['maintenance_mode'] = isset($_POST['maintenance_mode']) ? '1' : '0';
    $_POST['error_logging_enabled'] = isset($_POST['error_logging_enabled']) ? '1' : '0';

    $stmt = $conn->prepare("UPDATE scs_settings SET setting_value = ? WHERE setting_key = ?");
    $update_success = true;
    
    foreach ($_POST as $key => $value) {
        if ($key === 'save_settings') continue;
        
        $stmt->bind_param("ss", $value, $key);
        if (!$stmt->execute()) {
            $update_success = false;
            $message = "Error updating setting: " . $stmt->error;
            $message_type = 'error';
            break; 
        }
    }
    
    if ($update_success) {
        header("Location: system-configuration.php?success=1");
        exit();
    }
    $stmt->close();
}

// STEP 3: Now that all logic is done, include the header to start outputting the page.
require_once __DIR__ . '/../templates/header.php';

$page_title = "System Configuration - BizManager";
$timezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
$message = '';
$message_type = '';

// Check for a success message from the redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) {
        $message = "Configuration updated successfully!";
        $message_type = 'success';
    } elseif ($_GET['success'] == 'reset') {
        $message = "Application data has been successfully reset!";
        $message_type = 'success';
    }
}
if (isset($_GET['error'])) {
    $message = "An error occurred: " . htmlspecialchars($_GET['error']);
    $message_type = 'error';
}
?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Page Header -->
<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">System Configuration</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Settings
    </a>
</div>

<div class="glass-card p-8 max-w-4xl mx-auto">

    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="system-configuration.php" method="POST" class="space-y-8">
        
        <!-- General Settings Section -->
        <div>
            <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-300/50 pb-2 mb-4">General Settings</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="date_format" class="block text-sm font-medium text-gray-700">Date Format</label>
                    <select id="date_format" name="date_format" class="form-input mt-1 block w-full rounded-md p-3">
                        <?php 
                        $current_date_format = $app_config['date_format'] ?? 'd-m-Y';
                        $date_formats = ['d-m-Y' => date('d-m-Y'), 'm-d-Y' => date('m-d-Y'), 'Y-m-d' => date('Y-m-d'), 'F j, Y' => date('F j, Y')];
                        foreach ($date_formats as $format => $example) {
                            $selected = ($format === $current_date_format) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($format) . "' $selected>" . htmlspecialchars($example) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label for="timezone" class="block text-sm font-medium text-gray-700">Timezone</label>
                    <select id="timezone" name="timezone" class="form-input mt-1 block w-full rounded-md p-3">
                        <?php 
                        $current_timezone = $app_config['timezone'] ?? 'Asia/Dhaka';
                        foreach ($timezones as $timezone) {
                            $selected = ($timezone === $current_timezone) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($timezone) . "' $selected>" . htmlspecialchars($timezone) . "</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- SMTP Settings Section -->
        <div>
            <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-300/50 pb-2 mb-4">SMTP Email Settings</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="smtp_host" class="block text-sm font-medium text-gray-700">SMTP Host</label>
                    <input type="text" name="smtp_host" id="smtp_host" placeholder="e.g., smtp.gmail.com" value="<?php echo htmlspecialchars($app_config['smtp_host'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                </div>
                <div>
                    <label for="smtp_port" class="block text-sm font-medium text-gray-700">SMTP Port</label>
                    <input type="text" name="smtp_port" id="smtp_port" placeholder="e.g., 587" value="<?php echo htmlspecialchars($app_config['smtp_port'] ?? '587'); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                </div>
                 <div>
                    <label for="smtp_user" class="block text-sm font-medium text-gray-700">SMTP Username</label>
                    <input type="text" name="smtp_user" id="smtp_user" placeholder="Your email address" value="<?php echo htmlspecialchars($app_config['smtp_user'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                </div>
                 <div>
                    <label for="smtp_pass" class="block text-sm font-medium text-gray-700">SMTP Password</label>
                    <input type="password" name="smtp_pass" id="smtp_pass" placeholder="Your email password or app password" value="<?php echo htmlspecialchars($app_config['smtp_pass'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                </div>
            </div>
             <div class="mt-6">
                <label for="email_notifications_enabled" class="block text-sm font-medium text-gray-700">Email Notifications</label>
                <div class="mt-2 p-4 bg-white/50 rounded-lg">
                    <label class="flex items-center">
                        <input type="checkbox" name="email_notifications_enabled" value="1" 
                               class="rounded h-4 w-4 text-indigo-600 focus:ring-indigo-500"
                               <?php if (!empty($app_config['email_notifications_enabled']) && $app_config['email_notifications_enabled'] == '1') echo 'checked'; ?>>
                        <span class="ml-3 text-sm text-gray-700">Enable system-wide email notifications</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end pt-8 border-t border-gray-200/50">
            <button type="submit" name="save_settings" class="inline-flex justify-center py-2 px-6 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                Save Changes
            </button>
        </div>
    </form>

    <!-- System Administration Section -->
    <div class="mt-8">
        <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-300/50 pb-2 mb-4">System Administration</h3>
        <div class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700">Maintenance Mode</label>
                <div class="mt-2 p-4 bg-white/50 rounded-lg">
                    <label class="flex items-center">
                        <input type="checkbox" name="maintenance_mode" value="1" 
                               class="rounded h-4 w-4 text-red-600 focus:ring-red-500"
                               <?php if (!empty($app_config['maintenance_mode']) && $app_config['maintenance_mode'] == '1') echo 'checked'; ?>>
                        <span class="ml-3 text-sm text-gray-700">Enable Maintenance Mode (Only Super Admins can log in)</span>
                    </label>
                </div>
            </div>
             <div>
                <label class="block text-sm font-medium text-gray-700">Error Logging</label>
                <div class="mt-2 p-4 bg-white/50 rounded-lg">
                    <label class="flex items-center">
                        <input type="checkbox" name="error_logging_enabled" value="1" 
                               class="rounded h-4 w-4 text-red-600 focus:ring-red-500"
                               <?php if (!empty($app_config['error_logging_enabled']) && $app_config['error_logging_enabled'] == '1') echo 'checked'; ?>>
                        <span class="ml-3 text-sm text-gray-700">Enable Error Display (For debugging only. Disable on a live server.)</span>
                    </label>
                </div>
            </div>
             <div>
                <label class="block text-sm font-medium text-gray-700">Database Backup</label>
                <div class="mt-2">
                    <a href="../backup.php" class="inline-block px-6 py-2 bg-green-600 text-white font-semibold rounded-lg shadow-sm hover:bg-green-700 transition-colors">
                        Download Database Backup
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- NEW: System Reset Section (Only for Super Admins) -->
    <?php if ($_SESSION['user_role_id'] == 1): ?>
    <div class="mt-8 pt-6 border-t border-red-300/50">
        <h3 class="text-lg font-semibold text-red-600">Danger Zone</h3>
        <div class="mt-4 p-4 bg-red-50/80 rounded-lg flex items-start justify-between">
            <div>
                <p class="font-semibold">Reset Application Data</p>
                <p class="text-sm text-red-700">This will permanently delete all transactional data (sales, customers, products, etc.) and all users except for Super Admins. This action cannot be undone.</p>
            </div>
            <form id="reset-form" action="reset_app.php" method="POST" class="ml-4">
                <input type="hidden" name="confirm_reset" value="true">
                <button type="button" id="reset-button" class="px-4 py-2 bg-red-600 text-white font-semibold rounded-lg shadow-sm hover:bg-red-700 transition-colors whitespace-nowrap">
                    Reset Application
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const resetButton = document.getElementById('reset-button');
    if (resetButton) {
        resetButton.addEventListener('click', function() {
            const firstConfirm = confirm("DANGER: You are about to delete ALL transactional data. This action is irreversible. Are you absolutely sure you want to continue?");
            if (firstConfirm) {
                const secondConfirm = prompt("To confirm this action, please type 'RESET' in the box below.");
                if (secondConfirm === 'RESET') {
                    document.getElementById('reset-form').submit();
                } else {
                    alert('The reset was cancelled. You did not type "RESET" correctly.');
                }
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
