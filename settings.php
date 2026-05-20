<?php
include 'auth.php';
include 'db.php';

// Fetch current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$msg_profile  = $msgtype_profile  = '';
$msg_password = $msgtype_password = '';

// ── HANDLE PROFILE UPDATE ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $new_username = trim($_POST['username']);
    $new_email    = trim($_POST['email']);
    $new_phone    = trim($_POST['phone']);
    $new_address  = trim($_POST['address']);

    if (strlen($new_username) < 3) {
        $msg_profile = "Username must be at least 3 characters."; $msgtype_profile = 'error';
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $msg_profile = "Please enter a valid email address."; $msgtype_profile = 'error';
    } else {
        // Check if username or email is taken by someone else
        $chk = $conn->prepare("SELECT id FROM users WHERE (username=? OR email=?) AND id != ?");
        $chk->bind_param("ssi", $new_username, $new_email, $current_user_id);
        $chk->execute(); $chk->store_result();

        if ($chk->num_rows > 0) {
            $msg_profile = "Username or email is already taken by another account."; $msgtype_profile = 'error';
        } else {
            $qr_filename = $user['qr_code']; // Keep existing by default
            if ($current_role === 'seller' && !empty($_FILES['qr_code']['name'])) {
                $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
                $max_size = 2 * 1024 * 1024; // 2MB

                if (!in_array($_FILES['qr_code']['type'], $allowed)) {
                    $msg_profile = "Only JPG, PNG, GIF, or WEBP images are allowed for the QR Code.";
                    $msgtype_profile = 'error';
                } elseif ($_FILES['qr_code']['size'] > $max_size) {
                    $msg_profile = "QR Code image must be under 2MB.";
                    $msgtype_profile = 'error';
                } else {
                    $upload_dir = 'uploads/qrcodes/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $ext = pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION);
                    $new_filename = 'qr_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                    if (move_uploaded_file($_FILES['qr_code']['tmp_name'], $upload_dir . $new_filename)) {
                        // Delete old QR code file if it exists
                        if ($user['qr_code'] && file_exists($upload_dir . $user['qr_code'])) {
                            @unlink($upload_dir . $user['qr_code']);
                        }
                        $qr_filename = $new_filename;
                    } else {
                        $msg_profile = "Failed to upload QR code. Check folder permissions.";
                        $msgtype_profile = 'error';
                    }
                }
            }

            if (!$msg_profile || $msgtype_profile !== 'error') {
                $bank_name = trim($_POST['bank_name'] ?? '');
                $account_name = trim($_POST['account_name'] ?? '');
                $account_number = trim($_POST['account_number'] ?? '');
                $upd = $conn->prepare("UPDATE users SET username=?, email=?, phone=?, address=?, qr_code=?, bank_name=?, account_name=?, account_number=? WHERE id=?");
                $upd->bind_param("ssssssssi", $new_username, $new_email, $new_phone, $new_address, $qr_filename, $bank_name, $account_name, $account_number, $current_user_id);
                $upd->execute(); $upd->close();

                // Update session username
                $_SESSION['username'] = $new_username;
                $current_username     = $new_username;

                log_activity($conn, $current_user_id, "updated_profile", "Profile info updated");

                // Refresh user data
                $stmt2 = $conn->prepare("SELECT * FROM users WHERE id=?");
                $stmt2->bind_param("i", $current_user_id);
                $stmt2->execute();
                $user = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();

                $msg_profile = "✓ Profile updated successfully!"; $msgtype_profile = 'success';
            }
        }
        $chk->close();
    }
}

