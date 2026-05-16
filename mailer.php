<?php
// ============================================================
//  MAILER CONFIG
//  Fill in your Gmail address and App Password below.
//  How to get an App Password:
//    1. Go to myaccount.google.com
//    2. Security → 2-Step Verification (must be ON)
//    3. Search "App passwords" → create one → copy the 16-char password
// ============================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

define('MAIL_FROM',     'dael.keceleunice@gmail.com');   // ← your Gmail
define('MAIL_PASSWORD', 'geby eyix yztw mwjx');    // ← your 16-char App Password
define('MAIL_NAME',     'ShopBlue');
define('SITE_URL',      'http://localhost/SirNikkoCrud'); // ← your project URL

/**
 * Send an email using PHPMailer + Gmail SMTP.
 * Returns true on success, error string on failure.
 */
function send_mail($to_email, $to_name, $subject, $html_body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_FROM;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom(MAIL_FROM, MAIL_NAME);
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = email_template($subject, $html_body);
        $mail->AltBody = strip_tags($html_body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        return $mail->ErrorInfo;
    }
}

/**
 * Wraps content in a dark-themed HTML email template.
 */
function email_template($title, $content) {
    return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { margin:0; padding:0; background:#050d1a; font-family: Arial, sans-serif; }
  .wrap { max-width:520px; margin:40px auto; background:#0c1426; border:1px solid rgba(61,139,255,0.3); border-radius:16px; overflow:hidden; }
  .header { background:linear-gradient(135deg,#1a6cff,#0f4fd4); padding:28px 32px; text-align:center; }
  .header h1 { color:#fff; margin:0; font-size:1.3rem; letter-spacing:0.05em; }
  .body { padding:32px; color:#e8f0ff; line-height:1.6; }
  .body p { margin:0 0 16px; color:#a0b4cc; font-size:0.95rem; }
  .btn { display:inline-block; background:linear-gradient(135deg,#1a6cff,#0f4fd4); color:#fff; padding:13px 28px; border-radius:8px; text-decoration:none; font-weight:600; font-size:0.95rem; margin:8px 0; }
  .code { background:#050d1a; border:1px solid rgba(61,139,255,0.3); border-radius:8px; padding:16px; font-family:monospace; font-size:1.1rem; color:#3d8bff; text-align:center; letter-spacing:0.1em; margin:16px 0; }
  .footer { padding:18px 32px; text-align:center; color:#3d5a80; font-size:0.78rem; border-top:1px solid rgba(61,139,255,0.1); }
</style>
</head>
<body>
<div class="wrap">
  <div class="header"><h1>⬡ ShopBlue</h1></div>
  <div class="body">' . $content . '</div>
  <div class="footer">This email was sent by ShopBlue. If you did not request this, ignore it.</div>
</div>
</body>
</html>';
}
?>