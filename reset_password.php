<?php
session_start();
include 'db.php';

$token = trim($_GET['token'] ?? '');
$msg   = $msgtype = '';

// Validate token
$stmt = $conn->prepare("SELECT id, username FROM users WHERE reset_token=? AND reset_token_expiry > UTC_TIMESTAMP()");
$stmt->bind_param("s", $token);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$token || !$user) {
    $invalid = true;
}

// Handle new password submission
if (!isset($invalid) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm  = $_POST['confirm'];

    if (!preg_match('/^(?=.*[A-Z]).{8,}$/', $password)) {
        $msg = "Password needs 8+ characters and at least 1 uppercase letter."; $msgtype = 'error';
    } elseif ($password !== $confirm) {
        $msg = "Passwords do not match."; $msgtype = 'error';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $upd  = $conn->prepare("UPDATE users SET password=?, reset_token=NULL, reset_token_expiry=NULL WHERE id=?");
        $upd->bind_param("si", $hash, $user['id']);
        $upd->execute(); $upd->close();

        log_activity($conn, $user['id'], "password_reset", "Password changed via reset link");

        header("Location: login.php?reset=1"); exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Password – ShopBlue</title><link rel="stylesheet" href="style.css"></head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-title">🔒 Reset Password</div>

    <?php if (isset($invalid)): ?>
      <!-- Invalid or expired token -->
      <div class="msg msg-error">
        This reset link is <strong>invalid or has expired</strong>. Reset links are only valid for 1 hour.
      </div>
      <div style="text-align:center;margin-top:16px">
        <a href="forgot_password.php" class="btn btn-full">Request a New Link</a>
      </div>

    <?php else: ?>

      <p style="text-align:center;color:var(--muted);font-size:.88rem;margin-bottom:20px">
        Hi <strong style="color:var(--text)"><?= htmlspecialchars($user['username']) ?></strong>,
        enter your new password below.
      </p>

      <?php if ($msg): ?>
        <div class="msg msg-<?= $msgtype ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="field">
          <label>New Password</label>
          <div class="pw-box">
            <input type="password" name="password" id="pw" placeholder="Min 8 chars, 1 uppercase" required>
            <span class="pw-toggle" onclick="togglePw('pw')">👁</span>
          </div>
          <span class="help" id="pw-help"></span>
        </div>
        <div class="field">
          <label>Confirm New Password</label>
          <div class="pw-box">
            <input type="password" name="confirm" id="pw2" placeholder="Repeat your password" required>
            <span class="pw-toggle" onclick="togglePw('pw2')">👁</span>
          </div>
          <span class="help" id="match-help"></span>
        </div>
        <button type="submit" class="btn btn-full" style="margin-top:4px">Set New Password</button>
      </form>

      <div style="text-align:center;margin-top:16px">
        <a href="login.php" class="lnk" style="font-size:.85rem">← Back to Login</a>
      </div>

    <?php endif; ?>
  </div>
</div>
<script>
function togglePw(id){const i=document.getElementById(id);i.type=i.type==='password'?'text':'password';}

document.getElementById('pw').addEventListener('input',function(){
  const v=this.value,h=document.getElementById('pw-help'),m=[];
  if(v.length<8)m.push('8+ chars');if(!/[A-Z]/.test(v))m.push('1 uppercase');
  h.textContent=m.length?'Need: '+m.join(' & '):'✓ Looks good!';
  h.style.color=m.length?'#ff3b5c':'#00e5a0';
});

function checkMatch(){
  const p=document.getElementById('pw').value;
  const c=document.getElementById('pw2').value;
  const h=document.getElementById('match-help');
  if(!c)return;
  h.textContent = p===c ? '✓ Passwords match' : '✗ Passwords do not match';
  h.style.color = p===c ? '#00e5a0' : '#ff3b5c';
}
document.getElementById('pw').addEventListener('input', checkMatch);
document.getElementById('pw2').addEventListener('input', checkMatch);
</script>
</body></html>