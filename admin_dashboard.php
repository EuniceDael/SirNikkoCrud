<?php
include 'auth.php';
include 'db.php';
if ($current_role !== 'admin') { header("Location: login.php"); exit(); }

$pending_deposits = $conn->query("SELECT COUNT(*) FROM wallet_deposits WHERE status='pending'")->fetch_row()[0];
$pending_withdrawals = $conn->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status='pending'")->fetch_row()[0];
$total_buyers = $conn->query("SELECT COUNT(*) FROM users WHERE role='buyer'")->fetch_row()[0];
$total_sellers = $conn->query("SELECT COUNT(*) FROM users WHERE role='seller'")->fetch_row()[0];
$total_users = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];

$activity_stmt = $conn->prepare(
    "SELECT al.action, al.details, al.created_at, u.username
     FROM activity_logs al
     JOIN users u ON u.id = al.user_id
     ORDER BY al.created_at DESC
     LIMIT 5"
);
$activity_stmt->execute();
$recent_activity = $activity_stmt->get_result();
$activity_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard – ShopBlue</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php include 'nav.php'; ?>
<div class="page" style="max-width:960px">
  <div class="page-header">
    <h1>🛡️ Admin Dashboard</h1>
    <a href="admin_requests.php" class="btn">Review Requests</a>
  </div>

  <div class="grid-4" style="gap:16px;margin-bottom:18px">
    <div class="card">
      <div class="help">Pending Deposits</div>
      <div style="font-size:2rem;font-weight:700;color:var(--blue-hi)">&#8369;<?= $pending_deposits ?></div>
    </div>
    <div class="card">
      <div class="help">Pending Withdrawals</div>
      <div style="font-size:2rem;font-weight:700;color:var(--blue-hi)"><?= $pending_withdrawals ?></div>
    </div>
    <div class="card">
      <div class="help">Buyers</div>
      <div style="font-size:2rem;font-weight:700;color:var(--blue-hi)"><?= $total_buyers ?></div>
    </div>
    <div class="card">
      <div class="help">Sellers</div>
      <div style="font-size:2rem;font-weight:700;color:var(--blue-hi)"><?= $total_sellers ?></div>
    </div>
  </div>

  <div class="card" style="margin-bottom:18px">
    <h3>System Summary</h3>
    <div style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap">
      <div><strong>Total users</strong><div><?= $total_users ?></div></div>
      <div><strong>Pending approvals</strong><div><?= $pending_deposits + $pending_withdrawals ?></div></div>
    </div>
  </div>

  <div class="card">
    <h3>Recent Activity</h3>
    <?php if ($recent_activity->num_rows === 0): ?>
      <div class="empty">No recent activity yet.</div>
    <?php else: ?>
      <table>
        <thead><tr><th>User</th><th>Action</th><th>Details</th><th>Date</th></tr></thead>
        <tbody>
          <?php while ($row = $recent_activity->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['username']) ?></td>
              <td><?= htmlspecialchars($row['action']) ?></td>
              <td><?= htmlspecialchars($row['details']) ?></td>
              <td style="font-size:.85rem;color:var(--muted);white-space:nowrap"><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
</body></html>
