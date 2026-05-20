<?php
include 'auth.php';
include 'db.php';
if ($current_role !== 'seller') { header("Location: shop.php"); exit(); }

// ── HANDLE STATUS UPDATE ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $oid = intval($_POST['order_id']);

    // Verify seller owns items in this order
    $verify = $conn->prepare("SELECT id FROM order_items WHERE order_id=? AND seller_id=?");
    $verify->bind_param("ii", $oid, $current_user_id);
    $verify->execute(); $verify->store_result();
    $owns = $verify->num_rows > 0;
    $verify->close();

    if ($owns) {
        if ($_POST['action'] === 'update_status') {
            $allowed = ['pending','processing','shipped','delivered','cancelled'];
            $new_status = in_array($_POST['status'], $allowed) ? $_POST['status'] : 'pending';

            // Check previous status before updating
            $prev = $conn->prepare("SELECT status FROM orders WHERE id=?");
            $prev->bind_param("i", $oid); $prev->execute();
            $prev_status = $prev->get_result()->fetch_assoc()['status'];
            $prev->close();

            $conn->begin_transaction();
            try {
                $upd = $conn->prepare("UPDATE orders SET status=? WHERE id=?");
                $upd->bind_param("si", $new_status, $oid);
                $upd->execute(); $upd->close();

                // If changing TO cancelled from a non-cancelled status, restore stock and refund wallet if needed
                if ($new_status === 'cancelled' && $prev_status !== 'cancelled') {
                    $orderInfo = $conn->prepare("SELECT buyer_id, payment_method, payment_status, total_amount FROM orders WHERE id=?");
                    $orderInfo->bind_param("i", $oid); $orderInfo->execute();
                    $orderData = $orderInfo->get_result()->fetch_assoc();
                    $orderInfo->close();

                    $items = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id=?");
                    $items->bind_param("i", $oid); $items->execute();
                    $rows = $items->get_result(); $items->close();
                    while ($row = $rows->fetch_assoc()) {
                        $rst = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id=?");
                        $rst->bind_param("ii", $row['quantity'], $row['product_id']);
                        $rst->execute(); $rst->close();
                    }
                    if ((empty($orderData['payment_method']) || $orderData['payment_method'] === 'wallet') && $orderData['payment_status'] === 'paid') {
                        $refund = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?");
                        $refund->bind_param("di", $orderData['total_amount'], $orderData['buyer_id']);
                        $refund->execute(); $refund->close();

                        $refund_status = $conn->prepare("UPDATE orders SET payment_status='refunded' WHERE id=?");
                        $refund_status->bind_param("i", $oid);
                        $refund_status->execute();
                        $refund_status->close();
                        log_activity($conn, $current_user_id, "refund_buyer", "Order #$oid cancelled and refunded to buyer wallet");
                    }
                    log_activity($conn, $current_user_id, "cancelled_order", "Order #$oid — stock restored");
                } elseif ($new_status === 'delivered' && $prev_status !== 'delivered') {
                    $seller_income = 0;
                    $items = $conn->prepare("SELECT quantity, price FROM order_items WHERE order_id=? AND seller_id=?");
                    $items->bind_param("ii", $oid, $current_user_id);
                    $items->execute();
                    $rows = $items->get_result(); $items->close();
                    while ($row = $rows->fetch_assoc()) {
                        $seller_income += $row['quantity'] * $row['price'];
                    }
                    if ($seller_income > 0) {
                        $pay = $conn->prepare("UPDATE users SET seller_balance = seller_balance + ? WHERE id=?");
                        $pay->bind_param("di", $seller_income, $current_user_id);
                        $pay->execute(); $pay->close();
                    }
                    log_activity($conn, $current_user_id, "updated_order_status", "Order #$oid → $new_status");
                } else {
                    log_activity($conn, $current_user_id, "updated_order_status", "Order #$oid → $new_status");
                }
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
            }
            header("Location: seller_orders.php?updated=1"); exit();

        } elseif ($_POST['action'] === 'approve_cancel') {
            // Approve: set order to cancelled, restore stock
            $conn->begin_transaction();
            try {
                $upd = $conn->prepare("UPDATE orders SET status='cancelled', cancel_status='approved' WHERE id=?");
                $upd->bind_param("i", $oid); $upd->execute(); $upd->close();

                // Restore stock for each item
                $items = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id=?");
                $items->bind_param("i", $oid); $items->execute();
                $rows = $items->get_result(); $items->close();
                while ($row = $rows->fetch_assoc()) {
                    $rst = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id=?");
                    $rst->bind_param("ii", $row['quantity'], $row['product_id']);
                    $rst->execute(); $rst->close();
                }
                $orderInfo = $conn->prepare("SELECT buyer_id, payment_method, payment_status, total_amount FROM orders WHERE id=?");
                $orderInfo->bind_param("i", $oid); $orderInfo->execute();
                $orderData = $orderInfo->get_result()->fetch_assoc();
                $orderInfo->close();
                if ((empty($orderData['payment_method']) || $orderData['payment_method'] === 'wallet') && $orderData['payment_status'] === 'paid') {
                    $refund = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?");
                    $refund->bind_param("di", $orderData['total_amount'], $orderData['buyer_id']);
                    $refund->execute(); $refund->close();

                    $refund_status = $conn->prepare("UPDATE orders SET payment_status='refunded' WHERE id=?");
                    $refund_status->bind_param("i", $oid);
                    $refund_status->execute();
                    $refund_status->close();
                    log_activity($conn, $current_user_id, "refund_buyer", "Order #$oid cancelled and refunded to buyer wallet");
                }
                $conn->commit();
                log_activity($conn, $current_user_id, "approved_cancellation", "Order #$oid cancelled & stock restored");
            } catch (Exception $e) {
                $conn->rollback();
            }
            header("Location: seller_orders.php?updated=1"); exit();

        } elseif ($_POST['action'] === 'reject_cancel') {
            $upd = $conn->prepare("UPDATE orders SET cancel_status='rejected', cancel_requested=0 WHERE id=?");
            $upd->bind_param("i", $oid); $upd->execute(); $upd->close();
            log_activity($conn, $current_user_id, "rejected_cancellation", "Order #$oid");
            header("Location: seller_orders.php?updated=1"); exit();

        } elseif ($_POST['action'] === 'approve_payment') {
            $upd = $conn->prepare("UPDATE orders SET payment_status='paid', status='processing' WHERE id=?");
            $upd->bind_param("i", $oid); $upd->execute(); $upd->close();
            log_activity($conn, $current_user_id, "approved_payment", "Approved online payment for Order #$oid");
            header("Location: seller_orders.php?updated=1"); exit();

        } elseif ($_POST['action'] === 'reject_payment') {
            $conn->begin_transaction();
            try {
                $upd = $conn->prepare("UPDATE orders SET status='cancelled', payment_status='unpaid' WHERE id=?");
                $upd->bind_param("i", $oid); $upd->execute(); $upd->close();

                // Restore stock
                $items = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id=?");
                $items->bind_param("i", $oid); $items->execute();
                $rows = $items->get_result(); $items->close();
                while ($row = $rows->fetch_assoc()) {
                    $rst = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id=?");
                    $rst->bind_param("ii", $row['quantity'], $row['product_id']);
                    $rst->execute(); $rst->close();
                }
                $conn->commit();
                log_activity($conn, $current_user_id, "rejected_payment", "Rejected online payment for Order #$oid — stock restored");
            } catch (Exception $e) {
                $conn->rollback();
            }
            header("Location: seller_orders.php?updated=1"); exit();
        }
    }
}

