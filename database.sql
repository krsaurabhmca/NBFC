-- NBFC Core Banking Database Schema

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+05:30";

--
-- Database: `nbfc`
--

-- --------------------------------------------------------

--
-- Table structure for table `users` (Admin/Staff)
--
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `name` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Default Admin User (Password: admin123)
--
INSERT INTO `users` (`username`, `password`, `role`, `name`) VALUES
('admin', '$2y$10$e.wXqH3oN/b4d4P1I4kZTe7v.U/MhO7fA1R7D3bZ2Q5xZ/Q3V6jO2', 'admin', 'System Administrator');

-- --------------------------------------------------------

--
-- Table structure for table `members` (Customers)
--
CREATE TABLE `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_no` varchar(20) NOT NULL, -- Auto generated MBR-XXXX
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
  `status` enum('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_no` (`member_no`),
  UNIQUE KEY `aadhar_no` (`aadhar_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `schemes` (Master Configuration)
--
CREATE TABLE `schemes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scheme_type` enum('Savings','Loan','FD','RD','MIS','DD') NOT NULL,
  `scheme_name` varchar(100) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL, -- Annual Interest Rate
  `compounding_frequency` enum('Daily','Monthly','Quarterly','Half-Yearly','Yearly','Maturity') DEFAULT NULL,
  `minimum_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `penalty_percent` decimal(5,2) NOT NULL DEFAULT '0.00', -- For delayed EMI/RD
  `pre_closure_penalty_percent` decimal(5,2) NOT NULL DEFAULT '0.00', -- Reduction in interest
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Default Schemes
INSERT INTO `schemes` (`scheme_type`, `scheme_name`, `interest_rate`, `compounding_frequency`, `minimum_amount`, `penalty_percent`, `pre_closure_penalty_percent`) VALUES
('Savings', 'Regular Savings Account', 3.50, 'Quarterly', 500.00, 0.00, 0.00),
('FD', '1 Year Fixed Deposit', 7.00, 'Maturity', 10000.00, 0.00, 1.00),
('RD', '12 Months Recurring Deposit', 6.50, 'Quarterly', 500.00, 2.00, 1.00),
('MIS', 'Monthly Income Scheme', 7.50, 'Monthly', 50000.00, 0.00, 2.00),
('DD', 'Daily Deposit Piggy Bank', 5.00, 'Yearly', 50.00, 0.00, 0.00),
('Loan', 'Personal Loan', 15.00, 'Monthly', 10000.00, 5.00, 2.00);

-- --------------------------------------------------------

--
-- Table structure for table `accounts` (Member Scheme Accounts)
--
CREATE TABLE `accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_no` varchar(20) NOT NULL, -- Auto generated ACC-XXXX
  `member_id` int(11) NOT NULL,
  `scheme_id` int(11) NOT NULL,
  `account_type` enum('Savings','Loan','FD','RD','MIS','DD') NOT NULL,
  `opening_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `current_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `principal_amount` decimal(12,2) NOT NULL DEFAULT '0.00', -- Initial deposit or Loan amount
  `installment_amount` decimal(10,2) DEFAULT '0.00', -- EMI / RD / DD amount
  `tenure_months` int(11) DEFAULT NULL,
  `opening_date` date NOT NULL,
  `maturity_date` date DEFAULT NULL,
  `interest_accrued` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','matured','closed','pre-closed','defaulted') NOT NULL DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_no` (`account_no`),
  KEY `member_id` (`member_id`),
  KEY `scheme_id` (`scheme_id`),
  CONSTRAINT `fk_accounts_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  CONSTRAINT `fk_accounts_scheme` FOREIGN KEY (`scheme_id`) REFERENCES `schemes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `transactions` (Ledger Entries)
--
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(30) NOT NULL, -- TXN-XXXXX
  `account_id` int(11) NOT NULL,
  `transaction_type` enum('Deposit','Withdrawal','Interest','Fine','EMI','Pre-Closure','Account-Open') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `balance_after` decimal(12,2) NOT NULL,
  `description` varchar(255) NOT NULL,
  `transaction_date` datetime NOT NULL,
  `created_by` int(11) NOT NULL, -- User ID who processed it
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_id` (`transaction_id`),
  KEY `account_id` (`account_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_txn_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `fk_txn_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `loan_schedules` (EMI Tracking)
--
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
  `fine_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `fk_loan_schedule_acc` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
