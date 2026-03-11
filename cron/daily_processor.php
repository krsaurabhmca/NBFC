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
    
    if($days_elapsed >= 1) { // Process daily or catch up missed days
        // Formula: (Balance * Rate) / (100 * 365) * Days
        $daily_interest = ($acc['current_balance'] * $acc['interest_rate'] * $days_elapsed) / 36500;
        $daily_interest = round($daily_interest, 2);
        
        $acc_id = $acc['id'];
        
        if($daily_interest > 0) {
            // Update account balance and last calculation date
            mysqli_query($conn, "UPDATE accounts SET current_balance = current_balance + $daily_interest, interest_accrued = interest_accrued + $daily_interest, interest_last_calculated = '$today' WHERE id = $acc_id");
            
            // Log as transaction (automatic credit)
            $txn_id = 'INT-' . time() . rand(10,99) . $acc_id;
            $now_time = date('Y-m-d H:i:s');
            $new_bal = $acc['current_balance'] + $daily_interest;
            mysqli_query($conn, "INSERT INTO transactions (transaction_id, account_id, transaction_type, amount, balance_after, description, transaction_date, created_by) 
                                 VALUES ('$txn_id', $acc_id, 'Interest', $daily_interest, $new_bal, 'Auto Savings Interest Credit for $days_elapsed days', '$now_time', 1)");
        } else {
            mysqli_query($conn, "UPDATE accounts SET interest_last_calculated = '$today' WHERE id = $acc_id");
        }
        $savings_count++;
    }
}
logCron("Evaluated and auto-credited interest for $savings_count Savings Accounts.");

// 2. Identify and Fine Overdue Loan Accounts
logCron("Checking Loan EMI Schedules for defaults...");
$today = date('Y-m-d');
$sql_loans = "SELECT ls.id, ls.emi_amount, s.penalty_percent, a.id as account_id 
              FROM loan_schedules ls 
              JOIN accounts a ON ls.account_id = a.id 
              JOIN schemes s ON a.scheme_id = s.id 
              WHERE ls.due_date < '$today' AND ls.status = 'Pending'";
              
$overdue_loans = mysqli_query($conn, $sql_loans);
$loan_fines_count = 0;

while($emi = mysqli_fetch_assoc($overdue_loans)) {
    // If penalty_percent > 0, apply fine
    if($emi['penalty_percent'] > 0) {
        $fine = ($emi['emi_amount'] * $emi['penalty_percent']) / 100;
        $sch_id = $emi['id'];
        
        // Update schedule
        mysqli_query($conn, "UPDATE loan_schedules SET status = 'Overdue', fine_amount = fine_amount + $fine WHERE id = $sch_id");
        
        // Add fine to account balance (increasing debit)
        $acc_id = $emi['account_id'];
        mysqli_query($conn, "UPDATE accounts SET current_balance = current_balance - $fine WHERE id = $acc_id");
        $loan_fines_count++;
    }
}
logCron("Applied fines to $loan_fines_count overdue loan EMIs.");

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
?>
