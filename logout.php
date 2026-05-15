<?php
session_start();
include 'db.php';
if (isset($_SESSION['user_id'])) {
    log_activity($conn, $_SESSION['user_id'], "logout", "Logged out");
}
session_destroy();
header("Location: login.php");
exit();
?>
