-- NBFC Core Banking Database Schema (Final Version)
-- Updated: 2026-04-19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+05:30";

-- --------------------------------------------------------
-- Table structure for table `branches`
-- --------------------------------------------------------
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_name` varchar(100) NOT NULL,
  `branch_code` varchar(10) NOT NULL,
  `address` text NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `branch_code` (`branch_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `branches` (`id`, `branch_name`, `branch_code`, `address`) VALUES
(1, 'Main Branch', 'HQ01', 'Head Office Central');

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) DEFAULT '1',
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `name` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `branch_id` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`id`, `branch_id`, `username`, `password`, `role`, `name`) VALUES
(1, 1, 'admin', '$2y$10$e.wXqH3oN/b4d4P1I4kZTe7v.U/MhO7fA1R7D3bZ2Q5xZ/Q3V6jO2', 'admin', 'System Administrator');

-- --------------------------------------------------------
-- Table structure for table `members`
-- --------------------------------------------------------
CREATE TABLE `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) DEFAULT '1',
  `member_no` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `dob` date NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `phone` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text NOT NULL,
  `aadhar_no` varchar(12) NOT NULL,
  `pan_no` varchar(10) DEFAULT NULL,
  `nominee_name` varchar(100) DEFAULT NULL,
  `nominee_relation` varchar(50) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_no` (`member_no`),
  UNIQUE KEY `aadhar_no` (`aadhar_no`),
  KEY `branch_id` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `schemes`
-- --------------------------------------------------------
CREATE TABLE `schemes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scheme_type` enum('Savings','Loan','FD','RD','MIS','DD') NOT NULL,
  `scheme_name` varchar(100) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `compounding_frequency` enum('Daily','Monthly','Quarterly','Half-Yearly','Yearly','Maturity') DEFAULT NULL,
  `minimum_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `penalty_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `pre_closure_penalty_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `schemes` (`scheme_type`, `scheme_name`, `interest_rate`, `compounding_frequency`) VALUES
('Savings', 'Regular Savings Account', 3.50, 'Quarterly'),
('Loan', 'Personal Loan', 15.00, 'Monthly');

-- --------------------------------------------------------
-- Table structure for table `accounts`
-- --------------------------------------------------------
CREATE TABLE `accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_no` varchar(20) NOT NULL,
  `branch_id` int(11) DEFAULT '1',
  `member_id` int(11) NOT NULL,
  `scheme_id` int(11) NOT NULL,
  `account_type` enum('Savings','Loan','FD','RD','MIS','DD') NOT NULL,
  `opening_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `current_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `principal_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `installment_amount` decimal(10,2) DEFAULT '0.00',
  `tenure_months` int(11) DEFAULT NULL,
  `interest_rate` decimal(5,2) DEFAULT '0.00',
  `opening_date` date NOT NULL,
  `maturity_date` date DEFAULT NULL,
  `emi_date` int(11) DEFAULT '5',
  `repayment_frequency` varchar(20) DEFAULT 'Monthly',
  `repayment_day_1` int(11) DEFAULT '5',
  `repayment_day_2` int(11) DEFAULT NULL,
  `interest_accrued` decimal(12,2) NOT NULL DEFAULT '0.00',
  `referred_by` int(11) DEFAULT NULL,
  `disbursal_commission_percent` decimal(5,2) DEFAULT '0.00',
  `collection_commission_percent` decimal(5,2) DEFAULT '0.00',
  `loan_interest_type` enum('Flat','Reducing') DEFAULT 'Flat',
  `aadhar_copy` varchar(255) DEFAULT NULL,
  `pan_copy` varchar(255) DEFAULT NULL,
  `cheque_copy` varchar(255) DEFAULT NULL,
  `status` enum('active','matured','closed','pre-closed','defaulted','pending_approval') NOT NULL DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_no` (`account_no`),
  KEY `member_id` (`member_id`),
  KEY `scheme_id` (`scheme_id`),
  KEY `branch_id` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `transactions`
-- --------------------------------------------------------
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(30) NOT NULL,
  `account_id` int(11) NOT NULL,
  `transaction_type` enum('Deposit','Withdrawal','Interest','Fine','EMI','Pre-Closure','Account-Open') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `balance_after` decimal(12,2) NOT NULL,
  `description` varchar(255) NOT NULL,
  `status` enum('Success','Cancelled') DEFAULT 'Success',
  `cancel_remarks` text,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `transaction_date` datetime NOT NULL,
  `created_by` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_id` (`transaction_id`),
  KEY `account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `loan_schedules`
-- --------------------------------------------------------
CREATE TABLE `loan_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `installment_no` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `emi_amount` decimal(10,2) NOT NULL,
  `principal_component` decimal(10,2) NOT NULL,
  `interest_component` decimal(10,2) NOT NULL,
  `status` enum('Pending','Paid','Overdue') NOT NULL DEFAULT 'Pending',
  `paid_date` date DEFAULT NULL,
  `payment_mode` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(30) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  `fine_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `commission_amount` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `commissions`
-- --------------------------------------------------------
CREATE TABLE `commissions` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `settings`
-- --------------------------------------------------------
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('company_name', 'NBFC Core Banking'),
('loan_only_mode', '1');

COMMIT;
