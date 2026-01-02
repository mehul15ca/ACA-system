<?php
declare(strict_types=1);

namespace ACA\Api\Core\Database;

use ACA\Api\Core\DB as CoreDB;

/*
|--------------------------------------------------------------------------
| Backward-compatibility alias
|--------------------------------------------------------------------------
| This allows legacy references to:
|   ACA\Api\Core\Database\DB
| while the real implementation lives in:
|   ACA\Api\Core\DB
|
| Do NOT extend — DB is final.
*/

class_alias(CoreDB::class, __NAMESPACE__ . '\\DB');
