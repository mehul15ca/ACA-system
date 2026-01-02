<?php
declare(strict_types=1);

require "../config.php";
checkLogin();

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    exit("Missing user session.");
}

/* ---------------- CSRF ---------------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf_store'])) {
        $_SESSION['csrf_store'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_store'];
}
function csrf_verify(?string $token): void {
    if (!$token || empty($_SESSION['csrf_store']) || !hash_equals($_SESSION['csrf_store'], $token)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

/* ---------------- Add to Cart ---------------- */
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_to_cart') {
    csrf_verify($_POST['csrf'] ?? null);

    $product_id = (int)($_POST['product_id'] ?? 0);
    $qty        = max(1, (int)($_POST['qty'] ?? 1));
    $size       = (string)($_POST['size'] ?? 'M');

    $size_whitelist = ['S','M','L','XL'];
    if (!in_array($size, $size_whitelist, true)) {
        $size = 'M';
    }

    if ($product_id <= 0) {
        $message = "Invalid product.";
    } else {
        // Verify product exists & active
        $stmtP = $conn->prepare("
            SELECT id 
            FROM store_products 
            WHERE id = ? AND active = 1
            LIMIT 1
        ");
        $stmtP->bind_param("i", $product_id);
        $stmtP->execute();
        $prod = $stmtP->get_result()->fetch_assoc();

        if (!$prod) {
            $message = "Product not found or inactive.";
        } else {
            // Check existing cart row
            $stmtC = $conn->prepare("
                SELECT id, qty
                FROM store_cart_items
                WHERE user_id = ?
                  AND product_id = ?
                  AND size = ?
                LIMIT 1
            ");
            $stmtC->bind_param("iis", $userId, $product_id, $size);
            $stmtC->execute();
            $existing = $stmtC->get_result()->fetch_assoc();

            if ($existing) {
                // Update qty
                $newQty = (int)$existing['qty'] + $qty;
                $stmtU = $conn->prepare("
                    UPDATE store_cart_items
                    SET qty = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmtU->bind_param("iii", $newQty, $existing['id'], $userId);
                $stmtU->execute();
            } else {
                // Insert new row
                $stmtI = $conn->prepare("
                    INSERT INTO store_cart_items (user_id, product_id, size, qty)
                    VALUES (?, ?, ?, ?)
                ");
                $stmtI->bind_param("iisi", $userId, $product_id, $size, $qty);
                $stmtI->execute();
            }

            $message = "Item added to cart.";
        }
    }
}

/* ---------------- Load Products ---------------- */
$productsRes = $conn->query("
    SELECT id, name, base_price, description, main_image_drive_id
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
        body{margin:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#020617;color:#e5e7eb}
        .wrap{max-width:1100px;margin:0 auto;padding:16px}
        h1{font-size:24px;margin:4px 0 2px}
        .sub{font-size:13px;color:#9ca3af;margin-bottom:16px}
        .message{margin-bottom:10px;padding:8px 10px;border-radius:8px;font-size:13px}
        .message.ok{background:#022c22;color:#bbf7d0}
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px}
        .card{background:#020817;border-radius:16px;border:1px solid #1f2937;padding:10px;display:flex;flex-direction:column;gap:8px}
        .thumb{width:100%;aspect-ratio:4/3;border-radius:12px;overflow:hidden;background:#0f172a;display:flex;align-items:center;justify-content:center}
        .thumb img{width:100%;height:100%;object-fit:cover}
        .pname{font-size:14px;font-weight:600}
        .pprice{font-size:13px;color:#a5b4fc}
        select,input[type=number]{background:#020617;border-radius:999px;border:1px solid #1f2937;color:#e5e7eb;padding:4px 8px;font-size:12px}
        .row{display:flex;gap:6px;align-items:center;margin-top:4px}
        button{border:none;border-radius:999px;padding:6px 10px;font-size:12px;cursor:pointer;background:#22c55e;color:#021014}
        .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;font-size:13px}
        .cart-link a{color:#facc15;text-decoration:none}
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
        <div class="message ok"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="grid">
        <?php if ($productsRes && $productsRes->num_rows): ?>
            <?php while ($p = $productsRes->fetch_assoc()): ?>
                <div class="card">
                    <div class="thumb">
                        <?php if ($p['main_image_drive_id']): ?>
                            <img src="https://drive.google.com/uc?export=view&id=<?php echo urlencode($p['main_image_drive_id']); ?>" alt="">
                        <?php else: ?>
                            <span style="font-size:11px;color:#4b5563;">No image</span>
                        <?php endif; ?>
                    </div>

                    <div class="pname"><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="pprice">$<?php echo number_format((float)$p['base_price'], 2); ?> CAD</div>

                    <div style="font-size:12px;color:#9ca3af;max-height:40px;overflow:hidden;">
                        <?php
                        $desc = (string)($p['description'] ?? '');
                        echo htmlspecialchars(mb_substr($desc, 0, 80), ENT_QUOTES, 'UTF-8');
                        if (mb_strlen($desc) > 80) echo "...";
                        ?>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="add_to_cart">
                        <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">

                        <div class="row">
                            <label style="font-size:11px;">Size</label>
                            <select name="size">
                                <option>S</option>
                                <option selected>M</option>
                                <option>L</option>
                                <option>XL</option>
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
