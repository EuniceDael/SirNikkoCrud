<?php
include 'auth.php';
include 'db.php';
if ($current_role !== 'buyer') { header("Location: seller_dashboard.php"); exit(); }

// ── HANDLE CANCEL REQUEST ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'request_cancel') {
        $oid    = intval($_POST['order_id']);
        $reason = trim($_POST['reason']);

        if (!$reason) {
            $cancel_error = "Please provide a reason for cancellation.";
        } else {
            $chk = $conn->prepare("SELECT id, status FROM orders WHERE id=? AND buyer_id=?");
            $chk->bind_param("ii", $oid, $current_user_id);
            $chk->execute();
            $chk_order = $chk->get_result()->fetch_assoc();
            $chk->close();

            if (!$chk_order) {
                $cancel_error = "Order not found.";
            } elseif (in_array($chk_order['status'], ['shipped','delivered','cancelled'])) {
                $cancel_error = "You cannot request cancellation for an order that is already {$chk_order['status']}.";
            } else {
                $upd = $conn->prepare("UPDATE orders SET cancel_requested=1, cancellation_reason=?, cancel_status='pending' WHERE id=? AND buyer_id=?");
                $upd->bind_param("sii", $reason, $oid, $current_user_id);
                $upd->execute(); $upd->close();
                log_activity($conn, $current_user_id, "requested_cancellation", "Order #$oid — Reason: $reason");
                header("Location: my_orders.php?cancel_sent=1"); exit();
            }
        }
    }

    // ── HANDLE REVIEW SUBMIT ─────────────────────────────────
    if ($_POST['action'] === 'submit_review') {
        $oid        = intval($_POST['order_id']);
        $product_id = intval($_POST['product_id']);
        $rating     = max(1, min(5, intval($_POST['rating'])));
        $comment    = trim($_POST['comment']);

        // Verify this order is delivered and belongs to buyer
        $chk = $conn->prepare("SELECT id FROM orders WHERE id=? AND buyer_id=? AND status='delivered'");
        $chk->bind_param("ii", $oid, $current_user_id);
        $chk->execute(); $chk->store_result();
        $valid_order = $chk->num_rows > 0;
        $chk->close();

        // Verify product is in this order
        $chk2 = $conn->prepare("SELECT id FROM order_items WHERE order_id=? AND product_id=?");
        $chk2->bind_param("ii", $oid, $product_id);
        $chk2->execute(); $chk2->store_result();
        $valid_item = $chk2->num_rows > 0;
        $chk2->close();

        // Check not already reviewed
        $chk3 = $conn->prepare("SELECT id FROM reviews WHERE product_id=? AND buyer_id=?");
        $chk3->bind_param("ii", $product_id, $current_user_id);
        $chk3->execute(); $chk3->store_result();
        $already = $chk3->num_rows > 0;
        $chk3->close();

        if ($valid_order && $valid_item && !$already) {
            $rv = $conn->prepare("INSERT INTO reviews (product_id, buyer_id, rating, comment) VALUES (?,?,?,?)");
            $rv->bind_param("iiis", $product_id, $current_user_id, $rating, $comment);
            $rv->execute(); $rv->close();
            log_activity($conn, $current_user_id, "reviewed_product", "Product #$product_id — $rating stars");
        }
        header("Location: my_orders.php?reviewed=1"); exit();
    }
}

// ── FETCH ORDERS ─────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT o.*,
           GROUP_CONCAT(p.name ORDER BY oi.id SEPARATOR '||') AS items,
           GROUP_CONCAT(p.id   ORDER BY oi.id SEPARATOR '||') AS product_ids
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    JOIN products p     ON p.id = oi.product_id
    WHERE o.buyer_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();

