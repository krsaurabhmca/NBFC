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
    // Create table if not exists
    $create_sql = "CREATE TABLE IF NOT EXISTS `system_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `action` varchar(100) NOT NULL,
        `details` text,
        `ip_address` varchar(45) NOT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    mysqli_query($conn, $create_sql) or die(mysqli_error($conn));

    $user_id = (int)$user_id;
    $action = mysqli_real_escape_string($conn, $action);
    $details = mysqli_real_escape_string($conn, $details);
    $ip = $_SERVER['REMOTE_ADDR'];

    $sql = "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES ($user_id, '$action', '$details', '$ip')";
    mysqli_query($conn, $sql) or die(mysqli_error($conn));
}
