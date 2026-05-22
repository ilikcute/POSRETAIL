<?php

namespace App\Exceptions;

use Exception;

class LoyaltyBalanceException extends Exception
{
    public function __construct(
        string $message,
        private readonly array $contextData = []
    ) {
        parent::__construct($message);
    }

    public function contextData(): array
    {
        return $this->contextData;
    }
}
