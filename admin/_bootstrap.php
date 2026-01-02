<?php
// admin/_bootstrap.php

declare(strict_types=1);

// IMPORTANT: update path if your structure differs.
require_once __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/security/csrf.php';
require_once __DIR__ . '/../includes/security/admin_guard.php';

// 1) Require login + admin/staff role
AdminGuard::requireRole(['admin', 'staff']);

// 2) Enforce CSRF on any POST/PUT/PATCH/DELETE in admin
Csrf::validateRequest();
