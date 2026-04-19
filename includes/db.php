<?php
// includes/db.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Environment Detect
$is_local = (php_sapi_name() == 'cli' || $_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1');

// Sensitive configuration isolation
$config_file = __DIR__ . '/db_credentials.php';

if ($is_local) {
    $host = '127.0.0.1';
    $user = 'root';
    $pass = '';
    $dbname = 'nbfc';
    define('APP_URL', 'http://localhost/nbfc/');
} elseif (file_exists($config_file)) {
    require_once $config_file;
} else {
    // Non-local fallback (Placeholder)
    $host = 'localhost';
    $user = '';
    $pass = '';
    $dbname = '';
    define('APP_URL', '');
}

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Database Connection Error: " . mysqli_connect_error());
}

require_once __DIR__ . '/functions.php';

// Function to check if user is logged in
function checkAuth()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . APP_URL . "login.php");
        exit();
    }
}
?>