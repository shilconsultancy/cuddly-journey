<?php
// config.php

// --- START SESSION ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Database Credentials ---
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'shil_biz_manager';

// --- Create Database Connection ---
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// --- Check Connection ---
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// --- Load All System Settings into a Global Array ---
$app_config = [];
$result = $conn->query("SELECT setting_key, setting_value FROM scs_settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $app_config[$row['setting_key']] = $row['setting_value'];
    }
}

// --- GLOBAL ERROR REPORTING (CONTROLLED BY DATABASE) ---
if (!empty($app_config['error_logging_enabled']) && $app_config['error_logging_enabled'] == '1') {
    // DEVELOPMENT MODE: Show all errors on the screen
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    // PRODUCTION MODE: Hide errors from users and log them to a file
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
    ini_set('log_errors', 1);
    // It's a good practice to define a specific error log file
    // ini_set('error_log', __DIR__ . '/error.log'); 
}


// --- Set the System Timezone Globally ---
if (!empty($app_config['timezone'])) {
    date_default_timezone_set($app_config['timezone']);
}

// --- Include Global Functions ---
require_once __DIR__ . '/functions.php';

?>