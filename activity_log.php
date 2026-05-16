<?php
include 'auth.php';
include 'db.php';

$limit = 10;
$page  = max(1, intval($_GET['page'] ?? 1));
$start = ($page - 1) * $limit;

// Count total logs for this user
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id=?");
$count_stmt->bind_param("i", $current_user_id);
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_row()[0];
$count_stmt->close();

$pages = max(1, ceil($total / $limit));

if ($page > $pages && $total > 0) {
    header("Location: activity_log.php?page=$pages"); exit();
}

// Fetch logs
$stmt = $conn->prepare("
    SELECT al.*, u.username
    FROM activity_logs al
    LEFT JOIN users u ON u.id = al.user_id
    WHERE al.user_id = ?
    ORDER BY al.created_at DESC
    LIMIT ?, ?
");
$stmt->bind_param("iii", $current_user_id, $start, $limit);
$stmt->execute();
$logs = $stmt->get_result();
$stmt->close();

// Icon map for common actions
$action_icons = [
    'login'                  => '🔑',
    'logout'                 => '🚪',
    'registered'             => '✨',
    'placed_order'           => '🛒',
    'requested_cancellation' => '❌',
    'reviewed_product'       => '⭐',
    'added_product'          => '➕',
    'edited_product'         => '✏️',
    'deleted_product'        => '🗑',
    'updated_order_status'   => '📦',
    'approved_cancellation'  => '✅',
    'rejected_cancellation'  => '🚫',
    'cancelled_order'        => '❌',
];
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $current_role === 'buyer' ? 'My Activity' : 'Activity Log' ?> – ShopBlue</title>
<link rel="stylesheet" href="style.css"></head>
<body>
<?php include 'nav.php'; ?>
<div class="page">

  <div class="page-header">
    <h1><?= $current_role === 'buyer' ? '📋 My Activity' : '📝 Activity Log' ?></h1>
    <span style="color:var(--muted);font-size:.85rem">
      <?= $total ?> record<?= $total != 1 ? 's' : '' ?>
    </span>
  </div>

  <!-- Info box for buyers -->
  <?php if ($current_role === 'buyer'): ?>
    <div class="msg msg-info" style="margin-bottom:16px">
      This is a history of all your actions — logins, orders, reviews, and cancellation requests.
    </div>
  <?php endif; ?>

  <div class="card">
    <?php if ($total === 0): ?>
      <div class="empty">No activity recorded yet.</div>
    <?php else: ?>

      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Date &amp; Time</th>
            <th>Action</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $row_num = $start + 1;
          while ($l = $logs->fetch_assoc()):
            $action_key = $l['action'];
            $icon = $action_icons[$action_key] ?? '🔹';
            $label = ucwords(str_replace('_', ' ', $action_key));
          ?>
          <tr>
            <td style="color:var(--dim);font-size:.8rem"><?= $row_num++ ?></td>
            <td style="white-space:nowrap;color:var(--muted);font-size:.8rem">
              <?= date('M d, Y', strtotime($l['created_at'])) ?><br>
              <span style="color:var(--dim)"><?= date('g:i A', strtotime($l['created_at'])) ?></span>
            </td>
            <td>
              <span style="display:inline-flex;align-items:center;gap:6px">
                <span><?= $icon ?></span>
                <span class="badge badge-processing"
                  style="text-transform:none;font-family:'DM Sans',sans-serif;letter-spacing:0">
                  <?= htmlspecialchars($label) ?>
                </span>
              </span>
            </td>
            <td style="color:var(--muted);font-size:.87rem">
              <?= htmlspecialchars($l['details']) ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>

      <!-- PAGINATION -->
      <div class="pagination" style="margin-top:20px">
        <?php if ($page > 1): ?>
          <a href="?page=1" title="First">«</a>
          <a href="?page=<?= $page - 1 ?>" title="Previous">‹</a>
        <?php endif; ?>

        <?php
        $window   = 2;
        $start_pg = max(1, $page - $window);
        $end_pg   = min($pages, $page + $window);
        if ($start_pg > 1): ?>
          <span style="color:var(--dim);padding:0 4px">…</span>
        <?php endif; ?>
        <?php for ($i = $start_pg; $i <= $end_pg; $i++): ?>
          <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($end_pg < $pages): ?>
          <span style="color:var(--dim);padding:0 4px">…</span>
        <?php endif; ?>

        <?php if ($page < $pages): ?>
          <a href="?page=<?= $page + 1 ?>" title="Next">›</a>
          <a href="?page=<?= $pages ?>" title="Last">»</a>
        <?php endif; ?>
      </div>

      <div style="text-align:center;margin-top:10px;font-size:.78rem;color:var(--dim)">
        Page <?= $page ?> of <?= $pages ?>
        &nbsp;·&nbsp;
        Showing <?= $start + 1 ?>–<?= min($start + $limit, $total) ?> of <?= $total ?>
      </div>

    <?php endif; ?>
  </div>
</div>
</body></html>