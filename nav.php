<?php
// This file is included by every protected page.
// $current_role, $current_username, $current_user_id must already be set (via auth.php).
$page = basename($_SERVER['PHP_SELF']);
?>
<nav>
  <a href="<?= $current_role === 'seller' ? 'seller_dashboard.php' : 'shop.php' ?>" class="nav-brand">⬡ ShopBlue</a>

  <div class="nav-links">
    <?php if ($current_role === 'buyer'): ?>
      <a href="shop.php"        <?= $page==='shop.php'        ?'class="active"':'' ?>>Shop</a>
      <a href="my_orders.php"   <?= $page==='my_orders.php'   ?'class="active"':'' ?>>My Orders</a>
    <?php else: ?>
      <a href="seller_dashboard.php" <?= $page==='seller_dashboard.php' ?'class="active"':'' ?>>Dashboard</a>
      <a href="my_products.php"      <?= $page==='my_products.php'      ?'class="active"':'' ?>>My Products</a>
      <a href="seller_orders.php"    <?= $page==='seller_orders.php'    ?'class="active"':'' ?>>Orders</a>
      <a href="activity_log.php"     <?= $page==='activity_log.php'     ?'class="active"':'' ?>>Logs</a>
    <?php endif; ?>
  </div>

  <div class="nav-right">
    <span class="nav-user"><?= htmlspecialchars($current_username) ?></span>
    <span class="badge badge-<?= $current_role ?>"><?= $current_role ?></span>
    <a href="logout.php" class="btn-ghost btn-sm">Logout</a>
  </div>
</nav>
