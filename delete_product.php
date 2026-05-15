<?php
include 'auth.php';
include 'db.php';
if ($current_role !== 'seller') { header("Location: shop.php"); exit(); }

$id = intval($_GET['id'] ?? 0);

// Get product name for log before deleting
$chk = $conn->prepare("SELECT name FROM products WHERE id=? AND seller_id=?");
$chk->bind_param("ii", $id, $current_user_id);
$chk->execute();
$row = $chk->get_result()->fetch_assoc();
$chk->close();

if ($row) {
    $del = $conn->prepare("DELETE FROM products WHERE id=? AND seller_id=?");
    $del->bind_param("ii", $id, $current_user_id);
    $del->execute();
    $del->close();
    log_activity($conn, $current_user_id, "deleted_product", $row['name'] . " (ID $id)");
}

header("Location: my_products.php?deleted=1");
exit();
?>
