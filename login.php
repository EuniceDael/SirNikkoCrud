<?php
session_start();
include 'db.php';

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id,username,password,role FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            log_activity($conn, $user['id'], "login", "Logged in");
            $stmt->close();
            header("Location: " . ($user['role']==='seller' ? 'seller_dashboard.php' : 'shop.php'));
            exit();
        }
    }
    $error = "Invalid username or password.";
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login – ShopBlue</title><link rel="stylesheet" href="style.css"></head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-title">⬡ ShopBlue</div>

    <?php if ($error): ?>
      <div class="msg msg-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label>Username</label>
        <input type="text" name="username" placeholder="Your username" required autofocus>
      </div>
      <div class="field">
        <label>Password</label>
        <div class="pw-box">
          <input type="password" name="password" id="pw" placeholder="Your password" required>
          <span class="pw-toggle" onclick="togglePw('pw',this)">👁</span>
        </div>
      </div>
      <button type="submit" class="btn btn-full" style="margin-top:4px">Sign In</button>
    </form>

    <p style="text-align:center;margin-top:16px;font-size:.85rem;color:var(--muted)">
      No account yet? <a href="register.php" class="lnk">Register</a>
    </p>
  </div>
</div>
<script>function togglePw(id,el){const i=document.getElementById(id);i.type=i.type==='password'?'text':'password';}</script>
</body></html>
