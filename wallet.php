<?php
include 'auth.php';
include 'db.php';
if ($current_role !== 'buyer') { header("Location: seller_dashboard.php"); exit(); }

$msg = $msgtype = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deposit') {
    $amount = floatval($_POST['amount']);
    if ($amount <= 0) {
        $msg = "Please enter a valid amount to deposit."; $msgtype = 'error';
    } elseif (empty($_FILES['proof']['name'])) {
        $msg = "Please upload proof of payment."; $msgtype = 'error';
    } else {
        $allowed  = ['image/jpeg','image/png','image/webp','image/gif'];
        $max_size = 2 * 1024 * 1024;
        if (!in_array($_FILES['proof']['type'], $allowed)) {
            $msg = "Only JPG, PNG, WEBP, or GIF files are allowed."; $msgtype = 'error';
        } elseif ($_FILES['proof']['size'] > $max_size) {
            $msg = "Proof image must be under 2MB."; $msgtype = 'error';
        } else {
            $upload_dir = 'uploads/wallet/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext = pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION);
            $filename = 'wallet_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
            if (move_uploaded_file($_FILES['proof']['tmp_name'], $upload_dir . $filename)) {
                $ins = $conn->prepare("INSERT INTO wallet_deposits (user_id, amount, proof) VALUES (?,?,?)");
                $ins->bind_param("ids", $current_user_id, $amount, $filename);
                $ins->execute();
                $ins->close();
                log_activity($conn, $current_user_id, "wallet_deposit_requested", "Deposit request ₱$amount");
                $msg = "Deposit request submitted. An admin will approve it soon.";
                $msgtype = 'success';
            } else {
                $msg = "Failed to upload proof. Please try again."; $msgtype = 'error';
            }
        }
    }
}

$wallet = $conn->prepare("SELECT wallet_balance FROM users WHERE id=?");
$wallet->bind_param("i", $current_user_id);
$wallet->execute();
$wallet_balance = $wallet->get_result()->fetch_assoc()['wallet_balance'] ?? 0;
$wallet->close();

$req = $conn->prepare("SELECT * FROM wallet_deposits WHERE user_id=? ORDER BY created_at DESC");
$req->bind_param("i", $current_user_id);
$req->execute();
$requests = $req->get_result();
$req->close();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Wallet – ShopBlue</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php include 'nav.php'; ?>
<div class="page" style="max-width:780px">
  <div class="page-header"><h1>💰 Wallet</h1></div>
  <?php if ($msg): ?><div class="msg msg-<?= $msgtype ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
      <div>
        <div class="help">Current Wallet Balance</div>
        <div style="font-size:2rem;font-weight:700;color:var(--blue-hi)">&#8369;<?= number_format($wallet_balance, 2) ?></div>
      </div>
      <div style="text-align:right">
        <div class="help">Deposit funds with GCash</div>
        <div style="font-size:.9rem;color:var(--muted)">Upload your proof and wait for admin approval.</div>
      </div>
    </div>
  </div>

  <div class="card" style="margin-bottom:18px">
    <h3>Request Wallet Deposit</h3>
    <form method="POST" enctype="multipart/form-data" style="gap:14px">
      <input type="hidden" name="action" value="deposit">
      <div class="field"><label>Amount</label>
        <input type="number" step="0.01" min="1" name="amount" placeholder="₱0.00" required value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
      </div>
      <div class="field"><label>GCash Payment Proof</label>
        <input type="file" name="proof" accept="image/*" required>
        <span class="help">Upload your payment screenshot. Max 2MB.</span>
      </div>
      <button type="submit" class="btn">Submit Deposit Request</button>
    </form>
  </div>

  <div class="card">
    <h3>Deposit Requests</h3>
    <?php if ($requests->num_rows === 0): ?>
      <div class="empty">No deposit requests yet.</div>
    <?php else: ?>
      <table>
        <thead><tr><th>Amount</th><th>Status</th><th>Submitted</th><th>Proof</th></tr></thead>
        <tbody>
          <?php while ($row = $requests->fetch_assoc()): ?>
            <tr>
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
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
</body></html>