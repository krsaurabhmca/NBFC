<?php
require_once 'includes/db.php';

// Create Branches Table
$sql1 = "CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_name VARCHAR(100) NOT NULL,
    branch_code VARCHAR(20) UNIQUE NOT NULL,
    address TEXT,
    contact_no VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $sql1);

// Add branch_id to key tables if not exists
$tables = ['users', 'members', 'accounts'];
foreach($tables as $table) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE 'branch_id'");
    if(mysqli_num_rows($res) == 0) {
        mysqli_query($conn, "ALTER TABLE $table ADD COLUMN branch_id INT DEFAULT 1 AFTER id");
    }
}

// Insert Default Branch
$res = mysqli_query($conn, "SELECT id FROM branches LIMIT 1");
if(mysqli_num_rows($res) == 0) {
    mysqli_query($conn, "INSERT INTO branches (branch_name, branch_code, address) VALUES ('Main Branch', 'B001', 'Head Office')");
}

echo "Branch Management Infrastructure Initialized Successfully.";
?>
