<?php
include "../config.php";
checkLogin();
$role = currentUserRole();
if ($role !== 'superadmin') {
    http_response_code(403);
    echo "Access denied. Only superadmin can manage store products.";
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $id > 0;

$message = "";
$success = "";
$product = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'base_price' => '0.00',
    'main_image_drive_id' => '',
    'active' => 1,
];

// Load existing product
if ($isEdit) {
    $stmt = $conn->prepare("SELECT * FROM store_products WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if (!$res) {
        die("Product not found.");
    }
    $product = $res;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $slug  = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $base_price  = isset($_POST['base_price']) ? floatval($_POST['base_price']) : 0;
    $image_id    = trim($_POST['main_image_drive_id'] ?? '');
    $active      = isset($_POST['active']) && $_POST['active']=='1' ? 1 : 0;

    if ($name === '') {
        $message = "Name is required.";
    } elseif ($base_price <= 0) {
        $message = "Base price must be greater than zero.";
    } else {
        if ($slug === '') {
            // generate simple slug
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i','-',$name));
        }
        $nameEsc = $conn->real_escape_string($name);
        $slugEsc = $conn->real_escape_string($slug);
        $descEsc = $conn->real_escape_string($description);
        $imgEsc  = $conn->real_escape_string($image_id);
        $price   = $base_price;
        $activeInt = $active ? 1 : 0;

        if ($isEdit) {
            $sql = "
                UPDATE store_products
                SET name='{$nameEsc}',
                    slug='{$slugEsc}',
                    description='{$descEsc}',
                    base_price={$price},
                    main_image_drive_id='{$imgEsc}',
                    active={$activeInt}
                WHERE id={$id}
            ";
            if ($conn->query($sql)) {
                $success = "Product updated.";
            } else {
                $message = "Database error: ".$conn->error;
            }
        } else {
            $sql = "
                INSERT INTO store_products
                    (name, slug, description, base_price, currency, size_options, main_image_drive_id, active)
                VALUES
                    ('{$nameEsc}', '{$slugEsc}', '{$descEsc}', {$price}, 'CAD', 'S,M,L,XL', '{$imgEsc}', {$activeInt})
            ";
            if ($conn->query($sql)) {
                $success = "Product created.";
                $id = $conn->insert_id;
                $isEdit = true;
            } else {
                $message = "Database error: ".$conn->error;
            }
        }
    }

    // reload product data
    if ($isEdit) {
        $stmt = $conn->prepare("SELECT * FROM store_products WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if ($res) $product = $res;
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1><?php echo $isEdit ? "Edit Product" : "Add Product"; ?></h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
        </div>
        <div class="form-group">
            <label>Slug (optional)</label>
            <input type="text" name="slug" value="<?php echo htmlspecialchars($product['slug']); ?>">
            <p style="font-size:11px;color:#9ca3af;margin-top:2px;">Used in future if you want pretty URLs.</p>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="4"><?php echo htmlspecialchars($product['description']); ?></textarea>
        </div>
        <div class="form-group">
            <label>Base Price (CAD)</label>
            <input type="number" step="0.01" min="0" name="base_price"
                   value="<?php echo htmlspecialchars($product['base_price']); ?>" required>
        </div>
        <div class="form-group">
            <label>Main Image Google Drive File ID</label>
            <input type="text" name="main_image_drive_id"
                   value="<?php echo htmlspecialchars($product['main_image_drive_id']); ?>">
            <p style="font-size:11px;color:#9ca3af;margin-top:2px;">
                Image URL preview:
                <?php if (!empty($product['main_image_drive_id'])): ?>
                    https://drive.google.com/uc?export=view&id=<?php echo htmlspecialchars($product['main_image_drive_id']); ?>
                <?php else: ?>
                    (no image set yet)
                <?php endif; ?>
            </p>
        </div>
        <div class="form-group">
            <label>Active</label>
            <label style="font-size:12px;">
                <input type="checkbox" name="active" value="1" <?php if ($product['active']) echo 'checked'; ?>>
                Product visible in store
            </label>
        </div>

        <button type="submit" class="button-primary"><?php echo $isEdit ? "Update" : "Create"; ?> Product</button>
        <a href="store-products.php" class="button">Back to list</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
