<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
} elseif ($_SESSION['role'] === 'seller') {
    header("Location: seller_dashboard.php");
} elseif ($_SESSION['role'] === 'admin') {
    header("Location: admin_requests.php");
} else {
    header("Location: shop.php");
}
exit();
?>
