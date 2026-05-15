<?php
include 'auth.php';
include 'db.php';
if ($current_role !== 'seller') { header("Location: shop.php"); exit(); }

$limit = 30;
$page  = max(1, intval($_GET['page'] ?? 1));
$start = ($page - 1) * $limit;

// Sellers see logs for their own user_id (all their actions) + buyer actions on their products
$total_row = $conn->query("SELECT COUNT(*) FROM activity_logs WHERE user_id=$current_user_id")->fetch_row();
$total     = $total_row[0];
$pages     = ceil($total / $limit);

$stmt = $conn->prepare("SELECT al.*, u.username FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id WHERE al.user_id=? ORDER BY al.created_at DESC LIMIT ?,?");
$stmt->bind_param("iii", $current_user_id, $start, $limit);
$stmt->execute();
$logs = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Activity Log – ShopBlue</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php include 'nav.php'; ?>
<div class="page">
  <div class="page-header"><h1>📝 Activity Log</h1></div>
  <div class="card">
    <?php if ($logs->num_rows === 0): ?>
      <div class="empty">No activity recorded yet.</div>
    <?php else: ?>
      <table>
        <thead><tr><th>Date & Time</th><th>Action</th><th>Details</th></tr></thead>
        <tbody>
          <?php while ($l = $logs->fetch_assoc()): ?>
          <tr>
            <td style="white-space:nowrap;color:var(--muted);font-size:.8rem"><?= date('M d, Y  g:i A', strtotime($l['created_at'])) ?></td>
            <td><span class="badge badge-processing" style="text-transform:none"><?= htmlspecialchars($l['action']) ?></span></td>
            <td style="color:var(--muted)"><?= htmlspecialchars($l['details']) ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
      <?php if ($pages > 1): ?>
        <div class="pagination">
          <?php for ($i=1;$i<=$pages;$i++): ?>
            <a href="?page=<?= $i ?>" class="<?= $i===$page?'active':'' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</body></html>
