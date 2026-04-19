<?php
// includes/functions.php

function sanitize($conn, $input) {
    return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags(trim($input))));
}

function getSetting($conn, $key) {
    $key = sanitize($conn, $key);
    $res = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = '$key'");
    if($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return $row['setting_value'];
    }
    return '';
}

function generateSequenceNo($conn, $type_key, $table, $column) {
    // Dynamic Prefix mapping via settings
    $prefix_map = [
        'MEMBER' => getSetting($conn, 'prefix_member'),
        'SAVINGS' => getSetting($conn, 'prefix_savings'),
        'LOAN' => getSetting($conn, 'prefix_loan'),
        'FD' => getSetting($conn, 'prefix_fd'),
        'RD' => getSetting($conn, 'prefix_rd'),
        'MIS' => getSetting($conn, 'prefix_mis'),
        'DD' => getSetting($conn, 'prefix_dd')
    ];
    
    $prefix = isset($prefix_map[$type_key]) && !empty($prefix_map[$type_key]) ? $prefix_map[$type_key] : $type_key;

    $sql = "SELECT $column FROM $table WHERE $column LIKE '$prefix-%' ORDER BY id DESC LIMIT 1";
    $result = mysqli_query($conn, $sql);
    
    if($row = mysqli_fetch_assoc($result)) {
        $last_no = $row[$column];
        $parts = explode('-', $last_no);
        $num = (int)$parts[1];
        $num++;
        return $prefix . '-' . str_pad($num, 5, '0', STR_PAD_LEFT);
    } else {
        return $prefix . '-00001';
    }
}

function displayAlert() {
    if(isset($_SESSION['success'])) {
        echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert"><p>' . htmlspecialchars($_SESSION['success']) . '</p></div>';
        unset($_SESSION['success']);
    }
    if(isset($_SESSION['error'])) {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert"><p>' . htmlspecialchars($_SESSION['error']) . '</p></div>';
        unset($_SESSION['error']);
    }
}

function formatCurrency($amount) {
    return '₹ ' . number_format($amount, 2);
}

function getBranchWhere($table_alias = '', $is_first_condition = true) {
    if (($_SESSION['role'] ?? '') == 'admin') return "";
    $branch_id = (int)($_SESSION['branch_id'] ?? 0);
    $prefix = $table_alias ? "$table_alias." : "";
    $condition = " {$prefix}branch_id = $branch_id ";
    return $is_first_condition ? " WHERE $condition " : " AND $condition ";
}

