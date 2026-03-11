<?php
// includes/db.php
if(session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$host = 'localhost';
$user = 'root';
$pass = ''; // Default XAMPP password
$dbname = 'nbfc';

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Function to check if user is logged in
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>
