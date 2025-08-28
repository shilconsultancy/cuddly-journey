<?php
// db_connect.php

// --- Database Credentials ---
// Replace with your actual database credentials for your XAMPP environment.
$db_host = 'localhost';
$db_user = 'root'; // Default XAMPP username
$db_pass = '';     // Default XAMPP password is empty
$db_name = 'shil_biz_manager';

// --- Create Database Connection ---
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// --- Check Connection ---
// If the connection fails, stop the script and display an error.
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set the character set to utf8mb4 for full Unicode support.
$conn->set_charset("utf8mb4");

?>