<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when a request is valid but breaks a business rule (wrong status, conflict, etc.). */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, public int $status = 409, public array $errors = [])
    {
        parent::__construct($message);
    }
}
