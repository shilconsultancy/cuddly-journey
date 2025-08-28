<?php
// settings/locations.php

require_once __DIR__ . '/../templates/header.php';

$page_title = "Manage Locations - BizManager";

// Initialize variables
$message = '';
$message_type = '';
$edit_mode = false;
$location_to_edit = ['id' => '', 'location_name' => '', 'location_type' => '', 'address' => '', 'phone' => '', 'manager_id' => ''];

// --- FORM PROCESSING: ADD or UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $location_name = trim($_POST['location_name']);
    $location_type = trim($_POST['location_type']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : NULL;
    $location_id = $_POST['location_id'];

    if (empty($location_name) || empty($location_type)) {
        $message = "Location Name and Type are required.";
        $message_type = 'error';
    } else {
        if (!empty($location_id)) {
            // --- UPDATE existing location ---
            $stmt = $conn->prepare("UPDATE scs_locations SET location_name = ?, location_type = ?, address = ?, phone = ?, manager_id = ? WHERE id = ?");
            $stmt->bind_param("ssssii", $location_name, $location_type, $address, $phone, $manager_id, $location_id);
            if ($stmt->execute()) {
                $message = "Location updated successfully!";
                $message_type = 'success';
            } else {
                $message = "Error updating location: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            // --- ADD new location ---
            $stmt = $conn->prepare("INSERT INTO scs_locations (location_name, location_type, address, phone, manager_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $location_name, $location_type, $address, $phone, $manager_id);
            if ($stmt->execute()) {
                $message = "Location added successfully!";
                $message_type = 'success';
            } else {
                $message = "Error adding location: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete'])) {
    $location_id_to_delete = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM scs_locations WHERE id = ?");
    $stmt->bind_param("i", $location_id_to_delete);
    if ($stmt->execute()) {
        $message = "Location deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting location: " . $stmt->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// --- HANDLE EDIT ---
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $location_id_to_edit = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM scs_locations WHERE id = ?");
    $stmt->bind_param("i", $location_id_to_edit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $location_to_edit = $result->fetch_assoc();
    }
    $stmt->close();
}


// --- DATA FETCHING for the page ---
// Fetch all locations with manager names using a LEFT JOIN
$locations_result = $conn->query("
    SELECT 
        l.*, 
        u.full_name as manager_name 
    FROM 
        scs_locations l
    LEFT JOIN 
        scs_users u ON l.manager_id = u.id
    ORDER BY 
        l.location_name ASC
");

// Fetch users who can be managers (e.g., Super Admins, Administrators, Shop Managers)
$managers_result = $conn->query("
    SELECT 
        u.id, 
        u.full_name 
    FROM 
        scs_users u
    JOIN 
        scs_roles r ON u.role_id = r.id
    WHERE 
        r.role_name IN ('Super Admin', 'Administrator', 'Shop Manager')
    ORDER BY
        u.full_name ASC
");

?>

<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Page Header -->
<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-semibold text-gray-800">Manage Locations</h2>
    <a href="index.php" class="px-4 py-2 bg-white/80 text-gray-700 rounded-lg shadow-sm hover:bg-white transition-colors">
        &larr; Back to Settings
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Add/Edit Location Form -->
    <div class="lg:col-span-1">
        <div class="glass-card p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4"><?php echo $edit_mode ? 'Edit Location' : 'Add New Location'; ?></h3>
            <form action="locations.php" method="POST" class="space-y-4">
                <input type="hidden" name="location_id" value="<?php echo htmlspecialchars($location_to_edit['id']); ?>">
                <div>
                    <label for="location_name" class="block text-sm font-medium text-gray-700">Location Name</label>
                    <input type="text" name="location_name" id="location_name" value="<?php echo htmlspecialchars($location_to_edit['location_name']); ?>" class="form-input mt-1 block w-full rounded-md p-3" required>
                </div>
                <div>
                    <label for="location_type" class="block text-sm font-medium text-gray-700">Location Type</label>
                    <select name="location_type" id="location_type" class="form-input mt-1 block w-full rounded-md p-3" required>
                        <option value="">Select a type</option>
                        <option value="Shop" <?php if ($location_to_edit['location_type'] == 'Shop') echo 'selected'; ?>>Shop</option>
                        <option value="Warehouse" <?php if ($location_to_edit['location_type'] == 'Warehouse') echo 'selected'; ?>>Warehouse</option>
                        <option value="Office" <?php if ($location_to_edit['location_type'] == 'Office') echo 'selected'; ?>>Office</option>
                        <option value="Department" <?php if ($location_to_edit['location_type'] == 'Department') echo 'selected'; ?>>Department</option>
                    </select>
                </div>
                 <div>
                    <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                    <textarea name="address" id="address" rows="3" class="form-input mt-1 block w-full rounded-md p-3"><?php echo htmlspecialchars($location_to_edit['address']); ?></textarea>
                </div>
                 <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                    <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($location_to_edit['phone']); ?>" class="form-input mt-1 block w-full rounded-md p-3">
                </div>
                <div>
                    <label for="manager_id" class="block text-sm font-medium text-gray-700">Manager</label>
                    <select name="manager_id" id="manager_id" class="form-input mt-1 block w-full rounded-md p-3">
                        <option value="">None</option>
                        <?php while($manager = $managers_result->fetch_assoc()): ?>
                            <option value="<?php echo $manager['id']; ?>" <?php if ($location_to_edit['manager_id'] == $manager['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($manager['full_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="flex justify-end pt-2">
                    <?php if ($edit_mode): ?>
                        <a href="locations.php" class="bg-gray-200 py-2 px-4 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-300">Cancel</a>
                    <?php endif; ?>
                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <?php echo $edit_mode ? 'Update Location' : 'Add Location'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Locations List -->
    <div class="lg:col-span-2">
        <div class="glass-card p-6">
            <?php if (!empty($message)): ?>
                <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100/80 text-green-800' : 'bg-red-100/80 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="text-xs text-gray-800 uppercase bg-gray-50/50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Location</th>
                            <th scope="col" class="px-6 py-3">Type</th>
                            <th scope="col" class="px-6 py-3">Manager</th>
                            <th scope="col" class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $locations_result->fetch_assoc()): ?>
                        <tr class="bg-white/50 border-b border-gray-200/50">
                            <td class="px-6 py-4 font-medium">
                                <div class="font-semibold"><?php echo htmlspecialchars($row['location_name']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['phone']); ?></div>
                            </td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($row['location_type']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($row['manager_name'] ?? 'N/A'); ?></td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="locations.php?edit=<?php echo $row['id']; ?>" class="font-medium text-indigo-600 hover:underline">Edit</a>
                                <a href="locations.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this location?');" class="font-medium text-red-600 hover:underline">Delete</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>