<?php

namespace App\Exceptions;

use RuntimeException;

class MockInsurerTimeoutException extends RuntimeException
{
    public function __construct(string $message = 'Connection timed out while awaiting insurer response.')
    {
        parent::__construct($message, 504);
    }
}
