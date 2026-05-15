<?php
include 'auth.php';
include 'db.php';
if ($current_role !== 'buyer') { header("Location: seller_dashboard.php"); exit(); }

$id   = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT p.*, u.username AS seller FROM products p JOIN users u ON p.seller_id=u.id WHERE p.id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$p) { header("Location: shop.php"); exit(); }

$me = $conn->prepare("SELECT phone, address FROM users WHERE id=?");
$me->bind_param("i", $current_user_id);
$me->execute();
$my = $me->get_result()->fetch_assoc();
$me->close();

$revs = $conn->prepare("SELECT r.*, u.username FROM reviews r JOIN users u ON r.buyer_id=u.id WHERE r.product_id=? ORDER BY r.created_at DESC");
$revs->bind_param("i", $id);
$revs->execute();
$reviews = $revs->get_result();
$revs->close();

$chkRev = $conn->prepare("SELECT id FROM reviews WHERE product_id=? AND buyer_id=?");
$chkRev->bind_param("ii", $id, $current_user_id);
$chkRev->execute(); $chkRev->store_result();
$already_reviewed = $chkRev->num_rows > 0;
$chkRev->close();

$chkDel = $conn->prepare("
    SELECT o.id FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE o.buyer_id=? AND oi.product_id=? AND o.status='delivered' LIMIT 1
");
$chkDel->bind_param("ii", $current_user_id, $id);
$chkDel->execute(); $chkDel->store_result();
$has_delivered_order = $chkDel->num_rows > 0;
$chkDel->close();

$img_path = ($p['image'] && file_exists('uploads/products/' . $p['image']))
            ? 'uploads/products/' . htmlspecialchars($p['image'])
            : null;

$msg = $msgtype = '';

// ── ORDER ────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'order') {
    $qty     = max(1, intval($_POST['quantity']));
    $phone   = trim($_POST['phone']);
    $address = trim($_POST['address']);
    if ($qty > $p['stock']) {
        $msg = "Not enough stock. Only {$p['stock']} left."; $msgtype = 'error';
    } elseif (!$phone || !$address) {
        $msg = "Phone and address are required."; $msgtype = 'error';
    } else {
        $total = $p['price'] * $qty;
        $ord = $conn->prepare("INSERT INTO orders (buyer_id,phone,address,total_amount) VALUES (?,?,?,?)");
        $ord->bind_param("issd", $current_user_id, $phone, $address, $total);
        $ord->execute(); $order_id = $conn->insert_id; $ord->close();
        $oi = $conn->prepare("INSERT INTO order_items (order_id,product_id,seller_id,quantity,price) VALUES (?,?,?,?,?)");
        $oi->bind_param("iiiid", $order_id, $p['id'], $p['seller_id'], $qty, $p['price']);
        $oi->execute(); $oi->close();
        $st = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id=?");
        $st->bind_param("ii", $qty, $p['id']); $st->execute(); $st->close();
        $upd = $conn->prepare("UPDATE users SET phone=?, address=? WHERE id=?");
        $upd->bind_param("ssi", $phone, $address, $current_user_id); $upd->execute(); $upd->close();
        log_activity($conn, $current_user_id, "placed_order", "Order #$order_id — {$p['name']} x$qty");
        header("Location: my_orders.php?success=1"); exit();
    }
}

