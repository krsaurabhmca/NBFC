<?php
// cron/daily_processor.php
// This file is meant to be run via CLI cron job daily at 12:00 AM.
// e.g., php c:/xampp/htdocs/nbfc/cron/daily_processor.php

// Force script to run without timeouts
set_time_limit(0);

// Emulate CLI environment if running from browser for demo purposes
$is_cli = (php_sapi_name() === 'cli');

require_once __DIR__ . '/../includes/db.php';

function logCron($msg) {
    global $is_cli;
    $time = date('Y-m-d H:i:s');
    if($is_cli) {
        echo "[$time] $msg\n";
    } else {
        echo "<div style='font-family: monospace; border-bottom: 1px solid #ddd; padding: 5px; margin-bottom: 5px;'>[$time] $msg</div>";
    }
}

logCron("STARTING DAILY PROCESSOR...");

// 1. Calculate Daily Interest for Savings Accounts
logCron("Processing Savings Interest...");

$sql_savings = "SELECT a.id, a.current_balance, s.interest_rate, a.interest_last_calculated 
                FROM accounts a 
                JOIN schemes s ON a.scheme_id = s.id 
                WHERE a.account_type = 'Savings' AND a.status = 'active' AND a.current_balance > 0";
                
$savings_accs = mysqli_query($conn, $sql_savings);
$savings_count = 0;
$today = date('Y-m-d');

while($acc = mysqli_fetch_assoc($savings_accs)) {
    $last_date = $acc['interest_last_calculated'] ?: $today;
    $days_elapsed = (strtotime($today) - strtotime($last_date)) / (60 * 60 * 24);
    
    if($days_elapsed >= 1) { 
        // 1.1 Calculate Daily Interest (Accrual Only)
        $daily_interest = ($acc['current_balance'] * $acc['interest_rate'] * $days_elapsed) / 36500;
        $daily_interest = round($daily_interest, 2);
        
        $acc_id = $acc['id'];
        
        if($daily_interest > 0) {
            // Update ONLY interest_accrued and last calculation date
            mysqli_query($conn, "UPDATE accounts SET interest_accrued = interest_accrued + $daily_interest, interest_last_calculated = '$today' WHERE id = $acc_id");
            logCron("Accrued ₹$daily_interest interest for Account ID: $acc_id ($days_elapsed days)");
        } else {
            mysqli_query($conn, "UPDATE accounts SET interest_last_calculated = '$today' WHERE id = $acc_id");
        }
        $savings_count++;
    }
}
logCron("Evaluated and accrued daily interest for $savings_count Savings Accounts.");

// 1.2 Quarterly Interest Posting (Credit to Balance)
// Standard Quarters: Jan 1st, Apr 1st, Jul 1st, Oct 1st
$quarter_months = ['01-01', '04-01', '07-01', '10-01'];
$is_quarter_day = in_array(date('m-d'), $quarter_months);

