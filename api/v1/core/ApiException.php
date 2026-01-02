<?php
declare(strict_types=1);

namespace ACA\Api\Core;

use Throwable;

final class ApiException extends \Exception
{
    public function toResponse(): void
    {
        Response::error(
            $this->getMessage(),
            $this->getCode() ?: 400,
            'API_EXCEPTION'
        );
    }
}
