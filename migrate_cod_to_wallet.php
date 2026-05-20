<?php
require_once 'db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not available.\n");
}

$query = "SELECT COUNT(*) AS cnt FROM orders WHERE payment_method = '' OR payment_method IS NULL";
$result = $conn->query($query);
$row = $result ? $result->fetch_assoc() : null;
$count = $row ? intval($row['cnt']) : 0;

echo "Orders with empty payment_method: $count\n";

if ($count === 0) {
    echo "No rows require migration.\n";
    exit(0);
}

if (PHP_SAPI !== 'cli') {
    echo "This migration script must be run from the command line.\n";
    exit(1);
}

if (in_array($argv[1] ?? '', ['run', 'apply'], true)) {
    echo "Applying migration...\n";
    $conn->begin_transaction();
    try {
        $update = $conn->query("UPDATE orders SET payment_method = 'wallet' WHERE payment_method = '' OR payment_method IS NULL");
        if ($update === false) {
            throw new Exception($conn->error);
        }
        $conn->commit();
        echo "Migration applied successfully. Updated $count rows.\n";
    } catch (Exception $ex) {
        $conn->rollback();
        echo "Migration failed: " . $ex->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "Run this script with: php migrate_cod_to_wallet.php run\n";
    echo "This will update empty/null order payment_method values to 'wallet'.\n";
}
