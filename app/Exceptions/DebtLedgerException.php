<?php

namespace App\Exceptions;

use Exception;

class DebtLedgerException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(
        string $message,
        private readonly array $contextData = []
    ) {
        parent::__construct($message);
    }

    /**
     * Get the exception context data.
     */
    public function contextData(): array
    {
        return $this->contextData;
    }
}
