ACA System – Merchandise Store Module
=====================================

This module adds a simple merchandise store inside your ACA system.

It supports:
- Products with one main image (Google Drive file ID)
- Fixed size variants: S / M / L / XL
- Unlimited stock (no inventory deduction yet)
- Cart per logged-in user
- Orders with statuses:
    - pending
    - processing
    - in_transit
    - delivered
    - cancelled
- Online payment method placeholder (Stripe to be integrated later)
- Email notifications:
    - to student (or coach/admin user)
    - to superadmin (hardcoded email: mehul15.ca@gmail.com)
- Access:
    - Any logged-in user (students, coaches, admins, superadmin) can see the store

---------------------------------
1) DATABASE – NEW TABLES
---------------------------------

Run these SQL statements in phpMyAdmin on your `aca_system` database.

1.1) Products
-------------

    CREATE TABLE store_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        slug VARCHAR(180) UNIQUE,
        description TEXT,
        base_price DECIMAL(10,2) NOT NULL,
        currency VARCHAR(10) DEFAULT 'CAD',
        size_options VARCHAR(50) DEFAULT 'S,M,L,XL',
        main_image_drive_id VARCHAR(255),
        active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

Notes:
- `size_options` is kept as a simple string "S,M,L,XL" for now.

1.2) Cart Items
----------------

    CREATE TABLE store_cart_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        size ENUM('S','M','L','XL') NOT NULL,
        qty INT NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_cart_user_product_size (user_id, product_id, size),
        CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(id),
        CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES store_products(id)
    );

Notes:
- One row per user/product/size.
- Adding to cart increments qty when the same item already exists.

1.3) Orders
------------

    CREATE TABLE store_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_no VARCHAR(50) UNIQUE,
        user_id INT NOT NULL,
        role ENUM('student','coach','admin','superadmin') NOT NULL,
        student_id INT DEFAULT NULL,
        coach_id INT DEFAULT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        currency VARCHAR(10) DEFAULT 'CAD',
        status ENUM('pending','processing','in_transit','delivered','cancelled') DEFAULT 'pending',
        payment_status ENUM('pending','paid','failed') DEFAULT 'pending',
        payment_method VARCHAR(50) DEFAULT 'online',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(id),
        CONSTRAINT fk_order_student FOREIGN KEY (student_id) REFERENCES students(id),
        CONSTRAINT fk_order_coach FOREIGN KEY (coach_id) REFERENCES coaches(id)
    );

Notes:
- `status` is the order flow:
    pending → processing → in_transit → delivered (or cancelled)
- `payment_status` is kept separate for future Stripe integration.

1.4) Order Items
-----------------

    CREATE TABLE store_order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        size ENUM('S','M','L','XL') NOT NULL,
        qty INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        CONSTRAINT fk_orderitem_order FOREIGN KEY (order_id) REFERENCES store_orders(id),
        CONSTRAINT fk_orderitem_product FOREIGN KEY (product_id) REFERENCES store_products(id)
    );

---------------------------------
2) FILES ADDED
---------------------------------

Public store (any logged-in user):
----------------------------------

store/index.php
    - Product listing (cards)
    - "Add to Cart" button (size + qty)

store/cart.php
    - Shows current user cart
    - Allows updating qty or removing items
    - Shows subtotal
    - Checkout button

store/checkout.php
    - Simple checkout review page
    - Confirm order button (no real payment yet)
    - On submit:
        - Creates store_orders + store_order_items
        - Empties cart
        - Inserts email jobs into notifications_queue for:
            - student (or coach/admin user)
            - superadmin (email: mehul15.ca@gmail.com)
        - Redirects to order-success.php

store/order-success.php
    - Simple "Thank you" page showing order number.

Admin (superadmin full control, admin view-only):
-------------------------------------------------

admin/store-products.php
    - List all products
    - Only superadmin can:
        - Add product
        - Edit product
        - Activate/Deactivate
    - Admin (role='admin') can only view the list.

admin/store-product-edit.php
    - Create or edit a product.
    - Fields:
        - Name
        - Description
        - Base price
        - Google Drive image file ID
        - Active (Yes/No)
    - Only superadmin can access.

admin/store-orders.php
    - List of all orders.
    - Admin & superadmin can view.
    - Columns:
        - Order No
        - Date
        - User / Role
        - Total Amount
        - Status
        - Payment Status
        - Link: View

admin/store-order-view.php
    - Detail view of one order.
    - If user is superadmin:
        - Can update order status:
            pending → processing → in_transit → delivered / cancelled
        - Can also update payment_status (pending/paid/failed).
    - If user is admin:
        - Read-only (cannot change statuses).

---------------------------------
3) ACCESS CONTROL
---------------------------------

- All store/*.php pages:
    - include "../config.php";
    - require checkLogin();
    - This ensures only logged-in users can access the store.

- Admin product pages:
    - store-products.php:
        - admin + superadmin can see
        - Only superadmin sees "Add Product" / "Edit" actions.
    - store-product-edit.php:
        - only superadmin (role check)

- Admin orders pages:
    - store-orders.php:
        - admin + superadmin
    - store-order-view.php:
        - admin + superadmin
        - Only superadmin sees status/payment update form.

---------------------------------
4) EMAIL NOTIFICATIONS
---------------------------------

On successful order creation (checkout):
- The code looks up the user's email:
    - If role='student' and linked student has email, use that.
    - If role='coach' and linked coach has email, use that.
    - Else, uses the email from a simple query if available.
- It then inserts rows into `notifications_queue`:

    - One for the buyer:
        channel = 'email'
        subject = 'Your merchandise order ' . order_no
        message = basic summary

    - One for superadmin:
        channel = 'email'
        receiver_email = 'mehul15.ca@gmail.com'
        subject = 'New merchandise order ' . order_no
        message = basic summary

The actual sending logic (cron / worker that reads notifications_queue and sends) will use your existing notification system when we build it.

---------------------------------
5) NAVIGATION
---------------------------------

Public / user menu (where appropriate in your layout):
    Link: /store/index.php
    Label suggestion: "Store" or "Merchandise"

Admin sidebar (admin/includes/sidebar.php), under a suitable section:
    <li><a href="store-products.php">🛍 Store Products</a></li>
    <li><a href="store-orders.php">📦 Store Orders</a></li>

---------------------------------
6) NOTES / LIMITATIONS
---------------------------------

- Inventory is not tracked yet (unlimited stock).
- Payment is a placeholder:
    - payment_method = 'online'
    - payment_status = 'pending'
  Later we will integrate Stripe and update payment_status accordingly.
- Product images use Google Drive File ID:
    - Frontend image URL format:
      https://drive.google.com/uc?export=view&id=FILE_ID

