<?php
include 'auth.php';
include 'db.php';
if ($current_role !== 'admin') { header("Location: login.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];
    $notes = trim($_POST['notes'] ?? '');
    $reviewed_by = $current_user_id;
    $reviewed_at = gmdate('Y-m-d H:i:s');

    if ($action === 'approve_deposit') {
        $stmt = $conn->prepare("SELECT user_id, amount FROM wallet_deposits WHERE id=? AND status='pending'");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $upd = $conn->prepare("UPDATE wallet_deposits SET status='approved', notes=?, reviewed_by=?, reviewed_at=? WHERE id=?");
            $upd->bind_param("sisi", $notes, $reviewed_by, $reviewed_at, $request_id);
            $upd->execute();
            $upd->close();
            $wallet_upd = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?");
            $wallet_upd->bind_param("di", $row['amount'], $row['user_id']);
            $wallet_upd->execute();
            $wallet_upd->close();
            log_activity($conn, $current_user_id, "approved_deposit", "Deposit request #$request_id approved");
        }
    }
    if ($action === 'reject_deposit') {
        $upd = $conn->prepare("UPDATE wallet_deposits SET status='rejected', notes=?, reviewed_by=?, reviewed_at=? WHERE id=?");
        $upd->bind_param("sisi", $notes, $reviewed_by, $reviewed_at, $request_id);
        $upd->execute();
        $upd->close();
        log_activity($conn, $current_user_id, "rejected_deposit", "Deposit request #$request_id rejected");
    }

    if ($action === 'approve_withdrawal') {
        $stmt = $conn->prepare("SELECT seller_id, amount FROM withdrawal_requests WHERE id=? AND status='pending'");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $upd = $conn->prepare("UPDATE withdrawal_requests SET status='approved', notes=?, reviewed_by=?, reviewed_at=? WHERE id=?");
            $upd->bind_param("sisi", $notes, $reviewed_by, $reviewed_at, $request_id);
            $upd->execute();
            $upd->close();
            $wallet_upd = $conn->prepare("UPDATE users SET seller_balance = seller_balance - ? WHERE id=?");
            $wallet_upd->bind_param("di", $row['amount'], $row['seller_id']);
            $wallet_upd->execute();
            $wallet_upd->close();
            log_activity($conn, $current_user_id, "approved_withdrawal", "Withdrawal request #$request_id approved");
        }
    }
    if ($action === 'reject_withdrawal') {
        $upd = $conn->prepare("UPDATE withdrawal_requests SET status='rejected', notes=?, reviewed_by=?, reviewed_at=? WHERE id=?");
        $upd->bind_param("sisi", $notes, $reviewed_by, $reviewed_at, $request_id);
        $upd->execute();
        $upd->close();
        log_activity($conn, $current_user_id, "rejected_withdrawal", "Withdrawal request #$request_id rejected");
    }
    header("Location: admin_requests.php"); exit();
}

$deposits = $conn->query("SELECT wd.*, u.username FROM wallet_deposits wd JOIN users u ON u.id=wd.user_id ORDER BY wd.created_at DESC");
$withdrawals = $conn->query("SELECT wr.*, u.username FROM withdrawal_requests wr JOIN users u ON u.id=wr.seller_id ORDER BY wr.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Requests – ShopBlue</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php include 'nav.php'; ?>
<div class="page" style="max-width:960px">
  <div class="page-header"><h1>🛠 Admin Requests</h1></div>

  <div class="card" style="margin-bottom:18px">
    <h3>Pending Wallet Deposits</h3>
    <?php if ($deposits->num_rows === 0): ?>
      <div class="empty">No wallet deposit requests.</div>
    <?php else: ?>
      <table>
        <thead><tr><th>ID</th><th>Buyer</th><th>Amount</th><th>Status</th><th>Submitted</th><th>Proof</th><th>Actions</th></tr></thead>
        <tbody>
          <?php while ($row = $deposits->fetch_assoc()): ?>
            <tr>
              <td>#<?= $row['id'] ?></td>
              <td><?= htmlspecialchars($row['username']) ?></td>
              <td>&#8369;<?= number_format($row['amount'], 2) ?></td>
              <td><span class="badge badge-<?= $row['status'] === 'approved' ? 'delivered' : ($row['status'] === 'rejected' ? 'cancelled' : 'pending') ?>"><?= ucfirst($row['status']) ?></span></td>
              <td style="font-size:.85rem;color:var(--muted);white-space:nowrap"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
              <td>
                <?php if ($row['proof'] && file_exists('uploads/wallet/' . $row['proof'])): ?>
                  <a href="uploads/wallet/<?= htmlspecialchars($row['proof']) ?>" target="_blank" class="lnk">View</a>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td>
                <?php if ($row['status'] === 'pending'): ?>
                  <form method="POST" style="display:inline-flex;gap:5px;flex-wrap:wrap">
                    <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                    <input type="hidden" name="action" value="approve_deposit">
                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                  </form>
                  <form method="POST" style="display:inline-flex;gap:5px;flex-wrap:wrap">
                    <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                    <input type="hidden" name="action" value="reject_deposit">
                    <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                  </form>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Pending Seller Withdrawals</h3>
    <?php if ($withdrawals->num_rows === 0): ?>
      <div class="empty">No withdrawal requests.</div>
    <?php else: ?>
      <table>
        <thead><tr><th>ID</th><th>Seller</th><th>Amount</th><th>Bank</th><th>Status</th><th>Submitted</th><th>Actions</th></tr></thead>
        <tbody>
          <?php while ($row = $withdrawals->fetch_assoc()): ?>
            <tr>
              <td>#<?= $row['id'] ?></td>
              <td><?= htmlspecialchars($row['username']) ?></td>
              <td>&#8369;<?= number_format($row['amount'], 2) ?></td>
              <td style="font-size:.85rem;color:var(--muted)"><?= htmlspecialchars($row['bank_name'] . ' • ' . $row['account_number']) ?></td>
              <td><span class="badge badge-<?= $row['status'] === 'approved' ? 'delivered' : ($row['status'] === 'rejected' ? 'cancelled' : 'pending') ?>"><?= ucfirst($row['status']) ?></span></td>
              <td style="font-size:.85rem;color:var(--muted);white-space:nowrap"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
              <td>
                <?php if ($row['status'] === 'pending'): ?>
                  <form method="POST" style="display:inline-flex;gap:5px;flex-wrap:wrap">
                    <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                    <input type="hidden" name="action" value="approve_withdrawal">
                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                  </form>
                  <form method="POST" style="display:inline-flex;gap:5px;flex-wrap:wrap">
                    <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                    <input type="hidden" name="action" value="reject_withdrawal">
                    <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                  </form>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
</body></html>