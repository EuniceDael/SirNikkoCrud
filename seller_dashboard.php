<?php
include 'auth.php';
include 'db.php';
if ($current_role !== 'seller') { header("Location: shop.php"); exit(); }

// Stats
$total_products = $conn->query("SELECT COUNT(*) FROM products WHERE seller_id=$current_user_id")->fetch_row()[0];
$total_orders   = $conn->query("SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE oi.seller_id=$current_user_id")->fetch_row()[0];
$pending        = $conn->query("SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE oi.seller_id=$current_user_id AND o.status='pending'")->fetch_row()[0];
$revenue_row    = $conn->query("SELECT SUM(oi.price*oi.quantity) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.seller_id=$current_user_id AND o.status='delivered'")->fetch_row();
$revenue        = $revenue_row[0] ?? 0;

// Recent orders
$recent = $conn->prepare("
    SELECT o.id, o.status, o.total_amount, o.created_at, u.username AS buyer,
           GROUP_CONCAT(p.name SEPARATOR ', ') AS items
    FROM orders o
    JOIN order_items oi ON oi.order_id=o.id
    JOIN products p     ON p.id=oi.product_id
    JOIN users u        ON u.id=o.buyer_id
    WHERE oi.seller_id=?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5
");
$recent->bind_param("i", $current_user_id);
$recent->execute();
$recent_orders = $recent->get_result();
$recent->close();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard – ShopBlue</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php include 'nav.php'; ?>
<div class="page">
  <div class="page-header">
    <h1>📊 Dashboard</h1>
    <a href="add_product.php" class="btn">＋ Add Product</a>
  </div>

  <div class="stats-row">
    <div class="stat-card"><div class="stat-num"><?= $total_products ?></div><div class="stat-label">Products</div></div>
    <div class="stat-card"><div class="stat-num"><?= $total_orders ?></div><div class="stat-label">Total Orders</div></div>
    <div class="stat-card"><div class="stat-num"><?= $pending ?></div><div class="stat-label">Pending</div></div>
    <div class="stat-card"><div class="stat-num">&#8369;<?= number_format($revenue, 0) ?></div><div class="stat-label">Revenue</div></div>
  </div>

  <div class="card">
    <h3 style="margin-bottom:14px">Recent Orders</h3>
    <?php if ($recent_orders->num_rows === 0): ?>
      <div class="empty">No orders yet.</div>
    <?php else: ?>
      <table>
        <thead><tr><th>Order #</th><th>Buyer</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
          <?php while ($o = $recent_orders->fetch_assoc()): ?>
          <tr>
            <td>#<?= $o['id'] ?></td>
            <td><?= htmlspecialchars($o['buyer']) ?></td>
            <td><?= htmlspecialchars(mb_strimwidth($o['items'],0,40,'…')) ?></td>
            <td style="color:var(--blue-hi)">&#8369;<?= number_format($o['total_amount'],2) ?></td>
            <td><span class="badge badge-<?= $o['status'] ?>"><?= $o['status'] ?></span></td>
            <td style="font-size:.78rem;color:var(--muted)"><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
            <td><a href="order_detail.php?id=<?= $o['id'] ?>" class="btn btn-sm">View</a></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
</body></html>
