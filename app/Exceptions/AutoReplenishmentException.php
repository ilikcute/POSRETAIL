<?php

namespace App\Exceptions;

use Exception;

class AutoReplenishmentException extends Exception
{
    public function __construct(string $message = 'Auto replenishment operation failed.', int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
