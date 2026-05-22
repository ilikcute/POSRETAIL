<?php

namespace App\Exceptions;

use Exception;

class InventoryIntelligenceException extends Exception
{
    public function __construct(string $message = 'Inventory intelligence operation failed.', int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
