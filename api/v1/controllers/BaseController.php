<?php
declare(strict_types=1);

namespace ACA\Api\Controllers;

use ACA\Api\Core\Response;

abstract class BaseController
{
    protected static function ok(array $data = [], string $msg = 'OK'): void
    {
        Response::ok($data, $msg);
    }
}