// ── FILTERS ──────────────────────────────────────────────────
$filter      = $_GET['status']  ?? 'all';
$cancel_only = isset($_GET['cancel_requests']);
$where_parts = ["1=1"];
if ($filter !== 'all') $where_parts[] = "o.status='" . $conn->real_escape_string($filter) . "'";
if ($cancel_only)      $where_parts[] = "o.cancel_status='pending'";
$where = implode(' AND ', $where_parts);

$stmt = $conn->prepare("
    SELECT o.id, o.status, o.total_amount, o.created_at,
           o.cancel_requested, o.cancellation_reason, o.cancel_status,
           o.payment_method, o.payment_status, o.payment_proof,
           u.username AS buyer,
           GROUP_CONCAT(p.name ORDER BY oi.id SEPARATOR ', ') AS items
    FROM orders o
    JOIN order_items oi ON oi.order_id=o.id AND oi.seller_id=?
    JOIN products p     ON p.id=oi.product_id
    JOIN users u        ON u.id=o.buyer_id
    WHERE $where
    GROUP BY o.id
    ORDER BY o.cancel_requested DESC, o.created_at DESC
");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();

// Count pending cancel requests for badge
$pending_cancels = $conn->query("
    SELECT COUNT(DISTINCT o.id) FROM orders o
    JOIN order_items oi ON oi.order_id=o.id AND oi.seller_id=$current_user_id
    WHERE o.cancel_status='pending'
")->fetch_row()[0];

$statuses = ['all','pending','processing','shipped','delivered','cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Orders – ShopBlue</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php include 'nav.php'; ?>
<div class="page">
  <div class="page-header"><h1>📋 Customer Orders</h1></div>

  <?php if (isset($_GET['updated'])): ?>
    <div class="msg msg-success">✓ Order updated successfully.</div>
  <?php endif; ?>

  <!-- Filter tabs -->
  <div class="gap" style="margin-bottom:16px;flex-wrap:wrap">
    <?php foreach ($statuses as $s): ?>
      <a href="?status=<?= $s ?>" class="<?= (!$cancel_only && $filter===$s) ? 'btn btn-sm' : 'btn-ghost btn-sm' ?>"><?= ucfirst($s) ?></a>
    <?php endforeach; ?>
    <a href="?cancel_requests=1" class="<?= $cancel_only ? 'btn btn-sm btn-danger' : 'btn-ghost btn-sm' ?>"
       style="<?= $pending_cancels > 0 ? 'border-color:var(--danger)' : '' ?>">
      🚨 Cancel Requests <?= $pending_cancels > 0 ? "($pending_cancels)" : '' ?>
    </a>
  </div>

  <div class="card">
    <?php if ($orders->num_rows === 0): ?>
      <div class="empty">No orders found.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Order #</th><th>Buyer</th><th>Items</th><th>Total</th>
            <th>Status</th><th>Payment</th><th>Cancel Request</th><th>Date</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($o = $orders->fetch_assoc()): ?>
          <tr <?= $o['cancel_status']==='pending' ? 'style="outline:1px solid rgba(255,59,92,.35)"' : '' ?>>
            <td>#<?= $o['id'] ?></td>
            <td><?= htmlspecialchars($o['buyer']) ?></td>
            <td style="color:var(--muted)"><?= htmlspecialchars(mb_strimwidth($o['items'],0,35,'…')) ?></td>
            <td style="color:var(--blue-hi)">&#8369;<?= number_format($o['total_amount'],2) ?></td>
            <td><span class="badge badge-<?= $o['status'] ?>"><?= $o['status'] ?></span></td>

            <!-- Payment -->
            <td>
              <?php
                $pm = $o['payment_method'] ?? 'wallet';
                $ps = $o['payment_status'] ?? 'unpaid';
              ?>
              <div style="font-size:.78rem;color:var(--muted);margin-bottom:4px">
                <?= $pm === 'gcash' ? '📱 GCash' : '🟢 Wallet' ?>
              </div>
                  <?php if ($ps === 'paid'): ?>
                <span class="badge badge-delivered">✅ Paid</span>
              <?php elseif ($ps === 'refunded'): ?>
                <span class="badge" style="background:rgba(255,159,67,.1);color:#ff9f43;border:1px solid rgba(255,159,67,.3)">↩️ Refunded</span>
              <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:5px">
                  <span class="badge badge-pending">⏳ Unpaid</span>
                </div>
              <?php endif; ?>
              
              <?php if ($pm === 'gcash' && !empty($o['payment_proof'])): ?>
                <a href="uploads/payments/<?= htmlspecialchars($o['payment_proof']) ?>" target="_blank"
                   style="display:inline-flex;align-items:center;gap:4px;font-size:.75rem;color:var(--blue-hi);margin-top:6px;text-decoration:underline">
                  📄 View Receipt ↗
                </a>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($o['cancel_status'] === 'pending'): ?>
                <div style="max-width:180px">
                  <div style="font-size:.75rem;color:var(--danger);font-weight:600;margin-bottom:4px">⚠ Cancellation Requested</div>
                  <div style="font-size:.78rem;color:var(--muted);font-style:italic;margin-bottom:8px;word-break:break-word">
                    "<?= htmlspecialchars(mb_strimwidth($o['cancellation_reason'],0,80,'…')) ?>"
                  </div>
                  <div class="gap" style="gap:6px">
                    <form method="POST" style="display:inline;flex-direction:unset">
                      <input type="hidden" name="action" value="approve_cancel">
                      <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-success"
                        onclick="return confirm('Approve cancellation? Stock will be restored.')">✓ Approve</button>
                    </form>
                    <form method="POST" style="display:inline;flex-direction:unset">
                      <input type="hidden" name="action" value="reject_cancel">
                      <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-danger">✗ Reject</button>
                    </form>
                  </div>
                </div>
              <?php elseif ($o['cancel_status'] === 'approved'): ?>
                <span class="badge badge-cancelled">Approved</span>
              <?php elseif ($o['cancel_status'] === 'rejected'): ?>
                <span class="badge" style="background:rgba(255,159,67,.1);color:#ff9f43;border:1px solid rgba(255,159,67,.35)">Rejected</span>
              <?php else: ?>
                <span style="color:var(--dim);font-size:.8rem">—</span>
              <?php endif; ?>
            </td>

            <td style="font-size:.78rem;color:var(--muted);white-space:nowrap"><?= date('M d, Y', strtotime($o['created_at'])) ?></td>

            <!-- Status update + detail -->
            <td>
              <div style="display:flex;flex-direction:column;gap:7px">
                <?php
                // Only handle online payment verification (e.g. GCash) now that COD is removed
                $is_pending_gcash = ($o['payment_method'] === 'gcash' && $o['payment_status'] === 'unpaid' && $o['status'] !== 'cancelled');
                ?>

                <?php if ($is_pending_gcash): ?>
                  <div style="border:1px dashed var(--blue-border);padding:8px;border-radius:var(--r-sm);background:rgba(26,108,255,.04);display:flex;flex-direction:column;gap:6px">
                    <span style="font-size:.72rem;color:var(--muted);font-weight:600;text-transform:uppercase">Payment Verification</span>
                    <div style="display:flex;gap:4px">
                      <form method="POST" style="display:inline;flex-direction:unset" onsubmit="return confirm('⚠️ Approve this payment?\n\nThis will confirm the payment and move the order to processing.');">
                        <input type="hidden" name="action" value="approve_payment">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-success" style="padding:4px 8px">Approve</button>
                      </form>
                      <form method="POST" style="display:inline;flex-direction:unset" onsubmit="return confirm('⚠️ Reject this online payment?\n\nThis will cancel the order and restore stock immediately.');">
                        <input type="hidden" name="action" value="reject_payment">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" style="padding:4px 8px">Reject</button>
                      </form>
                    </div>
                  </div>
                <?php elseif ($o['status'] !== 'cancelled' && $o['status'] !== 'delivered'): ?>
                  <form method="POST" style="flex-direction:row;gap:6px;display:flex" onsubmit="return confirmStatusChange(this);">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                    <select name="status" style="padding:5px 8px;font-size:.78rem;width:auto">
                      <?php foreach (['pending','processing','shipped','delivered','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm">Save</button>
                  </form>
                <?php endif; ?>
                <a href="order_detail.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-ghost">View Details</a>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
<script>
function confirmStatusChange(form) {
  const select = form.querySelector('select[name="status"]');
  if (!select) return true;
  const val = select.value;
  if (val === 'delivered') {
    return confirm("⚠️ Are you sure you want to mark this order as DELIVERED?\n\nThis action is final and CANNOT BE UNDONE!");
  } else if (val === 'cancelled') {
    return confirm("⚠️ Are you sure you want to CANCEL this order?\n\nThis action will restore stock and CANNOT BE UNDONE!");
  }
  return true;
}
</script>
</body></html>