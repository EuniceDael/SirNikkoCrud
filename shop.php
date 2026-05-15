<?php
include 'auth.php';
include 'db.php';
if ($current_role !== 'buyer') { header("Location: seller_dashboard.php"); exit(); }

$search = trim($_GET['search'] ?? '');
$like   = "%$search%";

if ($search !== '') {
    $stmt = $conn->prepare("SELECT p.*, u.username AS seller FROM products p JOIN users u ON p.seller_id=u.id WHERE p.name LIKE ? OR p.description LIKE ? ORDER BY p.created_at DESC");
    $stmt->bind_param("ss", $like, $like);
} else {
    $stmt = $conn->prepare("SELECT p.*, u.username AS seller FROM products p JOIN users u ON p.seller_id=u.id ORDER BY p.created_at DESC");
}
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Shop – ShopBlue</title><link rel="stylesheet" href="style.css">
<style>
.product-img{width:100%;height:160px;object-fit:cover;border-radius:var(--r-md);margin-bottom:10px}
.product-img-placeholder{width:100%;height:160px;background:rgba(26,108,255,.06);border:1px dashed var(--blue-border);border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin-bottom:10px}
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="page">
  <div class="page-header"><h1>🛍 Shop</h1></div>

  <form method="GET" class="toolbar">
    <input type="text" name="search" placeholder="Search products…" value="<?= htmlspecialchars($search) ?>">
    <button type="submit" class="btn">Search</button>
    <?php if ($search): ?><a href="shop.php" class="btn-ghost">Clear</a><?php endif; ?>
  </form>

  <?php if ($products->num_rows === 0): ?>
    <div class="empty">No products found.</div>
  <?php else: ?>
    <div class="product-grid">
      <?php while ($p = $products->fetch_assoc()):
        $img_path = ($p['image'] && file_exists('uploads/products/' . $p['image']))
                    ? 'uploads/products/' . htmlspecialchars($p['image'])
                    : null;
      ?>
        <a href="product.php?id=<?= $p['id'] ?>" class="product-card">
          <?php if ($img_path): ?>
            <img src="<?= $img_path ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="product-img">
          <?php else: ?>
            <div class="product-img-placeholder">🖼</div>
          <?php endif; ?>
          <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
          <div class="product-desc"><?= htmlspecialchars(mb_strimwidth($p['description'] ?? '', 0, 80, '…')) ?></div>
          <div class="product-price">&#8369;<?= number_format($p['price'], 2) ?></div>
          <div class="product-meta">
            Stock: <?= $p['stock'] ?>  &nbsp;·&nbsp;  by <?= htmlspecialchars($p['seller']) ?>
          </div>
        </a>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>
</div>
</body></html>