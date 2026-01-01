<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
if ($userId <= 0) {
    die("Missing user session.");
}

// Handle Add to Cart
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='add_to_cart') {
    $product_id = intval($_POST['product_id']);
    $size = $_POST['size'] ?? 'M';
    $qty = intval($_POST['qty'] ?? 1);
    if ($qty < 1) $qty = 1;

    $size_whitelist = ['S','M','L','XL'];
    if (!in_array($size, $size_whitelist)) {
        $size = 'M';
    }

    // Check product exists and active
    $stmtP = $conn->prepare("SELECT id FROM store_products WHERE id = ? AND active = 1");
    $stmtP->bind_param("i", $product_id);
    $stmtP->execute();
    $prod = $stmtP->get_result()->fetch_assoc();
    if (!$prod) {
        $message = "Product not found or inactive.";
    } else {
        // Insert or update cart row
        $userIdInt = intval($userId);
        $sizeEsc = $conn->real_escape_string($size);
        // Check existing
        $sqlCheck = "
            SELECT id, qty FROM store_cart_items
            WHERE user_id = {$userIdInt}
              AND product_id = {$product_id}
              AND size = '{$sizeEsc}'
        ";
        $resCheck = $conn->query($sqlCheck);
        if ($resCheck && $row = $resCheck->fetch_assoc()) {
            $newQty = intval($row['qty']) + $qty;
            $conn->query("
                UPDATE store_cart_items
                SET qty = {$newQty}
                WHERE id = ".intval($row['id'])."
            ");
        } else {
            $conn->query("
                INSERT INTO store_cart_items (user_id, product_id, size, qty)
                VALUES ({$userIdInt}, {$product_id}, '{$sizeEsc}', {$qty})
            ");
        }
        $message = "Item added to cart.";
    }
}

// Load products
$productsRes = $conn->query("
    SELECT *
    FROM store_products
    WHERE active = 1
    ORDER BY created_at DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ACA Store - Merchandise</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin:0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:#020617;
            color:#e5e7eb;
        }
        .wrap {
            max-width:1100px;
            margin:0 auto;
            padding:16px;
        }
        h1 {
            font-size:24px;
            margin:4px 0 2px;
        }
        .sub {
            font-size:13px;
            color:#9ca3af;
            margin-bottom:16px;
        }
        .message {
            margin-bottom:10px;
            padding:8px 10px;
            border-radius:8px;
            font-size:13px;
        }
        .message.ok {
            background:#022c22;
            color:#bbf7d0;
        }
        .grid {
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(220px,1fr));
            gap:16px;
        }
        .card {
            background:#020817;
            border-radius:16px;
            border:1px solid #1f2937;
            padding:10px;
            display:flex;
            flex-direction:column;
            gap:8px;
        }
        .thumb {
            width:100%;
            aspect-ratio:4/3;
            border-radius:12px;
            overflow:hidden;
            background:#0f172a;
            display:flex;
            align-items:center;
            justify-content:center;
        }
        .thumb img {
            width:100%;
            height:100%;
            object-fit:cover;
            display:block;
        }
        .pname {
            font-size:14px;
            font-weight:600;
        }
        .pprice {
            font-size:13px;
            color:#a5b4fc;
        }
        form {
            margin:0;
        }
        select, input[type=number] {
            background:#020617;
            border-radius:999px;
            border:1px solid #1f2937;
            color:#e5e7eb;
            padding:4px 8px;
            font-size:12px;
        }
        .row {
            display:flex;
            gap:6px;
            align-items:center;
            margin-top:4px;
        }
        button {
            border:none;
            border-radius:999px;
            padding:6px 10px;
            font-size:12px;
            cursor:pointer;
            background:#22c55e;
            color:#021014;
        }
        .topbar {
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:12px;
            font-size:13px;
        }
        .cart-link a {
            color:#facc15;
            text-decoration:none;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <div>
            <h1>ACA Store</h1>
            <div class="sub">Official Australasia Cricket Academy Merchandise</div>
        </div>
        <div class="cart-link">
            <a href="cart.php">🛒 View Cart</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message ok"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="grid">
        <?php if ($productsRes && $productsRes->num_rows > 0): ?>
            <?php while ($p = $productsRes->fetch_assoc()): ?>
                <div class="card">
                    <div class="thumb">
                        <?php if (!empty($p['main_image_drive_id'])): ?>
                            <img src="https://drive.google.com/uc?export=view&id=<?php echo urlencode($p['main_image_drive_id']); ?>" alt="">
                        <?php else: ?>
                            <span style="font-size:11px;color:#4b5563;">No image</span>
                        <?php endif; ?>
                    </div>
                    <div class="pname"><?php echo htmlspecialchars($p['name']); ?></div>
                    <div class="pprice">$<?php echo number_format($p['base_price'], 2); ?> CAD</div>
                    <div style="font-size:12px;color:#9ca3af;max-height:40px;overflow:hidden;">
                        <?php echo htmlspecialchars(mb_substr($p['description'],0,80)); ?><?php if (mb_strlen($p['description'])>80) echo "..."; ?>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_to_cart">
                        <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                        <div class="row">
                            <label style="font-size:11px;">Size</label>
                            <select name="size">
                                <option value="S">S</option>
                                <option value="M" selected>M</option>
                                <option value="L">L</option>
                                <option value="XL">XL</option>
                            </select>
                            <label style="font-size:11px;">Qty</label>
                            <input type="number" name="qty" value="1" min="1" style="width:60px;">
                        </div>
                        <div class="row" style="margin-top:8px;">
                            <button type="submit">Add to Cart</button>
                        </div>
                    </form>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="font-size:13px;color:#9ca3af;">No products available yet.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
