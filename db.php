<?php
// ── DATABASE CONNECTION ──────────────────────────────────────
// Change these values to match your setup.
// Default XAMPP: host=localhost, user=root, pass=(empty), db=shop_db
$conn = new mysqli("localhost", "root", "", "shop_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Auto-migration for seller QR code support
$chk = $conn->query("SHOW COLUMNS FROM users LIKE 'qr_code'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN qr_code VARCHAR(255) DEFAULT NULL");
}

// Auto-migration for GCash/payment fields in orders table
$cols_to_add = [
    // Default to wallet-based flow; COD removed
    'payment_method' => "VARCHAR(50) DEFAULT 'wallet'",
    'payment_status' => "VARCHAR(50) DEFAULT 'unpaid'",
    'payment_proof' => "VARCHAR(255) DEFAULT NULL"
];
foreach ($cols_to_add as $col => $definition) {
    $chk_col = $conn->query("SHOW COLUMNS FROM orders LIKE '$col'");
    if ($chk_col && $chk_col->num_rows === 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN $col $definition");
    }
}

// Auto-migration for wallet and payout support
$cols_to_add = [
    'wallet_balance' => "DECIMAL(12,2) NOT NULL DEFAULT '0.00'",
    'seller_balance' => "DECIMAL(12,2) NOT NULL DEFAULT '0.00'",
    'bank_name' => "VARCHAR(100) DEFAULT NULL",
    'account_name' => "VARCHAR(100) DEFAULT NULL",
    'account_number' => "VARCHAR(100) DEFAULT NULL"
];
foreach ($cols_to_add as $col => $definition) {
    $chk_col = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
    if ($chk_col && $chk_col->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN $col $definition");
    }
}

// Auto-migration for admin role support
$chk_role = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($chk_role && $chk_role->num_rows > 0) {
    $row = $chk_role->fetch_assoc();
    if (strpos($row['Type'], "admin") === false) {
        $conn->query("ALTER TABLE users MODIFY role ENUM('buyer','seller','admin') NOT NULL DEFAULT 'buyer'");
    }
}

// Auto-migration for wallet deposit requests
if (!$conn->query("SHOW TABLES LIKE 'wallet_deposits'")->num_rows) {
    $conn->query(
        "CREATE TABLE wallet_deposits (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            amount decimal(12,2) NOT NULL,
            proof varchar(255) DEFAULT NULL,
            status enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            notes text DEFAULT NULL,
            reviewed_by int(11) DEFAULT NULL,
            reviewed_at datetime DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

// Auto-migration for seller withdrawal requests
if (!$conn->query("SHOW TABLES LIKE 'withdrawal_requests'")->num_rows) {
    $conn->query(
        "CREATE TABLE withdrawal_requests (
            id int(11) NOT NULL AUTO_INCREMENT,
            seller_id int(11) NOT NULL,
            amount decimal(12,2) NOT NULL,
            bank_name varchar(100) DEFAULT NULL,
            account_name varchar(100) DEFAULT NULL,
            account_number varchar(100) DEFAULT NULL,
            status enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            notes text DEFAULT NULL,
            reviewed_by int(11) DEFAULT NULL,
            reviewed_at datetime DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            KEY seller_id (seller_id),
            KEY status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
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
