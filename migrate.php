<?php
/**
 * NBFC Core - Database Migration Utility
 * Use this to update existing live databases to the latest schema.
 */
require_once 'includes/db.php';

echo "<h2>Starting Database Migration...</h2><hr>";

$queries = [
    // 1. Branches Table
    "CREATE TABLE IF NOT EXISTS `branches` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `branch_name` varchar(100) NOT NULL,
      `branch_code` varchar(10) NOT NULL,
      `address` text NOT NULL,
      `status` enum('active','inactive') NOT NULL DEFAULT 'active',
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `branch_code` (`branch_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "INSERT IGNORE INTO `branches` (`id`, `branch_name`, `branch_code`, `address`) VALUES (1, 'Main Branch', 'HQ01', 'Head Office Central');",

    // 2. Add branch_id to users, members, accounts
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `branch_id` int(11) DEFAULT '1' AFTER `id`;",
    "ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `branch_id` int(11) DEFAULT '1' AFTER `id`;",
    "ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `branch_id` int(11) DEFAULT '1' AFTER `account_no`;",

    // 3. Member Assets
    "ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `photo_path` varchar(255) DEFAULT NULL AFTER `nominee_relation`;",
    "ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `signature_path` varchar(255) DEFAULT NULL AFTER `photo_path`;",

    // 4. Account Details
    "ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `interest_rate` decimal(5,2) DEFAULT '0.00' AFTER `tenure_months`;",
    "ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `emi_date` int(11) DEFAULT '5' AFTER `maturity_date`;",
    "ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `repayment_frequency` varchar(20) DEFAULT 'Monthly' AFTER `emi_date`;",
    "ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `repayment_day_1` int(11) DEFAULT '5' AFTER `repayment_frequency`;",
    "ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `repayment_day_2` int(11) DEFAULT NULL AFTER `repayment_day_1`;",
    "ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `referred_by` int(11) DEFAULT NULL AFTER `interest_accrued`;",
    "ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `disbursal_commission_percent` decimal(5,2) DEFAULT '0.00' AFTER `referred_by`;",
    "ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `collection_commission_percent` decimal(5,2) DEFAULT '0.00' AFTER `disbursal_commission_percent`;",
    "ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `loan_interest_type` enum('Flat','Reducing') DEFAULT 'Flat' AFTER `collection_commission_percent`;",
    "ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `aadhar_copy` varchar(255) DEFAULT NULL AFTER `loan_interest_type`;",
    "ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `pan_copy` varchar(255) DEFAULT NULL AFTER `aadhar_copy`;",
    "ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `cheque_copy` varchar(255) DEFAULT NULL AFTER `pan_copy`;",
    
    // Status update for approval workflow
    "ALTER TABLE `accounts` MODIFY COLUMN `status` enum('active','matured','closed','pre-closed','defaulted','pending_approval') NOT NULL DEFAULT 'active';",

    // 5. Transaction Cancellation Columns
    "ALTER TABLE `transactions` ADD COLUMN IF NOT EXISTS `status` enum('Success','Cancelled') DEFAULT 'Success' AFTER `description`;",
    "ALTER TABLE `transactions` ADD COLUMN IF NOT EXISTS `cancel_remarks` text AFTER `status`;",
    "ALTER TABLE `transactions` ADD COLUMN IF NOT EXISTS `cancelled_at` datetime DEFAULT NULL AFTER `cancel_remarks`;",
    "ALTER TABLE `transactions` ADD COLUMN IF NOT EXISTS `cancelled_by` int(11) DEFAULT NULL AFTER `cancelled_at`;",

    // 6. loan_schedules refinement
    "ALTER TABLE `loan_schedules` ADD COLUMN IF NOT EXISTS `payment_mode` varchar(50) DEFAULT NULL AFTER `paid_date`;",
    "ALTER TABLE `loan_schedules` ADD COLUMN IF NOT EXISTS `transaction_id` varchar(30) DEFAULT NULL AFTER `payment_mode`;",
    "ALTER TABLE `loan_schedules` ADD COLUMN IF NOT EXISTS `discount_amount` decimal(10,2) DEFAULT '0.00' AFTER `transaction_id`;",
    "ALTER TABLE `loan_schedules` ADD COLUMN IF NOT EXISTS `commission_amount` decimal(10,2) DEFAULT '0.00' AFTER `fine_amount`;",

    // 7. Commissions Table
    "CREATE TABLE IF NOT EXISTS `commissions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `account_id` int(11) NOT NULL,
      `amount` decimal(12,2) NOT NULL,
      `type` enum('Disbursal','Collection') NOT NULL,
      `reference_id` int(11) NOT NULL,
      `status` enum('Pending','Paid') DEFAULT 'Pending',
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      KEY `account_id` (`account_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // 8. Settings Table
    "CREATE TABLE IF NOT EXISTS `settings` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `setting_key` varchar(50) NOT NULL,
      `setting_value` text NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES ('company_name', 'NBFC Core Banking');",
    "INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES ('loan_only_mode', '1');"
];

foreach ($queries as $sql) {
    if (mysqli_query($conn, $sql)) {
        echo "<div style='color:green'>[SUCCESS] " . substr($sql, 0, 80) . "...</div>";
    } else {
        echo "<div style='color:red'>[ERROR] " . mysqli_error($conn) . " | SQL: " . substr($sql, 0, 50) . "</div>";
    }
}

echo "<hr><h3>Migration Completed. Please DELETE this file (migrate.php) for security.</h3>";
?>