// FOR TESTING/DEMO: If user runs this today and wants to see it, we might need a manual trigger.
// But for standard logic, we check the date.
if($is_quarter_day) {
    logCron("QUARTERLY DAY DETECTED: Posting accrued interest to balances...");
    $posting_sql = "SELECT id, interest_accrued, current_balance FROM accounts WHERE account_type = 'Savings' AND interest_accrued > 0";
    $posting_res = mysqli_query($conn, $posting_sql);
    $post_count = 0;
    
    while($p_acc = mysqli_fetch_assoc($posting_res)) {
        $id = $p_acc['id'];
        $amt = $p_acc['interest_accrued'];
        $new_bal = $p_acc['current_balance'] + $amt;
        
        // 1. Update balance and reset accrued
        mysqli_query($conn, "UPDATE accounts SET current_balance = $new_bal, interest_accrued = 0 WHERE id = $id");
        
        // 2. Create Transaction
        $txn_id = 'INT-Q-' . time() . rand(10,99) . $id;
        $now_time = date('Y-m-d H:i:s');
        mysqli_query($conn, "INSERT INTO transactions (transaction_id, account_id, transaction_type, amount, balance_after, description, transaction_date, created_by) 
                             VALUES ('$txn_id', $id, 'Interest', $amt, $new_bal, 'Quarterly Savings Interest Credit (Consolidated)', '$now_time', 1)");
                             
        $post_count++;
    }
    logCron("Successfully posted quarterly interest to $post_count Savings Accounts.");
} else {
    logCron("Not a quarterly day. Total accrued interest remains pending in 'interest_accrued' column.");
}

// 2. Identify and Fine Overdue Loan Accounts (Unified Logic)
logCron("Synchronizing Loan Delinquencies...");
$loan_acc_res = mysqli_query($conn, "SELECT id FROM accounts WHERE account_type = 'Loan' AND status IN ('active', 'defaulted')");
$loan_sync_count = 0;
while($l_acc = mysqli_fetch_assoc($loan_acc_res)) {
    // This function handles both status updates (Pending -> Overdue) and Fixed Fine application
    calculateAndUpdateFines($conn, $l_acc['id'], $today);
    $loan_sync_count++;
}
logCron("Successfully synchronized delinquency states for $loan_sync_count loan portfolios.");

// 3. Mark Matured FD/RD/MIS accounts
logCron("Checking Term Deposits for Maturity...");
$mat_tys = ['FD', 'RD', 'MIS'];
$sql_mat = "SELECT id, account_no FROM accounts 
            WHERE account_type IN ('FD', 'RD', 'MIS') AND status = 'active' AND maturity_date <= '$today'";

$maturing_accs = mysqli_query($conn, $sql_mat);
$mat_count = 0;

while($m_acc = mysqli_fetch_assoc($maturing_accs)) {
    $id = $m_acc['id'];
    mysqli_query($conn, "UPDATE accounts SET status = 'matured' WHERE id = $id");
    // (Actual interest compounding math belongs here in full scale system)
    $mat_count++;
}
logCron("Marked $mat_count accounts as matured.");

logCron("DAILY PROCESSOR COMPLETED SUCCESSFULLY.");

// --- NESTED SECTION: ADVISOR WALLET INTEREST ENGINE ---

// 4. Daily Advisor Wallet Interest Accrual
logCron("Processing Advisor Wallet Accruals...");
$wallet_rate = (float)getSetting($conn, 'wallet_interest_rate') ?: 4.0;
$today = date('Y-m-d');

$sql_wallets = "SELECT id, wallet_balance, wallet_interest_accrued, wallet_interest_last_calculated FROM users WHERE role = 'advisor' AND wallet_balance > 0";
$wallet_res = mysqli_query($conn, $sql_wallets);
$wallet_acc_count = 0;

while($u = mysqli_fetch_assoc($wallet_res)) {
    $last_calc = $u['wallet_interest_last_calculated'] ?: $today;
    $days_passed = (strtotime($today) - strtotime($last_calc)) / (60 * 60 * 24);
    
    if($days_passed >= 1) {
        $daily_yield = ($u['wallet_balance'] * $wallet_rate * $days_passed) / 36500;
        $daily_yield = round($daily_yield, 4); // high precision for daily microscopic interest
        
        if($daily_yield > 0) {
            mysqli_query($conn, "UPDATE users SET wallet_interest_accrued = wallet_interest_accrued + $daily_yield, wallet_interest_last_calculated = '$today' WHERE id = " . $u['id']);
        } else {
            mysqli_query($conn, "UPDATE users SET wallet_interest_last_calculated = '$today' WHERE id = " . $u['id']);
        }
        $wallet_acc_count++;
    }
}
logCron("Accrued daily wallet interest for $wallet_acc_count Advisors.");

// 5. Monthly Advisor Wallet Interest Posting
// Runs on the 1st of every month
if(date('d') == '01') {
    logCron("MONTHLY START DETECTED: Posting wallet interest credits...");
    $credit_res = mysqli_query($conn, "SELECT id, wallet_interest_accrued, wallet_balance FROM users WHERE role = 'advisor' AND wallet_interest_accrued >= 0.01");
    $credit_count = 0;
    
    while($u_cred = mysqli_fetch_assoc($credit_res)) {
        $u_id = $u_cred['id'];
        $int_amount = round($u_cred['wallet_interest_accrued'], 2);
        $final_bal = $u_cred['wallet_balance'] + $int_amount;
        
        mysqli_query($conn, "START TRANSACTION");
        // 1. Update User Balance
        mysqli_query($conn, "UPDATE users SET wallet_balance = $final_bal, wallet_interest_accrued = 0 WHERE id = $u_id");
        
        // 2. Insert Wallet Transaction
        $wallet_txn_id = 'INT-W-' . time() . rand(10,99) . $u_id;
        $sql_w_txn = "INSERT INTO wallet_transactions (user_id, transaction_type, amount, balance_after, reference_id, description, created_by) 
                      VALUES ($u_id, 'Interest', $int_amount, $final_bal, '$wallet_txn_id', 'Monthly Wallet Interest Credit', 1)";
        mysqli_query($conn, $sql_w_txn);
        
        mysqli_query($conn, "COMMIT");
        $credit_count++;
    }
    logCron("Successfully credited interest to $credit_count Advisor Wallets.");
}
?>
