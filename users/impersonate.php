<?php
require_once '../includes/db.php';
session_start();

// Security: Only admins can impersonate others
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    if (!isset($_SESSION['admin_user_id'])) {
        die("Unauthorized access.");
    }
}

// Handle Revert back to Admin
if (isset($_GET['revert']) && $_GET['revert'] == 1 && isset($_SESSION['admin_user_id'])) {
    $admin_id = $_SESSION['admin_user_id'];
    
    // Fetch Admin Data
    $res = mysqli_query($conn, "SELECT id, name, username, role FROM users WHERE id = $admin_id");
    $user = mysqli_fetch_assoc($res);
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        unset($_SESSION['admin_user_id']);
        
        $_SESSION['success'] = "Back to Admin Session.";
        header("Location: " . APP_URL . "index.php");
        exit();
    }
}

// Handle Login As
$target_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($target_id > 0) {
    // Store current admin ID if not already impersonating
    if (!isset($_SESSION['admin_user_id'])) {
        $_SESSION['admin_user_id'] = $_SESSION['user_id'];
    }
    
    $res = mysqli_query($conn, "SELECT id, name, username, role FROM users WHERE id = $target_id");
    $user = mysqli_fetch_assoc($res);
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        
        $_SESSION['success'] = "System Mode: Logged in as " . $user['name'] . " (" . $user['role'] . ")";
        header("Location: " . APP_URL . "index.php");
        exit();
    }
}

header("Location: index.php");
exit();
