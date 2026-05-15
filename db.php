<?php
// ── DATABASE CONNECTION ──────────────────────────────────────
// Change these values to match your setup.
// Default XAMPP: host=localhost, user=root, pass=(empty), db=shop_db
$conn = new mysqli("localhost", "root", "", "shop_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ── HELPER: write to activity_logs ──────────────────────────
function log_activity($conn, $user_id, $action, $details = "") {
    $stmt = $conn->prepare(
        "INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iss", $user_id, $action, $details);
    $stmt->execute();
    $stmt->close();
}
?>
