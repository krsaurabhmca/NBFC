<?php
require_once 'includes/db.php';
$res = mysqli_query($conn, "SHOW COLUMNS FROM members");
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . "\n";
}
?>
