<?php

namespace App\Exceptions;

use App\Enums\PolicyStatus;
use Exception;

class InvalidStateTransitionException extends Exception
{
    public function __construct(
        public readonly PolicyStatus $fromState,
        public readonly PolicyStatus $toState,
        string $message = '',
    ) {
        $msg = $message ?: "Invalid state transition from [{$fromState->value}] to [{$toState->value}].";
        parent::__construct($msg, 422);
    }
}
