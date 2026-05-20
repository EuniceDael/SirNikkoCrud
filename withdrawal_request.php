<?php
include 'auth.php';
include 'db.php';
if ($current_role !== 'seller') { header("Location: shop.php"); exit(); }

$msg = $msgtype = '';

$stmt = $conn->prepare("SELECT wallet_balance, seller_balance, bank_name, account_name, account_number, qr_code FROM users WHERE id=?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'withdraw') {
    $amount = round(floatval($_POST['amount']), 2);
    if ($amount <= 0) {
        $msg = "Please enter a valid withdrawal amount."; $msgtype = 'error';
    } elseif ($amount > floatval($user['seller_balance'])) {
        $msg = "Withdrawal amount cannot exceed your available seller balance."; $msgtype = 'error';
    } else {
        $bank_name = trim($_POST['bank_name']);
        $account_name = trim($_POST['account_name']);
        $account_number = trim($_POST['account_number']);
        if (!$bank_name || !$account_name || !$account_number) {
            $msg = "Please fill in your bank details before requesting a withdrawal."; $msgtype = 'error';
        } else {
            $ins = $conn->prepare("INSERT INTO withdrawal_requests (seller_id, amount, bank_name, account_name, account_number) VALUES (?,?,?,?,?)");
            $ins->bind_param("idsss", $current_user_id, $amount, $bank_name, $account_name, $account_number);
            $ins->execute();
            $ins->close();
            log_activity($conn, $current_user_id, "withdrawal_requested", "Withdrawal request ₱$amount");
            $msg = "Withdrawal request submitted. An admin will review it shortly.";
            $msgtype = 'success';
        }
    }
}

$req = $conn->prepare("SELECT * FROM withdrawal_requests WHERE seller_id=? ORDER BY created_at DESC");
$req->bind_param("i", $current_user_id);
$req->execute();
$requests = $req->get_result();
$req->close();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Withdrawal Request – ShopBlue</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php include 'nav.php'; ?>
<div class="page" style="max-width:780px">
  <div class="page-header"><h1>🏦 Withdrawal Request</h1></div>
  <?php if ($msg): ?><div class="msg msg-<?= $msgtype ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
      <div>
        <div class="help">Available Seller Balance</div>
        <div style="font-size:2rem;font-weight:700;color:var(--blue-hi)">&#8369;<?= number_format($user['seller_balance'], 2) ?></div>
      </div>
      <div style="text-align:right">
        <div class="help">Your payout information</div>
        <div style="font-size:.9rem;color:var(--muted)">Update your QR code and bank details in <a href="settings.php" class="lnk">Settings</a>.</div>
      </div>
    </div>
  </div>

  <div class="card" style="margin-bottom:18px">
    <h3>Request Withdrawal</h3>
    <form method="POST" style="gap:14px">
      <input type="hidden" name="action" value="withdraw">
      <div class="field"><label>Amount</label>
        <input type="number" step="0.01" min="1" name="amount" placeholder="₱0.00" required value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
      </div>
      <div class="field"><label>Bank Name</label>
        <input type="text" name="bank_name" required value="<?= htmlspecialchars($_POST['bank_name'] ?? $user['bank_name'] ?? '') ?>">
      </div>
      <div class="field"><label>Account Name</label>
        <input type="text" name="account_name" required value="<?= htmlspecialchars($_POST['account_name'] ?? $user['account_name'] ?? '') ?>">
      </div>
      <div class="field"><label>Account Number</label>
        <input type="text" name="account_number" required value="<?= htmlspecialchars($_POST['account_number'] ?? $user['account_number'] ?? '') ?>">
      </div>
      <button type="submit" class="btn">Submit Withdrawal Request</button>
    </form>
  </div>

  <div class="card">
    <h3>Your Withdrawal Requests</h3>
    <?php if ($requests->num_rows === 0): ?>
      <div class="empty">No withdrawal requests yet.</div>
    <?php else: ?>
      <table>
        <thead><tr><th>Amount</th><th>Status</th><th>Submitted</th><th>Bank Info</th></tr></thead>
        <tbody>
          <?php while ($row = $requests->fetch_assoc()): ?>
            <tr>
              <td>&#8369;<?= number_format($row['amount'], 2) ?></td>
              <td><span class="badge badge-<?= $row['status'] === 'approved' ? 'delivered' : ($row['status'] === 'rejected' ? 'cancelled' : 'pending') ?>"><?= ucfirst($row['status']) ?></span></td>
              <td style="font-size:.85rem;color:var(--muted);white-space:nowrap"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
              <td style="font-size:.85rem;color:var(--muted)"><?= htmlspecialchars($row['bank_name'] . ' • ' . $row['account_number']) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
</body></html>