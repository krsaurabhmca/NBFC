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
