<?php
// This file is included by every protected page.
// $current_role, $current_username, $current_user_id must already be set (via auth.php).
$page = basename($_SERVER['PHP_SELF']);
?>
<nav>
  <a href="<?= $current_role === 'seller' ? 'seller_dashboard.php' : 'shop.php' ?>" class="nav-brand">⬡ ShopBlue</a>
 
  <div class="nav-links">
    <?php if ($current_role === 'buyer'): ?>
      <a href="shop.php"         <?= $page==='shop.php'         ? 'class="active"' : '' ?>>Shop</a>
      <a href="my_orders.php"    <?= $page==='my_orders.php'    ? 'class="active"' : '' ?>>My Orders</a>
      <a href="activity_log.php" <?= $page==='activity_log.php' ? 'class="active"' : '' ?>>My Activity</a>
    <?php else: ?>
      <a href="seller_dashboard.php" <?= $page==='seller_dashboard.php' ? 'class="active"' : '' ?>>Dashboard</a>
      <a href="my_products.php"      <?= $page==='my_products.php'      ? 'class="active"' : '' ?>>My Products</a>
      <a href="seller_orders.php"    <?= $page==='seller_orders.php'    ? 'class="active"' : '' ?>>Orders</a>
      <a href="activity_log.php"     <?= $page==='activity_log.php'     ? 'class="active"' : '' ?>>Logs</a>
    <?php endif; ?>
  </div>
 
  <div class="nav-right">
    <!-- Settings dropdown -->
    <div class="nav-dropdown" id="nav-dropdown">
      <button class="nav-settings-btn <?= $page==='settings.php' ? 'active' : '' ?>"
              onclick="toggleDropdown()" id="settings-toggle">
        <span class="nav-user"><?= htmlspecialchars($current_username) ?></span>
        <span class="badge badge-<?= $current_role ?>"><?= $current_role ?></span>
        <span class="dropdown-arrow">▾</span>
      </button>
 
      <div class="dropdown-menu" id="dropdown-menu">
        <!-- Profile info header -->
        <div class="dropdown-header">
          <div class="dropdown-avatar"><?= strtoupper(substr($current_username, 0, 1)) ?></div>
          <div>
            <div class="dropdown-uname"><?= htmlspecialchars($current_username) ?></div>
            <div class="dropdown-role"><?= ucfirst($current_role) ?></div>
          </div>
        </div>
        <div class="dropdown-divider"></div>
        <a href="settings.php" class="dropdown-item <?= $page==='settings.php' ? 'dropdown-item-active' : '' ?>">
          ⚙️ <span>Settings</span>
        </a>
        </a>
        <div class="dropdown-divider"></div>
        <a href="logout.php" class="dropdown-item dropdown-item-danger">
          🚪 <span>Logout</span>
        </a>
      </div>
    </div>
  </div>
</nav>
 
<style>
.nav-dropdown{position:relative}
.nav-settings-btn{display:flex;align-items:center;gap:7px;background:transparent;border:1px solid var(--blue-border);border-radius:20px;padding:5px 12px 5px 8px;cursor:pointer;transition:var(--t);color:var(--text)}
.nav-settings-btn:hover,.nav-settings-btn.active{background:var(--blue-soft);border-color:var(--blue-border2)}
.dropdown-arrow{font-size:.7rem;color:var(--muted);transition:transform .2s}
.nav-dropdown.open .dropdown-arrow{transform:rotate(180deg)}
.dropdown-menu{display:none;position:absolute;top:calc(100% + 10px);right:0;min-width:220px;background:var(--glass2);border:1px solid var(--blue-border);border-radius:var(--r-lg);box-shadow:var(--shadow),0 0 20px rgba(26,108,255,.15);overflow:hidden;z-index:999;animation:dropIn .18s ease}
@keyframes dropIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.nav-dropdown.open .dropdown-menu{display:block}
.dropdown-header{display:flex;align-items:center;gap:12px;padding:16px}
.dropdown-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--blue),#0f4fd4);display:flex;align-items:center;justify-content:center;font-family:'Oxanium',sans-serif;font-size:1.1rem;font-weight:700;color:#fff;flex-shrink:0;border:1px solid var(--blue-border2)}
.dropdown-uname{font-weight:600;font-size:.9rem;color:var(--text)}
.dropdown-role{font-size:.75rem;color:var(--muted);margin-top:2px}
.dropdown-divider{height:1px;background:linear-gradient(90deg,transparent,var(--blue-border),transparent);margin:2px 0}
.dropdown-item{display:flex;align-items:center;gap:10px;padding:11px 16px;color:var(--muted);text-decoration:none;font-size:.875rem;transition:var(--t)}
.dropdown-item:hover{background:var(--blue-soft);color:var(--text)}
.dropdown-item-active{color:var(--blue-hi)!important;background:var(--blue-soft)}
.dropdown-item-danger{color:var(--danger)!important}
.dropdown-item-danger:hover{background:var(--danger-soft)!important;color:var(--danger)!important}
</style>
 
<script>
function toggleDropdown() {
  document.getElementById('nav-dropdown').classList.toggle('open');
}
// Close when clicking outside
document.addEventListener('click', function(e) {
  const dd = document.getElementById('nav-dropdown');
  if (dd && !dd.contains(e.target)) dd.classList.remove('open');
});
</script>