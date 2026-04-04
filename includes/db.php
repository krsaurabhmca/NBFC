<?php
// includes/db.php
if(session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$is_local = ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1');

if ($is_local) {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $dbname = 'nbfc';
    define('APP_URL', 'http://localhost/nbfc/');
} else {
    $host = 'localhost';
    $user = 'u443617320_jnbank';
    $pass = '@Bank_2001';
    $dbname = 'u443617320_jnbank';
    define('APP_URL', 'https://jn.morg.in/');
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
