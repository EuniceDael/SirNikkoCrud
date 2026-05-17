<?php
include 'auth.php';
include 'db.php';

$limit = 10;
// Parse directly from URI since $_GET is misconfigured on this server
$uri_query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '';
parse_str($uri_query, $uri_params);
$current_page  = isset($uri_params['page']) && is_numeric($uri_params['page']) ? max(1, intval($uri_params['page'])) : 1;
// Also check POST (for pagination buttons)
if (isset($_POST['pg']) && is_numeric($_POST['pg'])) $current_page = max(1, intval($_POST['pg']));
$start = ($current_page - 1) * $limit;

$total  = $conn->query("SELECT COUNT(*) FROM activity_logs WHERE user_id=$current_user_id")->fetch_row()[0];
$pages  = max(1, ceil($total / $limit));
$result = $conn->query("SELECT al.*, u.username FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id WHERE al.user_id=$current_user_id ORDER BY al.created_at DESC LIMIT $start, $limit");
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $current_role === 'buyer' ? 'My Activity' : 'Activity Log' ?> – ShopBlue</title>
<link rel="stylesheet" href="style.css">
<style>
.pagination-btn{display:inline-flex;align-items:center;justify-content:center;width:33px;height:33px;background:var(--glass2);color:var(--muted);border:1px solid var(--blue-border);border-radius:var(--r-sm);font-size:.83rem;cursor:pointer;transition:var(--t);font-family:'DM Sans',sans-serif}
.pagination-btn:hover{background:var(--blue-soft);color:var(--text);border-color:var(--blue-hi)}
.pagination-btn.active{background:var(--blue);color:#fff;border-color:var(--blue-hi);box-shadow:0 0 14px rgba(26,108,255,.4)}
</style></head>
<body>
<?php include 'nav.php'; ?>
<div class="page">
  <div class="page-header">
    <h1><?= $current_role === 'buyer' ? '📋 My Activity' : '📝 Activity Log' ?></h1>
    <span style="color:var(--muted);font-size:.85rem"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></span>
  </div>
  <div class="card">
    <?php if ($total == 0): ?>
      <div class="empty">No activity recorded yet.</div>
    <?php else: ?>
      <table>
        <thead><tr><th>#</th><th>Date & Time</th><th>Action</th><th>Details</th></tr></thead>
        <tbody>
          <?php $row_num = $start + 1; while ($l = $result->fetch_assoc()): ?>
          <tr>
            <td style="color:var(--dim);font-size:.8rem"><?= $row_num++ ?></td>
            <td style="white-space:nowrap;color:var(--muted);font-size:.8rem"><?= date('M d, Y g:i A', strtotime($l['created_at'])) ?></td>
            <td><span class="badge badge-processing" style="text-transform:none"><?= htmlspecialchars(str_replace('_',' ',$l['action'])) ?></span></td>
            <td style="color:var(--muted)"><?= htmlspecialchars($l['details']) ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>

      <?php if ($pages > 1): ?>
      <div class="pagination" style="margin-top:20px">
        <form method="POST" style="display:inline;flex-direction:unset">
          <input type="hidden" name="pg" value="1">
          <button type="submit" class="pagination-btn" <?= $current_page <= 1 ? 'disabled style="opacity:.3"' : '' ?>>«</button>
        </form>
        <form method="POST" style="display:inline;flex-direction:unset">
          <input type="hidden" name="pg" value="<?= max(1, $current_page - 1) ?>">
          <button type="submit" class="pagination-btn" <?= $current_page <= 1 ? 'disabled style="opacity:.3"' : '' ?>>‹</button>
        </form>
        <span style="color:var(--muted);font-size:.85rem;padding:0 12px">
          Page <?= $current_page ?> of <?= $pages ?>
        </span>
        <form method="POST" style="display:inline;flex-direction:unset">
          <input type="hidden" name="pg" value="<?= min($pages, $current_page + 1) ?>">
          <button type="submit" class="pagination-btn" <?= $current_page >= $pages ? 'disabled style="opacity:.3"' : '' ?>>›</button>
        </form>
        <form method="POST" style="display:inline;flex-direction:unset">
          <input type="hidden" name="pg" value="<?= $pages ?>">
          <button type="submit" class="pagination-btn" <?= $current_page >= $pages ? 'disabled style="opacity:.3"' : '' ?>>»</button>
        </form>
      </div>
      <div style="text-align:center;margin-top:10px;font-size:.78rem;color:var(--dim)">
        Showing <?= $start + 1 ?>–<?= min($start + $limit, $total) ?> of <?= $total ?>
      </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</div>
</body></html>