<?php
// Entry point — redirect to login or the right home page
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
} elseif ($_SESSION['role'] === 'seller') {
    header("Location: seller_dashboard.php");
} else {
    header("Location: shop.php");
}
exit();
?>
