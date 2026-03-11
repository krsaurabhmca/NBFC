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
?>
