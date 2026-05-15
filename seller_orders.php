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

                // If changing TO cancelled from a non-cancelled status, restore stock
                if ($new_status === 'cancelled' && $prev_status !== 'cancelled') {
                    $items = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id=?");
                    $items->bind_param("i", $oid); $items->execute();
                    $rows = $items->get_result(); $items->close();
                    while ($row = $rows->fetch_assoc()) {
                        $rst = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id=?");
                        $rst->bind_param("ii", $row['quantity'], $row['product_id']);
                        $rst->execute(); $rst->close();
                    }
                    log_activity($conn, $current_user_id, "cancelled_order", "Order #$oid — stock restored");
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
            <th>Status</th><th>Cancel Request</th><th>Date</th><th>Actions</th>
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

            <!-- Cancel request info -->
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
                <?php if ($o['status'] !== 'cancelled'): ?>
                <form method="POST" style="flex-direction:row;gap:6px;display:flex">
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
                <a href="order_detail.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-ghost">View</a>
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