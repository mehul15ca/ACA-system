# ACA SYSTEM — Cron Jobs (Production Setup)

## 1) Rule: Cron scripts are CLI-only
Every file under `cron/` must start with:

```php
<?php
require_once __DIR__ . '/_bootstrap.php';
