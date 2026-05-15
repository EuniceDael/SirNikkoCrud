<?php
session_start();
include 'db.php';

$msg = $msgtype = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role     = in_array($_POST['role'], ['buyer','seller']) ? $_POST['role'] : 'buyer';

    if (strlen($username) < 3) {
        $msg = "Username must be at least 3 characters."; $msgtype = "error";
    } elseif (!preg_match('/^(?=.*[A-Z]).{8,}$/', $password)) {
        $msg = "Password needs 8+ characters and at least 1 uppercase letter."; $msgtype = "error";
    } else {
        $chk = $conn->prepare("SELECT id FROM users WHERE username=?");
        $chk->bind_param("s", $username);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $msg = "Username already taken."; $msgtype = "error";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = $conn->prepare("INSERT INTO users (username,password,role) VALUES (?,?,?)");
            $ins->bind_param("sss", $username, $hash, $role);
            $ins->execute();
            $new_id = $conn->insert_id;
            log_activity($conn, $new_id, "registered", "Role: $role");
            $ins->close();
            $msg = "Account created! You can now login."; $msgtype = "success";
        }
        $chk->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Register – ShopBlue</title><link rel="stylesheet" href="style.css"></head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-title">⬡ Create Account</div>

    <?php if ($msg): ?>
      <div class="msg msg-<?= $msgtype ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label>I want to be a…</label>
        <div class="role-grid">
          <label class="role-opt <?= (!isset($_POST['role']) || $_POST['role']==='buyer') ? 'picked' : '' ?>" id="opt-buyer">
            <input type="radio" name="role" value="buyer" checked>
            <span class="role-icon">🛒</span>
            <span class="role-lbl">Buyer</span>
          </label>
          <label class="role-opt <?= (isset($_POST['role']) && $_POST['role']==='seller') ? 'picked' : '' ?>" id="opt-seller">
            <input type="radio" name="role" value="seller">
            <span class="role-icon">🏪</span>
            <span class="role-lbl">Seller</span>
          </label>
        </div>
      </div>

      <div class="field">
        <label>Username</label>
        <input type="text" name="username" placeholder="e.g. juan_dela_cruz" required
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>

      <div class="field">
        <label>Password</label>
        <div class="pw-box">
          <input type="password" name="password" id="pw" placeholder="Min 8 chars, 1 uppercase" required>
          <span class="pw-toggle" onclick="togglePw('pw',this)">👁</span>
        </div>
        <span class="help" id="pw-help"></span>
      </div>

      <button type="submit" class="btn btn-full" style="margin-top:4px">Register</button>
    </form>

    <p style="text-align:center;margin-top:16px;font-size:.85rem;color:var(--muted)">
      Already have an account? <a href="login.php" class="lnk">Login</a>
    </p>
  </div>
</div>
<script>
function togglePw(id,el){const i=document.getElementById(id);i.type=i.type==='password'?'text':'password';}
document.querySelectorAll('.role-opt').forEach(opt=>{
  opt.addEventListener('click',()=>{
    document.querySelectorAll('.role-opt').forEach(o=>o.classList.remove('picked'));
    opt.classList.add('picked');
    opt.querySelector('input').checked=true;
  });
});
document.getElementById('pw').addEventListener('input',function(){
  const v=this.value,h=document.getElementById('pw-help'),m=[];
  if(v.length<8)m.push('8+ chars');if(!/[A-Z]/.test(v))m.push('1 uppercase');
  h.textContent=m.length?'Need: '+m.join(' & '):'✓ Looks good!';
  h.style.color=m.length?'#ff3b5c':'#00e5a0';
});
</script>
</body></html>
