<?php
include 'auth.php';
include 'db.php';
if ($current_role !== 'seller') { header("Location: shop.php"); exit(); }

$stmt = $conn->prepare("SELECT * FROM products WHERE seller_id=? ORDER BY created_at DESC");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Products – ShopBlue</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php include 'nav.php'; ?>
<div class="page">
  <div class="page-header">
    <h1>📦 My Products</h1>
    <a href="add_product.php" class="btn">＋ Add Product</a>
  </div>

  <?php if (isset($_GET['deleted'])): ?><div class="msg msg-success">Product deleted.</div><?php endif; ?>
  <?php if (isset($_GET['saved'])): ?><div class="msg msg-success">Product saved successfully.</div><?php endif; ?>

  <div class="card">
    <?php if ($products->num_rows === 0): ?>
      <div class="empty">No products yet. <a href="add_product.php" class="lnk">Add your first one</a></div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Image</th><th>Name</th><th>Description</th>
            <th>Price</th><th>Stock</th><th>Added</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($p = $products->fetch_assoc()):
            $img_path = ($p['image'] && file_exists('uploads/products/' . $p['image']))
                        ? 'uploads/products/' . htmlspecialchars($p['image'])
                        : null;
          ?>
          <tr>
            <!-- Thumbnail -->
            <td style="width:64px">
              <?php if ($img_path): ?>
                <img src="<?= $img_path ?>" alt="<?= htmlspecialchars($p['name']) ?>"
                  style="width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid var(--blue-border)">
              <?php else: ?>
                <div style="width:56px;height:56px;border-radius:8px;border:1px dashed var(--blue-border);background:rgba(26,108,255,.06);display:flex;align-items:center;justify-content:center;font-size:1.3rem">🖼</div>
              <?php endif; ?>
            </td>
            <td style="font-weight:500"><?= htmlspecialchars($p['name']) ?></td>
            <td style="color:var(--muted)"><?= htmlspecialchars(mb_strimwidth($p['description'] ?? '', 0, 50, '…')) ?></td>
            <td style="color:var(--blue-hi)">&#8369;<?= number_format($p['price'],2) ?></td>
            <td><?= $p['stock'] ?></td>
            <td style="font-size:.78rem;color:var(--muted);white-space:nowrap"><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
            <td>
              <div class="gap">
                <a href="edit_product.php?id=<?= $p['id'] ?>" class="btn btn-sm">Edit</a>
                <a href="delete_product.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-danger"
                   onclick="return confirm('Delete this product?')">Delete</a>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
</body></html>