<?php
// includes/db.php
if(session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Environment Detect
$is_local = ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1');

// Sensitive configuration isolation
$config_file = __DIR__ . '/db_credentials.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    // Local defaults (Development Fallbacks)
    $host = 'localhost';
    $user = ($is_local) ? 'root' : ''; 
    $pass = ($is_local) ? '' : '';
    $dbname = ($is_local) ? 'nbfc' : '';
    if (!defined('APP_URL')) {
        define('APP_URL', ($is_local) ? 'http://localhost/nbfc/' : '');
    }
}

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Database Connection Error: " . mysqli_connect_error());
}

// Function to check if user is logged in
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>
