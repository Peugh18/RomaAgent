<?php

namespace App\Exceptions;

use RuntimeException;

class GeminiQuotaExceededException extends RuntimeException
{
    public function __construct(
        string $message,
        public int $retryAfterSeconds = 60,
    ) {
        parent::__construct($message);
    }
}
