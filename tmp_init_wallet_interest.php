<?php
require_once 'includes/db.php';

echo "Initializing Advisor Wallet Interest System...\n";

// Add columns to users table
$queries = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS wallet_interest_accrued DECIMAL(15,2) DEFAULT 0.00 AFTER wallet_balance",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS wallet_interest_last_calculated DATE DEFAULT NULL AFTER wallet_interest_accrued",
    "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('wallet_interest_rate', '4.0')",
];

foreach ($queries as $q) {
    if(mysqli_query($conn, $q)) {
        echo "Executed: " . substr($q, 0, 50) . "...\n";
    } else {
        echo "FAILED: $q. Error: " . mysqli_error($conn) . "\n";
    }
}

echo "Initialization Complete!\n";
?>
