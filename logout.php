<?php
if(session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once 'includes/db.php';
require_once 'includes/functions.php';

if(isset($_SESSION['user_id'])) {
    logAction($conn, $_SESSION['user_id'], 'Logout Success', 'User signed out manually.');
}

session_destroy();
header("Location: login.php");
exit();
?>
