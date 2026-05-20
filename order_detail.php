<?php
include 'auth.php';
include 'db.php';

$order_id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT o.*, u.username AS buyer_name FROM orders o JOIN users u ON u.id=o.buyer_id WHERE o.id=?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) { header("Location: login.php"); exit(); }

// Access control
if ($current_role === 'buyer' && $order['buyer_id'] != $current_user_id) {
    header("Location: my_orders.php"); exit();
}
if ($current_role === 'seller') {
    $chk = $conn->prepare("SELECT id FROM order_items WHERE order_id=? AND seller_id=?");
    $chk->bind_param("ii", $order_id, $current_user_id);
    $chk->execute(); $chk->store_result();
    if ($chk->num_rows === 0) { header("Location: seller_orders.php"); exit(); }
    $chk->close();
}

// Fetch items
if ($current_role === 'seller') {
    $items_stmt = $conn->prepare("SELECT oi.*, p.name AS product_name FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? AND oi.seller_id=?");
    $items_stmt->bind_param("ii", $order_id, $current_user_id);
} else {
    $items_stmt = $conn->prepare("SELECT oi.*, p.name AS product_name FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE oi.order_id=?");
    $items_stmt->bind_param("i", $order_id);
}
$items_stmt->execute();
$items = $items_stmt->get_result();
$items_stmt->close();

$back = $current_role === 'seller' ? 'seller_orders.php' : 'my_orders.php';
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order #<?= $order_id ?> – ShopBlue</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php include 'nav.php'; ?>
<div class="page" style="max-width:680px">
  <a href="<?= $back ?>" class="back">&#8592; Back</a>

  <div class="card">
    <div class="page-header">
      <h2>Order #<?= $order_id ?></h2>
      <span class="badge badge-<?= $order['status'] ?>"><?= $order['status'] ?></span>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px">
      <div>
        <div class="help" style="margin-bottom:3px">BUYER</div>
        <div style="font-size:.9rem"><?= htmlspecialchars($order['buyer_name']) ?></div>
      </div>
      <div>
        <div class="help" style="margin-bottom:3px">DATE</div>
        <div style="font-size:.9rem"><?= date('F d, Y  g:i A', strtotime($order['created_at'])) ?></div>
      </div>
      <div>
        <div class="help" style="margin-bottom:3px">PHONE</div>
        <div style="font-size:.9rem"><?= htmlspecialchars($order['phone']) ?></div>
      </div>
      <div>
        <div class="help" style="margin-bottom:3px">ADDRESS</div>
        <div style="font-size:.9rem"><?= nl2br(htmlspecialchars($order['address'])) ?></div>
      </div>
      <div>
        <div class="help" style="margin-bottom:3px">PAYMENT METHOD</div>
        <div style="font-size:.9rem"><?= $order['payment_method'] === 'gcash' ? '📱 GCash / Online' : '� Wallet' ?></div>
      </div>
      <div>
        <div class="help" style="margin-bottom:3px">PAYMENT STATUS</div>
        <?php
          $ps = $order['payment_status'] ?? 'unpaid';
          $ps_color = $ps === 'paid' ? 'var(--success)' : ($ps === 'refunded' ? '#ff9f43' : 'var(--danger)');
          $ps_icon  = $ps === 'paid' ? '✅' : ($ps === 'refunded' ? '↩️' : '⏳');
        ?>
        <div style="font-size:.9rem;color:<?= $ps_color ?>"><?= $ps_icon ?> <?= ucfirst($ps) ?></div>
      </div>
    </div>

    <!-- Payment proof -->
    <?php if (!empty($order['payment_proof']) && file_exists('uploads/payments/' . $order['payment_proof'])): ?>
    <div class="divider"></div>
    <div style="margin-bottom:16px">
      <div class="help" style="margin-bottom:8px">PAYMENT PROOF</div>
      <img src="uploads/payments/<?= htmlspecialchars($order['payment_proof']) ?>"
           alt="Payment proof"
           style="max-width:320px;width:100%;border-radius:var(--r-md);border:1px solid var(--blue-border)">
    </div>
    <?php endif; ?>

    <!-- Cancellation info block -->
    <?php if ($order['cancel_status'] !== 'none'): ?>
    <div class="divider"></div>
    <div style="margin-bottom:16px">
      <div class="help" style="margin-bottom:6px">CANCELLATION REQUEST</div>
      <div class="gap" style="margin-bottom:6px">
        <?php if ($order['cancel_status'] === 'pending'): ?>
          <span class="badge badge-pending">⏳ Pending seller decision</span>
        <?php elseif ($order['cancel_status'] === 'approved'): ?>
          <span class="badge badge-cancelled">✓ Approved</span>
        <?php elseif ($order['cancel_status'] === 'rejected'): ?>
          <span class="badge" style="background:rgba(255,159,67,.1);color:#ff9f43;border:1px solid rgba(255,159,67,.35)">✗ Rejected by seller</span>
        <?php endif; ?>
      </div>
      <?php if ($order['cancellation_reason']): ?>
        <div style="background:rgba(255,59,92,.07);border:1px solid rgba(255,59,92,.2);border-radius:var(--r-sm);padding:12px 14px;font-size:.87rem;color:var(--muted)">
          <span style="color:var(--dim);font-size:.75rem;display:block;margin-bottom:4px">REASON</span>
          <?= nl2br(htmlspecialchars($order['cancellation_reason'])) ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="divider"></div>

    <table>
      <thead>
        <tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr>
      </thead>
      <tbody>
        <?php while ($it = $items->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($it['product_name']) ?></td>
          <td><?= $it['quantity'] ?></td>
          <td>&#8369;<?= number_format($it['price'], 2) ?></td>
          <td style="color:var(--blue-hi)">&#8369;<?= number_format($it['price'] * $it['quantity'], 2) ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

    <div style="text-align:right;margin-top:14px">
      <span style="font-family:'Oxanium',sans-serif;font-size:1.15rem;font-weight:700;color:var(--blue-hi)">
        Total: &#8369;<?= number_format($order['total_amount'], 2) ?>
      </span>
    </div>
  </div>
</div>
</body></html>