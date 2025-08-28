<?php
// pos/start-session.php

// STEP 1: All PHP logic and potential redirects must come first.
require_once __DIR__ . '/../config.php';

if (!check_permission('POS', 'create')) {
    die('You do not have permission to access the POS system.');
}

$page_title = "Start POS Session - BizManager";
$user_id = $_SESSION['user_id'];
$location_id = $_SESSION['location_id'] ?? null; // Use null coalescing for safety

// Check for an already active session for this user
$active_session_stmt = $conn->prepare("SELECT id FROM scs_pos_sessions WHERE user_id = ? AND status = 'Active'");
$active_session_stmt->bind_param("i", $user_id);
$active_session_stmt->execute();
$active_session_result = $active_session_stmt->get_result();
if ($active_session_result->num_rows > 0) {
    // This redirect will now work because it's before any HTML output
    header("Location: index.php"); 
    exit();
}
$active_session_stmt->close();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opening_balance = (float)$_POST['opening_balance'];

    if (!is_numeric($_POST['opening_balance'])) {
        $message = "Opening balance must be a valid number.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO scs_pos_sessions (location_id, user_id, opening_balance, status) VALUES (?, ?, ?, 'Active')");
        $stmt->bind_param("iid", $location_id, $user_id, $opening_balance);
        if ($stmt->execute()) {
            $new_session_id = $conn->insert_id;
            $_SESSION['pos_session_id'] = $new_session_id;
            log_activity('POS_SESSION_START', "User started new POS session #" . $new_session_id, $conn);
            // This redirect will also work now
            header("Location: index.php");
            exit();
        } else {
            $message = "Failed to start a new session. Please try again.";
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// If the user does not have a location assigned, they can't use the POS.
if (!$location_id) {
     // We can't use the header yet, so we die with a simple, clean message.
     die('<div style="font-family: sans-serif; text-align: center; padding: 50px;"><h2>Location Not Set</h2><p>Your user account is not assigned to a specific location. Please contact an administrator to have your profile updated.</p><a href="../dashboard.php">Back to Dashboard</a></div>');
}


// STEP 2: Now that all logic is complete, include the header and start outputting HTML.
require_once __DIR__ . '/../templates/header.php';
?>
<title><?php echo htmlspecialchars($page_title); ?></title>

<div class="flex flex-col items-center justify-center h-full">
    <div class="glass-card p-8 md:p-12 text-center w-full max-w-md">
        <div class="mx-auto mb-6 p-4 bg-indigo-100/50 rounded-full w-24 h-24 flex items-center justify-center backdrop-blur-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <h2 class="text-3xl font-bold text-gray-800 mb-2">Start Your Session</h2>
        <p class="text-gray-600 max-w-sm mb-8">
            Please enter the opening cash balance in your register to begin.
        </p>

        <?php if (!empty($message)): ?>
            <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'error' ? 'bg-red-100/80 text-red-800' : 'bg-green-100/80 text-green-800'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="start-session.php" method="POST">
             <div>
                <label for="opening_balance" class="sr-only">Opening Balance</label>
                <div class="relative rounded-md shadow-sm">
                     <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <span class="text-gray-500 sm:text-sm"><?php echo htmlspecialchars($app_config['currency_symbol']); ?></span>
                    </div>
                    <input type="number" step="0.01" name="opening_balance" id="opening_balance" class="form-input block w-full rounded-md border-gray-300 pl-7 pr-12 text-center text-lg p-4" placeholder="0.00" required>
                </div>
            </div>
            <button type="submit" class="mt-6 w-full inline-block px-6 py-4 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700 transition-colors">
                Begin Session
            </button>
        </form>
         <a href="../dashboard.php" class="mt-4 inline-block text-sm text-gray-600 hover:text-indigo-600">
            &larr; Back to Dashboard
        </a>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>