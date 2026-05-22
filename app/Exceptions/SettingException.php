<?php

namespace App\Exceptions;

use Exception;

class SettingException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param  array<string, mixed>  $contextData
     */
    public function __construct(
        string $message,
        private readonly array $contextData = []
    ) {
        parent::__construct($message);
    }

    /**
     * Get the context data for the exception.
     *
     * @return array<string, mixed>
     */
    public function contextData(): array
    {
        return $this->contextData;
    }
}
