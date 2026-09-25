<?php

namespace App\Support;

use RuntimeException;

class ApiException extends RuntimeException
{
    public function __construct(
        public readonly string $error,
        public readonly int $status,
    ) {
        parent::__construct($error);
    }
}