// ── REVIEW ───────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'review') {
    if (!$has_delivered_order) {
        $msg = "You can only review after your order is delivered."; $msgtype = 'error';
    } elseif ($already_reviewed) {
        $msg = "You have already reviewed this product."; $msgtype = 'error';
    } else {
        $rating  = max(1, min(5, intval($_POST['rating'])));
        $comment = trim($_POST['comment']);
        $rv = $conn->prepare("INSERT INTO reviews (product_id,buyer_id,rating,comment) VALUES (?,?,?,?)");
        $rv->bind_param("iiis", $id, $current_user_id, $rating, $comment);
        $rv->execute(); $rv->close();
        log_activity($conn, $current_user_id, "reviewed_product", "Product #$id — $rating stars");
        header("Location: product.php?id=$id&reviewed=1"); exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($p['name']) ?> – ShopBlue</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php include 'nav.php'; ?>
<div class="page" style="max-width:740px">
  <a href="shop.php" class="back">&#8592; Back to Shop</a>

  <?php if ($msg): ?><div class="msg msg-<?= $msgtype ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if (isset($_GET['reviewed'])): ?><div class="msg msg-success">✓ Review submitted! Thank you.</div><?php endif; ?>

  <div class="card">
    <!-- Product image -->
    <?php if ($img_path): ?>
      <img src="<?= $img_path ?>" alt="<?= htmlspecialchars($p['name']) ?>"
        style="width:100%;max-height:320px;object-fit:cover;border-radius:var(--r-md);margin-bottom:20px;border:1px solid var(--blue-border)">
    <?php else: ?>
      <div style="width:100%;height:200px;background:rgba(26,108,255,.06);border:1px dashed var(--blue-border);border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;font-size:3rem;margin-bottom:20px">🖼</div>
    <?php endif; ?>

    <h2><?= htmlspecialchars($p['name']) ?></h2>
    <p style="color:var(--muted);font-size:.9rem;margin-bottom:16px"><?= nl2br(htmlspecialchars($p['description'] ?? '')) ?></p>

    <div class="gap" style="margin-bottom:18px">
      <span style="font-family:'Oxanium',sans-serif;font-size:1.5rem;font-weight:700;color:var(--blue-hi)">
        &#8369;<?= number_format($p['price'], 2) ?>
      </span>
      <span style="color:var(--dim);font-size:.85rem">Stock: <?= $p['stock'] ?></span>
      <span style="color:var(--muted);font-size:.85rem">by <?= htmlspecialchars($p['seller']) ?></span>
    </div>

    <?php if ($p['stock'] > 0): ?>
    <div class="divider"></div>
    <h3 style="margin-bottom:14px">Place Order</h3>
    <form method="POST">
      <input type="hidden" name="action" value="order">
      <div class="field"><label>Quantity</label>
        <input type="number" name="quantity" value="1" min="1" max="<?= $p['stock'] ?>">
      </div>
      <div class="field"><label>Phone Number</label>
        <input type="tel" name="phone" placeholder="e.g. 09123456789" required
               value="<?= htmlspecialchars($_POST['phone'] ?? $my['phone'] ?? '') ?>">
      </div>
      <div class="field"><label>Delivery Address</label>
        <textarea name="address" placeholder="Your full delivery address" required><?= htmlspecialchars($_POST['address'] ?? $my['address'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn" style="margin-top:4px">🛒 Place Order</button>
    </form>
    <?php else: ?>
      <div class="msg msg-error">Out of stock.</div>
    <?php endif; ?>
  </div>

  <!-- REVIEWS -->
  <div class="card">
    <h3 style="margin-bottom:14px">Customer Reviews</h3>
    <?php if ($has_delivered_order && !$already_reviewed): ?>
      <form method="POST" style="margin-bottom:20px">
        <input type="hidden" name="action" value="review">
        <div class="field"><label>Your Rating</label>
          <select name="rating">
            <?php for ($i=5;$i>=1;$i--): ?>
              <option value="<?= $i ?>"><?= str_repeat('★',$i).str_repeat('☆',5-$i) ?> (<?= $i ?> star<?= $i>1?'s':'' ?>)</option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="field"><label>Comment</label>
          <textarea name="comment" placeholder="Share your experience…"></textarea>
        </div>
        <button type="submit" class="btn btn-sm btn-success">Submit Review</button>
      </form>
      <div class="divider"></div>
    <?php elseif ($already_reviewed): ?>
      <div class="msg msg-info" style="margin-bottom:16px">✓ You have already reviewed this product.</div>
    <?php else: ?>
      <div class="msg msg-info" style="margin-bottom:16px">🔒 Reviews are only available after your order is <strong>delivered</strong>.</div>
    <?php endif; ?>

    <?php if ($reviews->num_rows === 0): ?>
      <div class="empty">No reviews yet.</div>
    <?php else: ?>
      <?php while ($rv = $reviews->fetch_assoc()): ?>
        <div style="margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--blue-border)">
          <div class="gap">
            <span style="font-weight:600;font-size:.88rem"><?= htmlspecialchars($rv['username']) ?></span>
            <span class="stars"><?= str_repeat('★',$rv['rating']).str_repeat('☆',5-$rv['rating']) ?></span>
            <span style="color:var(--dim);font-size:.78rem"><?= date('M d, Y', strtotime($rv['created_at'])) ?></span>
          </div>
          <p style="font-size:.87rem;color:var(--muted);margin-top:5px"><?= nl2br(htmlspecialchars($rv['comment'])) ?></p>
        </div>
      <?php endwhile; ?>
    <?php endif; ?>
  </div>
</div>
</body></html>