// ── HANDLE PASSWORD CHANGE ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_pw  = $_POST['current_password'];
    $new_pw      = $_POST['new_password'];
    $confirm_pw  = $_POST['confirm_password'];

    if (!password_verify($current_pw, $user['password'])) {
        $msg_password = "Current password is incorrect."; $msgtype_password = 'error';
    } elseif (!preg_match('/^(?=.*[A-Z]).{8,}$/', $new_pw)) {
        $msg_password = "New password needs 8+ characters and at least 1 uppercase letter."; $msgtype_password = 'error';
    } elseif ($new_pw !== $confirm_pw) {
        $msg_password = "New passwords do not match."; $msgtype_password = 'error';
    } elseif (password_verify($new_pw, $user['password'])) {
        $msg_password = "New password must be different from your current password."; $msgtype_password = 'error';
    } else {
        $hash = password_hash($new_pw, PASSWORD_DEFAULT);
        $upd  = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $upd->bind_param("si", $hash, $current_user_id);
        $upd->execute(); $upd->close();

        log_activity($conn, $current_user_id, "changed_password", "Password changed from settings");
        $msg_password = "✓ Password changed successfully!"; $msgtype_password = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Settings – ShopBlue</title><link rel="stylesheet" href="style.css">
<style>
.settings-grid{display:grid;grid-template-columns:220px 1fr;gap:20px;align-items:start}
.settings-sidebar{background:var(--glass2);border:1px solid var(--blue-border);border-radius:var(--r-lg);overflow:hidden;position:sticky;top:78px}
.sidebar-avatar{width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,var(--blue),#0f4fd4);display:flex;align-items:center;justify-content:center;font-family:'Oxanium',sans-serif;font-size:1.8rem;font-weight:700;color:#fff;margin:0 auto 10px;border:2px solid var(--blue-border2)}
.sidebar-info{padding:20px;text-align:center;border-bottom:1px solid var(--blue-border)}
.sidebar-uname{font-family:'Oxanium',sans-serif;font-size:1rem;font-weight:600}
.sidebar-role{font-size:.78rem;color:var(--muted);margin-top:4px}
.sidebar-joined{font-size:.75rem;color:var(--dim);margin-top:6px}
.sidebar-nav{overflow:hidden}
.sidebar-nav a{display:flex;align-items:center;gap:10px;padding:12px 18px;color:var(--muted);text-decoration:none;font-size:.875rem;transition:var(--t);border-left:3px solid transparent;margin:0;border-radius:0}
.sidebar-nav a:hover{background:rgba(26,108,255,.12);color:var(--text);border-left-color:var(--blue-border2)}
.sidebar-nav a.active{background:rgba(26,108,255,.15);color:var(--blue-hi);border-left-color:var(--blue-hi)}
@media(max-width:640px){.settings-grid{grid-template-columns:1fr}.settings-sidebar{position:static}}
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="page">
  <div class="page-header" style="margin-bottom:20px">
    <h1>⚙️ Settings</h1>
  </div>

  <div class="settings-grid">

    <!-- SIDEBAR -->
    <div class="settings-sidebar">
      <div class="sidebar-info">
        <div class="sidebar-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
        <div class="sidebar-uname"><?= htmlspecialchars($user['username']) ?></div>
        <div class="sidebar-role"><span class="badge badge-<?= $current_role ?>"><?= $current_role ?></span></div>
        <div class="sidebar-joined">Joined <?= date('M Y', strtotime($user['created_at'])) ?></div>
      </div>
      <nav class="sidebar-nav">
        <a href="#profile"  class="active" onclick="switchTab('profile',this)">👤  Profile Info</a>
        <a href="#password" onclick="switchTab('password',this)">🔒  Change Password</a>
      </nav>
    </div>

    <!-- MAIN CONTENT -->
    <div>

      <!-- PROFILE TAB -->
      <div id="tab-profile">
        <div class="card">
          <h2>👤 Profile Information</h2>

          <?php if ($msg_profile): ?>
            <div class="msg msg-<?= $msgtype_profile ?>"><?= htmlspecialchars($msg_profile) ?></div>
          <?php endif; ?>

          <form method="POST" enctype="multipart/form-data" style="gap:14px">
            <input type="hidden" name="action" value="update_profile">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div class="field">
                <label>Username</label>
                <input type="text" name="username" required minlength="3"
                       value="<?= htmlspecialchars($user['username']) ?>">
              </div>
              <div class="field">
                <label>Email Address</label>
                <input type="text" name="email" required
                       value="<?= htmlspecialchars($user['email'] ?? '') ?>">
              </div>
            </div>

            <div class="field">
              <label>Phone Number</label>
              <input type="tel" name="phone" placeholder="e.g. 09123456789"
                     value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
            </div>

            <?php if ($current_role === 'seller'): 
              $qr_path = ($user['qr_code'] && file_exists('uploads/qrcodes/' . $user['qr_code']))
                         ? 'uploads/qrcodes/' . htmlspecialchars($user['qr_code'])
                         : null;
            ?>
              <div class="field">
                <label>Payment QR Code <span style="color:var(--dim)">(optional, max 2MB)</span></label>
                <div style="display:flex;gap:16px;align-items:center;margin-top:4px;flex-wrap:wrap">
                  <div id="qr-preview-box" style="width:100px;height:100px;border:1px dashed var(--blue-border);border-radius:var(--r-sm);background:rgba(26,108,255,.06);display:flex;align-items:center;justify-content:center;overflow:hidden">
                    <?php if ($qr_path): ?>
                      <img src="<?= $qr_path ?>" alt="QR Code" style="width:100%;height:100%;object-fit:cover">
                    <?php else: ?>
                      <span style="font-size:1.5rem;color:var(--dim)">🖼</span>
                    <?php endif; ?>
                  </div>
                  <div style="flex:1;min-width:180px">
                    <input type="file" name="qr_code" accept="image/*" id="qr-input" onchange="previewQr(this)" style="background:rgba(6,14,35,.88);border:1px solid var(--blue-border);border-radius:var(--r-sm);color:var(--text);padding:8px 12px;font-size:.85rem;width:100%">
                    <span class="help">JPG, PNG, WEBP — Scan to pay QR Code for GCash / Online Payments</span>
                  </div>
                </div>
              </div>
              <div class="field">
                <label>Bank Name <span style="color:var(--dim)">(for withdrawals)</span></label>
                <input type="text" name="bank_name" value="<?= htmlspecialchars($user['bank_name'] ?? '') ?>">
              </div>
              <div class="field">
                <label>Account Name</label>
                <input type="text" name="account_name" value="<?= htmlspecialchars($user['account_name'] ?? '') ?>">
              </div>
              <div class="field">
                <label>Account Number</label>
                <input type="text" name="account_number" value="<?= htmlspecialchars($user['account_number'] ?? '') ?>">
              </div>
            <?php endif; ?>

            <div class="field">
              <label>
                <?= $current_role === 'buyer' ? 'Default Delivery Address' : 'Business Address' ?>
              </label>
              <textarea name="address" placeholder="Your full address"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
            </div>

            <div class="field">
              <label>Role</label>
              <input type="text" value="<?= ucfirst($current_role) ?>" disabled
                     style="opacity:.5;cursor:not-allowed">
              <span class="help">Role cannot be changed after registration.</span>
            </div>

            <button type="submit" class="btn" style="margin-top:4px">Save Changes</button>
          </form>
        </div>
      </div>

      <!-- PASSWORD TAB -->
      <div id="tab-password" style="display:none">
        <div class="card">
          <h2>🔒 Change Password</h2>

          <?php if ($msg_password): ?>
            <div class="msg msg-<?= $msgtype_password ?>"><?= htmlspecialchars($msg_password) ?></div>
          <?php endif; ?>

          <form method="POST" style="gap:14px">
            <input type="hidden" name="action" value="change_password">

            <div class="field">
              <label>Current Password</label>
              <div class="pw-box">
                <input type="password" name="current_password" id="pw-current" placeholder="Your current password" required>
                <span class="pw-toggle" onclick="togglePw('pw-current')">👁</span>
              </div>
            </div>

            <div class="field">
              <label>New Password</label>
              <div class="pw-box">
                <input type="password" name="new_password" id="pw-new" placeholder="Min 8 chars, 1 uppercase" required>
                <span class="pw-toggle" onclick="togglePw('pw-new')">👁</span>
              </div>
              <span class="help" id="pw-help"></span>
            </div>

            <div class="field">
              <label>Confirm New Password</label>
              <div class="pw-box">
                <input type="password" name="confirm_password" id="pw-confirm" placeholder="Repeat new password" required>
                <span class="pw-toggle" onclick="togglePw('pw-confirm')">👁</span>
              </div>
              <span class="help" id="match-help"></span>
            </div>

            <button type="submit" class="btn" style="margin-top:4px">Change Password</button>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
// ── TAB SWITCHING ─────────────────────────────────────────────
function switchTab(tab, el) {
  document.getElementById('tab-profile').style.display  = tab === 'profile'  ? 'block' : 'none';
  document.getElementById('tab-password').style.display = tab === 'password' ? 'block' : 'none';
  document.querySelectorAll('.sidebar-nav a').forEach(a => a.classList.remove('active'));
  if (el) el.classList.add('active');
}

// Auto-open password tab if URL has #password
if (window.location.hash === '#password') {
  switchTab('password', document.querySelector('.sidebar-nav a:nth-child(2)'));
}

// ── PASSWORD HELPERS ──────────────────────────────────────────
function togglePw(id) {
  const i = document.getElementById(id);
  i.type = i.type === 'password' ? 'text' : 'password';
}

document.getElementById('pw-new').addEventListener('input', function() {
  const v = this.value, h = document.getElementById('pw-help'), m = [];
  if (v.length < 8) m.push('8+ chars');
  if (!/[A-Z]/.test(v)) m.push('1 uppercase');
  h.textContent = m.length ? 'Need: ' + m.join(' & ') : '✓ Looks good!';
  h.style.color = m.length ? '#ff3b5c' : '#00e5a0';
  checkMatch();
});

function checkMatch() {
  const p = document.getElementById('pw-new').value;
  const c = document.getElementById('pw-confirm').value;
  const h = document.getElementById('match-help');
  if (!c) return;
  h.textContent = p === c ? '✓ Passwords match' : '✗ Passwords do not match';
  h.style.color = p === c ? '#00e5a0' : '#ff3b5c';
}
document.getElementById('pw-confirm').addEventListener('input', checkMatch);

function previewQr(input) {
  const box = document.getElementById('qr-preview-box');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      box.innerHTML = `<img src="${e.target.result}" alt="QR Preview" style="width:100%;height:100%;object-fit:cover">`;
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
</body></html>