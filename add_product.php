<?php
include 'auth.php';
include 'db.php';
if ($current_role !== 'seller') { header("Location: shop.php"); exit(); }

$msg = $msgtype = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']);
    $desc  = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $image = null;

    if (!$name || $price <= 0 || $stock < 0) {
        $msg = "Please fill all fields correctly."; $msgtype = 'error';
    } else {
        // ── Handle image upload ──────────────────────────────
        if (!empty($_FILES['image']['name'])) {
            $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
            $max_size = 2 * 1024 * 1024; // 2MB

            if (!in_array($_FILES['image']['type'], $allowed)) {
                $msg = "Only JPG, PNG, GIF, or WEBP images are allowed."; $msgtype = 'error';
            } elseif ($_FILES['image']['size'] > $max_size) {
                $msg = "Image must be under 2MB."; $msgtype = 'error';
            } else {
                $upload_dir = 'uploads/products/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                    $image = $filename;
                } else {
                    $msg = "Failed to upload image. Check folder permissions."; $msgtype = 'error';
                }
            }
        }

        if (!$msg) {
            $stmt = $conn->prepare("INSERT INTO products (seller_id, name, description, price, stock, image) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("issdis", $current_user_id, $name, $desc, $price, $stock, $image);
            $stmt->execute();
            $new_id = $conn->insert_id;
            $stmt->close();
            log_activity($conn, $current_user_id, "added_product", "$name (ID $new_id)");
            header("Location: my_products.php?saved=1"); exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Add Product – ShopBlue</title><link rel="stylesheet" href="style.css">
<style>
.img-preview-box{width:100%;height:200px;border:2px dashed var(--blue-border);border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;flex-direction:column;gap:10px;cursor:pointer;transition:var(--t);overflow:hidden;position:relative;background:rgba(6,14,35,.5)}
.img-preview-box:hover{border-color:var(--blue-hi);background:var(--blue-soft)}
.img-preview-box img{width:100%;height:100%;object-fit:cover;position:absolute;inset:0}
.img-preview-box .placeholder-text{color:var(--muted);font-size:.85rem;text-align:center;z-index:1}
.img-preview-box .placeholder-icon{font-size:2rem;z-index:1}
input[type=file]{display:none}
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="page" style="max-width:560px">
  <a href="my_products.php" class="back">&#8592; My Products</a>
  <div class="card">
    <h2>Add Product</h2>
    <?php if ($msg): ?><div class="msg msg-<?= $msgtype ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" style="gap:12px">

      <!-- Image upload -->
      <div class="field">
        <label>Product Image <span style="color:var(--dim)">(optional, max 2MB)</span></label>
        <div class="img-preview-box" onclick="document.getElementById('img-input').click()" id="preview-box">
          <span class="placeholder-icon">🖼</span>
          <span class="placeholder-text">Click to upload image<br><span style="font-size:.75rem;color:var(--dim)">JPG, PNG, WEBP, GIF</span></span>
        </div>
        <input type="file" name="image" id="img-input" accept="image/*" onchange="previewImage(this)">
      </div>

      <div class="field"><label>Product Name</label>
        <input type="text" name="name" placeholder="e.g. Wireless Mouse" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>
      <div class="field"><label>Description</label>
        <textarea name="description" placeholder="Describe your product…"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="field"><label>Price (&#8369;)</label>
          <input type="number" name="price" step="0.01" min="0.01" placeholder="0.00" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
        </div>
        <div class="field"><label>Stock Quantity</label>
          <input type="number" name="stock" min="0" placeholder="0" required value="<?= htmlspecialchars($_POST['stock'] ?? '') ?>">
        </div>
      </div>
      <button type="submit" class="btn btn-full" style="margin-top:4px">Save Product</button>
    </form>
  </div>
</div>
<script>
function previewImage(input) {
  const box = document.getElementById('preview-box');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      box.innerHTML = `<img src="${e.target.result}" alt="preview"><div style="position:absolute;bottom:8px;right:10px;background:rgba(0,0,0,.6);color:#fff;font-size:.75rem;padding:3px 8px;border-radius:4px">Click to change</div>`;
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
</body></html>