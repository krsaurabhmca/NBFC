<?php
require_once 'includes/db.php';

$res = mysqli_query($conn, "SHOW COLUMNS FROM accounts LIKE 'aadhar_copy'");
if(mysqli_num_rows($res) == 0) {
    mysqli_query($conn, "ALTER TABLE accounts ADD COLUMN aadhar_copy VARCHAR(255) NULL AFTER created_at");
}

$res = mysqli_query($conn, "SHOW COLUMNS FROM accounts LIKE 'pan_copy'");
if(mysqli_num_rows($res) == 0) {
    mysqli_query($conn, "ALTER TABLE accounts ADD COLUMN pan_copy VARCHAR(255) NULL AFTER aadhar_copy");
}

$res = mysqli_query($conn, "SHOW COLUMNS FROM accounts LIKE 'cheque_copy'");
if(mysqli_num_rows($res) == 0) {
    mysqli_query($conn, "ALTER TABLE accounts ADD COLUMN cheque_copy VARCHAR(255) NULL AFTER pan_copy");
}

echo "Accounts table updated with document columns.";
?>