function logAction($conn, $user_id, $action, $details = '') {
    // Create table if not exists (Ensure robustness)
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `system_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `action` varchar(100) NOT NULL,
        `details` text,
        `ip_address` varchar(45) NOT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $user_id = (int)$user_id;
    $action = mysqli_real_escape_string($conn, $action);
    $details = mysqli_real_escape_string($conn, $details);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $sql = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES ($user_id, '$action', '$details', '$ip')";
    mysqli_query($conn, $sql) or die(mysqli_error($conn));
}

function calculateAndUpdateFines($conn, $account_id, $as_of_date = null) {
    // Get default fine settings
    $late_fine_fixed = (float)getSetting($conn, 'loan_late_fine_fixed') ?: 50.00;
    $grace_days = (int)getSetting($conn, 'loan_grace_days') ?: 3;
    
    $today = $as_of_date ?: date('Y-m-d');
    
    // Select Pending EMI installments that are past due
    // A fine is only applied if current date > due_date + grace_days
    $sql = "SELECT id, due_date, status, fine_amount, emi_amount 
            FROM loan_schedules 
            WHERE account_id = $account_id AND status != 'Paid'";
            
    $res = mysqli_query($conn, $sql);
    while($sch = mysqli_fetch_assoc($res)) {
        $due = $sch['due_date'];
        $grace_date = date('Y-m-d', strtotime($due . " + $grace_days days"));
        
        $new_status = $sch['status'];
        $new_fine = $sch['fine_amount'];
        
        if($today > $due) {
            $new_status = 'Overdue';
            if($today > $grace_date && $sch['fine_amount'] <= 0) {
                 // Apply fine once if not already applied
                 $new_fine = $late_fine_fixed;
            }
        }
        
        if($new_status != $sch['status'] || $new_fine != $sch['fine_amount']) {
             mysqli_query($conn, "UPDATE loan_schedules SET status = '$new_status', fine_amount = $new_fine WHERE id = " . $sch['id']);
        }
    }
}

function generateLoanSchedules($conn, $account_id, $principal, $interest_rate, $tenure_months, $disbursal_date, $frequency = 'Monthly', $day1 = 1, $day2 = 15, $interest_type = 'Flat') {
    // 1. Delete existing schedules
    mysqli_query($conn, "DELETE FROM loan_schedules WHERE account_id = $account_id");

    // 2. Determine Total Installments
    $total_installments = $tenure_months;
    if ($frequency == 'Weekly') $total_installments = $tenure_months * 4;
    elseif ($frequency == 'Bi-Weekly') $total_installments = $tenure_months * 2;

    $rate_annual = ($interest_rate / 100);
    
    // 3. Calculate EMI (Approximation for non-monthly if reducing, exact for flat)
    // We treat everything as "Total Interest / Total Installments" for Simplicity (Common in NBFC)
    // For Reducing, we calculate the monthly EMI first then divide.
    $rate_monthly = $rate_annual / 12;
    if($interest_type == 'Reducing') {
        $monthly_emi = ($principal * $rate_monthly * pow(1 + $rate_monthly, $tenure_months)) / (pow(1 + $rate_monthly, $tenure_months) - 1);
        $total_payable = $monthly_emi * $tenure_months;
    } else {
        $total_int = $principal * $rate_annual * ($tenure_months / 12);
        $total_payable = $principal + $total_int;
    }
    
    $emi = round($total_payable / $total_installments, 2);
    $total_interest_accumulated = round($total_payable - $principal, 2);
    
    $rem_principal = $principal;
    $rem_interest = $total_interest_accumulated;

    $current_date = strtotime($disbursal_date);
    
    for($i = 1; $i <= $total_installments; $i++) {
        // Calculate Next Due Date based on Frequency
        if ($frequency == 'Weekly') {
            // Find next fixed day (1=Mon, 7=Sun)
            $target_day_name = ['','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][$day1];
            $d_str = date('Y-m-d', $current_date);
            $final_due_date = date('Y-m-d', strtotime("next $target_day_name", strtotime($d_str . " + " . (($i-1)*7) . " days")));
        } elseif ($frequency == 'Bi-Weekly') {
            // 2 Dates per month (e.g. 1st and 15th)
            $month_idx = floor(($i - 1) / 2);
            $is_second = ($i % 2 == 0);
            $target_day = $is_second ? $day2 : $day1;
            
            $temp_date = date('Y-m-d', strtotime($disbursal_date . " + $month_idx months"));
            $parts = explode('-', $temp_date);
            $final_due_date = $parts[0].'-'.$parts[1].'-'.str_pad($target_day, 2, '0', STR_PAD_LEFT);
        } else {
            // Monthly
            $d_date = date('Y-m-d', strtotime($disbursal_date . " + $i months"));
            $parts = explode('-', $d_date);
            $final_due_date = $parts[0].'-'.$parts[1].'-'.str_pad($day1, 2, '0', STR_PAD_LEFT);
        }

        // Components (Simplified: Linear interest distribution for predictable collections)
        $prin_comp = round($principal / $total_installments, 2);
        $int_comp = round($total_interest_accumulated / $total_installments, 2);
        
        // Correction for last installment
        if ($i == $total_installments) {
            $prin_comp = $rem_principal;
            $int_comp = $rem_interest;
        } else {
            $rem_principal -= $prin_comp;
            $rem_interest -= $int_comp;
        }

        $emi_adj = $prin_comp + $int_comp;
        
        $sql_sch = "INSERT INTO loan_schedules (account_id, installment_no, due_date, emi_amount, principal_component, interest_component, status) 
                    VALUES ($account_id, $i, '$final_due_date', $emi_adj, $prin_comp, $int_comp, 'Pending')";
        mysqli_query($conn, $sql_sch);
    }
    
    // Update Account
    $sql_upd = "UPDATE accounts SET 
                current_balance = -$total_payable, 
                installment_amount = $emi, 
                principal_amount = $principal,
                tenure_months = $tenure_months,
                interest_rate = $interest_rate,
                repayment_frequency = '$frequency',
                repayment_day_1 = $day1,
                repayment_day_2 = $day2,
                loan_interest_type = '$interest_type'
                WHERE id = $account_id";
    mysqli_query($conn, $sql_upd);
    
    return [
        'emi' => $emi,
        'total_payable' => $total_payable,
        'total_interest' => $total_interest_accumulated,
        'installments' => $total_installments
    ];
}
