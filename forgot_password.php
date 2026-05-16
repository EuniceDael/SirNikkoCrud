<?php
session_start();
include 'db.php';
include 'mailer.php';

$msg = $msgtype = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Please enter a valid email address."; $msgtype = 'error';
    } else {
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Always show the same message whether email exists or not (security)
        $msg     = "If that email is registered, a reset link has been sent.";
        $msgtype = 'success';

        if ($user) {
            // Generate secure token, valid for 1 hour
            $token   = bin2hex(random_bytes(32));
            $expiry  = gmdate('Y-m-d H:i:s', strtotime('+1 hour'));

            $upd = $conn->prepare("UPDATE users SET reset_token=?, reset_token_expiry=? WHERE id=?");
            $upd->bind_param("ssi", $token, $expiry, $user['id']);
            $upd->execute(); $upd->close();

            $reset_link = SITE_URL . "/reset_password.php?token=$token";

            $body = "
                <p>Hi <strong style='color:#e8f0ff'>{$user['username']}</strong>,</p>
                <p>We received a request to reset your ShopBlue password.</p>
                <p>Click the button below to set a new password. This link expires in <strong style='color:#3d8bff'>1 hour</strong>.</p>
                <p style='text-align:center;margin:24px 0'>
                  <a href='$reset_link' class='btn'>Reset My Password</a>
                </p>
                <p>Or copy and paste this link into your browser:</p>
                <div class='code'>$reset_link</div>
                <p>If you didn't request a password reset, you can safely ignore this email.</p>
            ";
            send_mail($email, $user['username'], 'Reset Your ShopBlue Password', $body);

            log_activity($conn, $user['id'], "password_reset_requested", "Token sent to $email");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Forgot Password – ShopBlue</title><link rel="stylesheet" href="style.css"></head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-title">🔑 Forgot Password</div>

    <p style="text-align:center;color:var(--muted);font-size:.88rem;margin-bottom:20px">
      Enter your email address and we'll send you a link to reset your password.
    </p>

    <?php if ($msg): ?>
      <div class="msg msg-<?= $msgtype ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if (!$msg): ?>
    <form method="POST">
      <div class="field">
        <label>Email Address</label>
        <input type="text" name="email" placeholder="your@email.com" required autofocus
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-full" style="margin-top:4px">Send Reset Link</button>
    </form>
    <?php endif; ?>

    <div style="text-align:center;margin-top:16px">
      <a href="login.php" class="lnk" style="font-size:.85rem">← Back to Login</a>
    </div>
  </div>
</div>
</body></html>