// Fetch already-reviewed product IDs by this buyer (for quick lookup)
$rev_stmt = $conn->prepare("SELECT product_id FROM reviews WHERE buyer_id=?");
$rev_stmt->bind_param("i", $current_user_id);
$rev_stmt->execute();
$rev_rows = $rev_stmt->get_result();
$rev_stmt->close();
$reviewed_products = [];
while ($r = $rev_rows->fetch_assoc()) {
    $reviewed_products[] = $r['product_id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Orders – ShopBlue</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php include 'nav.php'; ?>
<div class="page">

  <?php if (isset($_GET['success'])): ?>
    <div class="msg msg-success">✓ Order placed! The seller will process it soon.</div>
  <?php endif; ?>
  <?php if (isset($_GET['cancel_sent'])): ?>
    <div class="msg msg-info">📋 Cancellation request sent. Waiting for seller approval.</div>
  <?php endif; ?>
  <?php if (isset($_GET['reviewed'])): ?>
    <div class="msg msg-success">⭐ Review submitted! Thank you.</div>
  <?php endif; ?>
  <?php if (isset($cancel_error)): ?>
    <div class="msg msg-error"><?= htmlspecialchars($cancel_error) ?></div>
  <?php endif; ?>

  <div class="page-header"><h1>📦 My Orders</h1></div>

  <?php if ($orders->num_rows === 0): ?>
    <div class="card">
      <div class="empty">No orders yet. <a href="shop.php" class="lnk">Start shopping →</a></div>
    </div>
  <?php else: ?>
    <?php while ($o = $orders->fetch_assoc()):
      $cancellable  = in_array($o['status'], ['pending','processing']) && $o['cancel_status'] === 'none';
      $is_delivered = $o['status'] === 'delivered';

      // Build per-order product list for review buttons
      $item_names = explode('||', $o['items']);
      $item_ids   = explode('||', $o['product_ids']);
    ?>

    <div class="card" style="margin-bottom:16px">
      <!-- Order header row -->
      <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px">
        <div>
          <div style="font-family:'Oxanium',sans-serif;font-size:.8rem;color:var(--muted);margin-bottom:4px">
            ORDER #<?= $o['id'] ?> &nbsp;·&nbsp; <?= date('M d, Y', strtotime($o['created_at'])) ?>
          </div>
          <div style="font-family:'Oxanium',sans-serif;font-size:1.1rem;font-weight:700;color:var(--blue-hi)">
            &#8369;<?= number_format($o['total_amount'], 2) ?>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <span class="badge badge-<?= $o['status'] ?>"><?= $o['status'] ?></span>
          <?php if ($o['cancel_status'] === 'pending'): ?>
            <span class="badge badge-pending">⏳ Cancel Pending</span>
          <?php elseif ($o['cancel_status'] === 'approved'): ?>
            <span class="badge badge-cancelled">✓ Cancelled</span>
          <?php elseif ($o['cancel_status'] === 'rejected'): ?>
            <span class="badge" style="background:rgba(255,159,67,.1);color:#ff9f43;border:1px solid rgba(255,159,67,.3)">✗ Cancel Rejected</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Shipping info -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
        <div>
          <div class="help">Phone</div>
          <div style="font-size:.88rem"><?= htmlspecialchars($o['phone']) ?></div>
        </div>
        <div>
          <div class="help">Address</div>
          <div style="font-size:.88rem"><?= htmlspecialchars($o['address']) ?></div>
        </div>
      </div>

      <div class="divider"></div>

      <!-- Items list -->
      <div style="margin-bottom:14px">
        <div class="help" style="margin-bottom:8px">Items Ordered</div>
        <?php foreach ($item_names as $idx => $pname):
          $pid          = intval($item_ids[$idx]);
          $already_rev  = in_array($pid, $reviewed_products);
        ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid rgba(61,139,255,.07)">
            <span style="font-size:.9rem"><?= htmlspecialchars($pname) ?></span>

            <?php if ($is_delivered): ?>
              <?php if ($already_rev): ?>
                <span style="font-size:.78rem;color:var(--success)">⭐ Reviewed</span>
              <?php else: ?>
                <button class="btn btn-sm btn-success"
                  onclick="openReviewModal(<?= $o['id'] ?>, <?= $pid ?>, '<?= htmlspecialchars(addslashes($pname)) ?>')">
                  ✍ Write Review
                </button>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Action buttons -->
      <div class="gap">
        <a href="order_detail.php?id=<?= $o['id'] ?>" class="btn-ghost btn-sm">View Detail</a>
        <?php if ($cancellable): ?>
          <button class="btn btn-sm btn-danger"
            onclick="openCancelModal(<?= $o['id'] ?>)">Request Cancel</button>
        <?php endif; ?>
      </div>
    </div>

    <?php endwhile; ?>
  <?php endif; ?>
</div>

<!-- ── REVIEW MODAL ─────────────────────────────────────────── -->
<div id="review-modal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.65);backdrop-filter:blur(5px);align-items:center;justify-content:center">
  <div style="background:var(--glass2);border:1px solid var(--blue-border);border-radius:var(--r-xl);padding:30px 28px;width:min(92%,480px);box-shadow:var(--shadow);position:relative">
    <div style="position:absolute;top:0;left:10%;right:10%;height:1px;background:linear-gradient(90deg,transparent,var(--blue-hi),transparent)"></div>
    <h3 style="margin-bottom:4px">⭐ Write a Review</h3>
    <p id="review-product-name" style="color:var(--muted);font-size:.85rem;margin-bottom:18px"></p>
    <form method="POST" style="gap:12px">
      <input type="hidden" name="action" value="submit_review">
      <input type="hidden" name="order_id" id="review-order-id" value="">
      <input type="hidden" name="product_id" id="review-product-id" value="">
      <div class="field">
        <label>Star Rating</label>
        <div id="star-picker" style="display:flex;gap:8px;margin-top:4px">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <span class="star-btn" data-val="<?= $i ?>"
              style="font-size:1.6rem;cursor:pointer;color:var(--dim);transition:color .15s"
              onmouseenter="hoverStars(<?= $i ?>)"
              onmouseleave="resetStars()"
              onclick="selectStar(<?= $i ?>)">★</span>
          <?php endfor; ?>
        </div>
        <input type="hidden" name="rating" id="rating-input" value="5">
      </div>
      <div class="field">
        <label>Comment</label>
        <textarea name="comment" placeholder="How was your experience with this product?…" style="min-height:100px"></textarea>
      </div>
      <div class="gap" style="margin-top:4px">
        <button type="submit" class="btn btn-success">Submit Review</button>
        <button type="button" class="btn-ghost" onclick="closeReviewModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ── CANCEL MODAL ─────────────────────────────────────────── -->
<div id="cancel-modal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.65);backdrop-filter:blur(5px);align-items:center;justify-content:center">
  <div style="background:var(--glass2);border:1px solid var(--blue-border);border-radius:var(--r-xl);padding:30px 28px;width:min(92%,460px);box-shadow:var(--shadow)">
    <h3 style="margin-bottom:6px">Request Cancellation</h3>
    <p style="color:var(--muted);font-size:.85rem;margin-bottom:18px">Tell the seller why you want to cancel this order.</p>
    <form method="POST" style="gap:12px">
      <input type="hidden" name="action" value="request_cancel">
      <input type="hidden" name="order_id" id="modal-order-id" value="">
      <div class="field">
        <label>Reason for Cancellation</label>
        <textarea name="reason" id="cancel-reason" placeholder="e.g. Ordered by mistake, found a better price…" required style="min-height:100px"></textarea>
      </div>
      <div class="gap" style="margin-top:4px">
        <button type="submit" class="btn btn-danger">Send Request</button>
        <button type="button" class="btn-ghost" onclick="closeCancelModal()">Nevermind</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── REVIEW MODAL ─────────────────────────────────────────────
let selectedStar = 5;

function openReviewModal(orderId, productId, productName) {
  document.getElementById('review-order-id').value   = orderId;
  document.getElementById('review-product-id').value = productId;
  document.getElementById('review-product-name').textContent = productName;
  selectStar(5);
  document.querySelector('[name="comment"]').value = '';
  document.getElementById('review-modal').style.display = 'flex';
}
function closeReviewModal() {
  document.getElementById('review-modal').style.display = 'none';
}
function hoverStars(val) {
  document.querySelectorAll('.star-btn').forEach(s => {
    s.style.color = parseInt(s.dataset.val) <= val ? '#ffaa00' : 'var(--dim)';
  });
}
function resetStars() {
  document.querySelectorAll('.star-btn').forEach(s => {
    s.style.color = parseInt(s.dataset.val) <= selectedStar ? '#ffaa00' : 'var(--dim)';
  });
}
function selectStar(val) {
  selectedStar = val;
  document.getElementById('rating-input').value = val;
  resetStars();
}
// Init stars on load
document.addEventListener('DOMContentLoaded', () => selectStar(5));

document.getElementById('review-modal').addEventListener('click', function(e){
  if (e.target === this) closeReviewModal();
});

// ── CANCEL MODAL ─────────────────────────────────────────────
function openCancelModal(orderId) {
  document.getElementById('modal-order-id').value = orderId;
  document.getElementById('cancel-reason').value  = '';
  document.getElementById('cancel-modal').style.display = 'flex';
}
function closeCancelModal() {
  document.getElementById('cancel-modal').style.display = 'none';
}
document.getElementById('cancel-modal').addEventListener('click', function(e){
  if (e.target === this) closeCancelModal();
});
</script>
</body></html>