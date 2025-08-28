<?php
// backup.php

// Include the global config to get DB credentials and check session
require_once 'config.php';
session_start();

// --- SECURITY CHECK: Only Super Admins (role_id 1) can perform backups ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role_id'] != 1) {
    // Redirect to dashboard if not a super admin
    header("Location: dashboard.php");
    exit();
}

// --- Backup Logic ---
$tables = array();
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$return = '';

foreach ($tables as $table) {
    $result = $conn->query("SELECT * FROM $table");
    $num_fields = $result->field_count;
    
    $return .= 'DROP TABLE IF EXISTS ' . $table . ';';
    $row2 = $conn->query("SHOW CREATE TABLE $table")->fetch_row();
    $return .= "\n\n" . $row2[1] . ";\n\n";
    
    for ($i = 0; $i < $num_fields; $i++) {
        while ($row = $result->fetch_row()) {
            $return .= 'INSERT INTO ' . $table . ' VALUES(';
            for ($j = 0; $j < $num_fields; $j++) {
                $row[$j] = addslashes($row[$j]);
                $row[$j] = preg_replace("/\n/", "\\n", $row[$j]);
                if (isset($row[$j])) {
                    $return .= '"' . $row[$j] . '"';
                } else {
                    $return .= '""';
                }
                if ($j < ($num_fields - 1)) {
                    $return .= ',';
                }
            }
            $return .= ");\n";
        }
    }
    $return .= "\n\n\n";
}

// --- Force Download ---
$backup_file_name = 'bizmanager_backup_' . date("Y-m-d_H-i-s") . '.sql';
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $backup_file_name . '"');
echo $return;
exit();

?>