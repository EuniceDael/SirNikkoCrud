<?php
// simulate_userflow.php
// CLI script to simulate: create buyer/seller, deposit, place order (wallet), seller cancel + refund
if (PHP_SAPI !== 'cli') { echo "Run from CLI only.\n"; exit(1); }
require_once 'db.php';

function ensure_user($conn, $email, $username, $role) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
    $stmt->bind_param("s", $email); $stmt->execute(); $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { $stmt->close(); return $row['id']; }
    $stmt->close();

    $password = password_hash('password123', PASSWORD_DEFAULT);
    $wallet = 0.00; $seller_bal = 0.00; $phone = '09123456789'; $address = 'Test Address';
    $ins = $conn->prepare("INSERT INTO users (username,email,password,role,wallet_balance,seller_balance,phone,address) VALUES (?,?,?,?,?,?,?,?)");
    $ins->bind_param('sssddsss', $username, $email, $password, $role, $wallet, $seller_bal, $phone, $address);
    $ins->execute(); $id = $conn->insert_id; $ins->close();
    return $id;
}

function ensure_product($conn, $seller_id, $name, $price, $stock) {
    $stmt = $conn->prepare("SELECT id,price,stock FROM products WHERE seller_id=? AND name=?");
    $stmt->bind_param('is', $seller_id, $name); $stmt->execute(); $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { $stmt->close(); return $row['id']; }
    $stmt->close();
    $desc = 'Simulated product for userflow test'; $img = NULL;
    $ins = $conn->prepare("INSERT INTO products (seller_id,name,description,price,stock,image,created_at) VALUES (?,?,?,?,?,?,NOW())");
    $ins->bind_param('issdis', $seller_id, $name, $desc, $price, $stock, $img);
    $ins->execute(); $pid = $conn->insert_id; $ins->close();
    return $pid;
}

echo "Simulating userflow...\n";

// 1) Ensure test buyer & seller
$buyer_email = 'sim_buyer@example.com';
$seller_email = 'sim_seller@example.com';
$buyer_id = ensure_user($conn, $buyer_email, 'sim_buyer', 'buyer');
$seller_id = ensure_user($conn, $seller_email, 'sim_seller', 'seller');

echo "Buyer ID: $buyer_id, Seller ID: $seller_id\n";

// 2) Ensure product
$product_name = 'Sim Product'; $price = 150.00; $stock = 10;
$product_id = ensure_product($conn, $seller_id, $product_name, $price, $stock);
echo "Product ID: $product_id (price=PHP $price)\n";

// 3) Deposit to buyer wallet (simulate)
$deposit = 500.00;
$upd = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?");
$upd->bind_param('di', $deposit, $buyer_id); $upd->execute(); $upd->close();

$bal = $conn->query("SELECT wallet_balance FROM users WHERE id=$buyer_id")->fetch_assoc()['wallet_balance'];
echo "Buyer wallet after deposit: PHP " . number_format($bal,2) . "\n";

// 4) Place order (wallet)
$qty = 2; $total = $price * $qty; $phone = '09123456789'; $address = '123 Simulation St';
$buyer_bal = floatval($conn->query("SELECT wallet_balance FROM users WHERE id=$buyer_id")->fetch_assoc()['wallet_balance']);
if ($buyer_bal < $total) { echo "Insufficient balance to place order.\n"; exit(1); }

$conn->begin_transaction();
try {
    // deduct wallet
    $d = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id=?");
    $d->bind_param('di', $total, $buyer_id); $d->execute(); $d->close();

    // insert order
    $ord = $conn->prepare("INSERT INTO orders (buyer_id,phone,address,total_amount,payment_method,payment_status,payment_proof,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
    $pm = 'wallet'; $ps = 'paid'; $pp = NULL;
    $ord->bind_param('issdsss', $buyer_id, $phone, $address, $total, $pm, $ps, $pp);
    $ord->execute(); $order_id = $conn->insert_id; $ord->close();

    // insert order_items
    $oi = $conn->prepare("INSERT INTO order_items (order_id,product_id,seller_id,quantity,price) VALUES (?,?,?,?,?)");
    $oi->bind_param('iiiid', $order_id, $product_id, $seller_id, $qty, $price); $oi->execute(); $oi->close();

    // reduce product stock
    $st = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id=?");
    $st->bind_param('ii', $qty, $product_id); $st->execute(); $st->close();

    $conn->commit();
    echo "Order placed: ID=$order_id, total=PHP " . number_format($total,2) . "\n";
} catch (Exception $e) {
    $conn->rollback();
    echo "Order failed: " . $e->getMessage() . "\n"; exit(1);
}

$buyer_bal_after = $conn->query("SELECT wallet_balance FROM users WHERE id=$buyer_id")->fetch_assoc()['wallet_balance'];
echo "Buyer wallet after purchase: PHP " . number_format($buyer_bal_after,2) . "\n";
$product_stock_after = $conn->query("SELECT stock FROM products WHERE id=$product_id")->fetch_assoc()['stock'];
echo "Product stock after purchase: $product_stock_after\n";

// 5) Seller cancels order (simulate approve_cancel flow)
$conn->begin_transaction();
try {
    $u = $conn->prepare("UPDATE orders SET status='cancelled', cancel_status='approved' WHERE id=?");
    $u->bind_param('i', $order_id); $u->execute(); $u->close();

    // restore stock
    $items = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id=?");
    $items->bind_param('i', $order_id); $items->execute(); $rows = $items->get_result(); $items->close();
    while ($row = $rows->fetch_assoc()) {
        $r = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id=?");
        $r->bind_param('ii', $row['quantity'], $row['product_id']); $r->execute(); $r->close();
    }

    // refund buyer if paid via wallet
    $orderData = $conn->query("SELECT buyer_id,payment_method,payment_status,total_amount FROM orders WHERE id=$order_id")->fetch_assoc();
    if ((empty($orderData['payment_method']) || $orderData['payment_method'] === 'wallet') && $orderData['payment_status'] === 'paid') {
        $ref = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?");
        $ref->bind_param('di', $orderData['total_amount'], $orderData['buyer_id']); $ref->execute(); $ref->close();

        $rs = $conn->prepare("UPDATE orders SET payment_status='refunded' WHERE id=?"); $rs->bind_param('i', $order_id); $rs->execute(); $rs->close();
    }

    $conn->commit();
    echo "Seller cancelled order $order_id and refund applied if applicable.\n";
} catch (Exception $e) {
    $conn->rollback(); echo "Cancellation failed: " . $e->getMessage() . "\n"; exit(1);
}

$buyer_final = $conn->query("SELECT wallet_balance FROM users WHERE id=$buyer_id")->fetch_assoc()['wallet_balance'];
$prod_final = $conn->query("SELECT stock FROM products WHERE id=$product_id")->fetch_assoc()['stock'];
$order_final = $conn->query("SELECT status,payment_status FROM orders WHERE id=$order_id")->fetch_assoc();

echo "Final buyer wallet: PHP " . number_format($buyer_final,2) . "\n";
echo "Final product stock: $prod_final\n";
echo "Final order status: {$order_final['status']} (payment_status={$order_final['payment_status']})\n";

echo "Simulation complete.\n";
