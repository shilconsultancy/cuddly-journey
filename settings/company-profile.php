<?php
// settings/company-profile.php

require_once __DIR__ . '/../templates/header.php';

if (!check_permission('Settings', 'edit')) {
    die('<div class="glass-card p-8 text-center">You do not have permission to access this page.</div>');
}

$page_title = "Company Profile - BizManager";
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    try {
        // Handle Logo Upload
        $logo_path = $app_config['company_logo_url']; // Keep old path by default

        if (isset($_FILES['company_logo_url']) && $_FILES['company_logo_url']['error'] == UPLOAD_ERR_OK) {
            $target_dir = __DIR__ . "/../uploads/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }

            // Delete the old logo if it exists and is not a placeholder/external URL
            if (!empty($logo_path) && strpos($logo_path, 'uploads/') === 0 && file_exists($target_dir . basename($logo_path))) {
                unlink($target_dir . basename($logo_path));
            }

            $file_ext = strtolower(pathinfo($_FILES["company_logo_url"]["name"], PATHINFO_EXTENSION));
            $unique_filename = 'logo_' . uniqid() . '.' . $file_ext;
            $target_file = $target_dir . $unique_filename;
            
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
            if (in_array($file_ext, $allowed_types) && $_FILES["company_logo_url"]["size"] <= 2000000) { // 2MB limit
                if (move_uploaded_file($_FILES["company_logo_url"]["tmp_name"], $target_file)) {
                    $logo_path = 'uploads/' . $unique_filename; // Set the new path
                } else {
                    throw new Exception("Sorry, there was a server error moving the uploaded file.");
                }
            } else {
                throw new Exception("Sorry, only JPG, PNG, SVG & GIF files under 2MB are allowed.");
            }
        }
        
        // Update all other text-based settings
        foreach ($_POST as $key => $value) {
            $stmt = $conn->prepare("UPDATE scs_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->bind_param("ss", $value, $key);
            $stmt->execute();
            $stmt->close();
        }

        // Specifically update the logo path in the database
        $stmt_logo = $conn->prepare("UPDATE scs_settings SET setting_value = ? WHERE setting_key = 'company_logo_url'");
        $stmt_logo->bind_param("s", $logo_path);
        $stmt_logo->execute();
        $stmt_logo->close();

        $conn->commit();
        $message = "Company profile updated successfully!";
        $message_type = 'success';

        // Refresh the config array to show new values immediately
        $app_config = get_company_details($conn);

    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}
?>
<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Company Profile</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Settings
    </a>
</div>

<div class="glass-card p-8">
    
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="company-profile.php" method="POST" enctype="multipart/form-data" class="space-y-6">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="company_name" class="block text-sm font-medium text-gray-700">Company Name</label>
                <input type="text" name="company_name" id="company_name" value="<?php echo htmlspecialchars($app_config['company_name'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
            </div>
            <div>
                <label for="company_slogan" class="block text-sm font-medium text-gray-700">Company Slogan</label>
                <input type="text" name="company_slogan" id="company_slogan" value="<?php echo htmlspecialchars($app_config['company_slogan'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
            </div>
        </div>

        <div>
            <label for="company_logo_upload" class="block text-sm font-medium text-gray-700">Company Logo</label>
            <div class="mt-2 flex items-center space-x-4">
                <img id="logo_preview" src="../<?php echo htmlspecialchars($app_config['company_logo_url'] ?? 'https://placehold.co/200x80/e2e8f0/e2e8f0'); ?>" alt="Current Logo" class="h-16 w-auto bg-gray-100 p-2 rounded-md object-contain">
                <input type="file" name="company_logo_url" id="company_logo_upload" class="form-input block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            </div>
            <p class="text-xs text-gray-500 mt-1">Upload a new logo to replace the current one. (PNG, JPG, SVG, GIF up to 2MB)</p>
        </div>

        <div class="border-t border-gray-200/50 pt-6"></div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="company_email" class="block text-sm font-medium text-gray-700">Email Address</label>
                <input type="email" name="company_email" id="company_email" value="<?php echo htmlspecialchars($app_config['company_email'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
            </div>
            <div>
                <label for="company_phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                <input type="text" name="company_phone" id="company_phone" value="<?php echo htmlspecialchars($app_config['company_phone'] ?? ''); ?>" class="form-input mt-1 block w-full rounded-md p-3">
            </div>
        </div>

        <div>
            <label for="company_address" class="block text-sm font-medium text-gray-700">Company Address</label>
            <textarea name="company_address" id="company_address" rows="3" class="form-input mt-1 block w-full rounded-md p-3"><?php echo htmlspecialchars($app_config['company_address'] ?? ''); ?></textarea>
        </div>

        <div class="flex justify-end pt-6 border-t border-gray-200/50">
            <button type="submit" class="ml-3 inline-flex justify-center py-2 px-6 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                Save Changes
            </button>
        </div>
    </form>
</div>

<script>
document.getElementById('company_logo_upload').addEventListener('change', function(event) {
    const [file] = event.target.files;
    if (file) {
        const preview = document.getElementById('logo_preview');
        preview.src = URL.createObjectURL(file);
        preview.onload = () => URL.revokeObjectURL(preview.src); // free memory
    }
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>