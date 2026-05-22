<?php

namespace App\Exceptions;

use Exception;

class DailyCloseException extends Exception
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
