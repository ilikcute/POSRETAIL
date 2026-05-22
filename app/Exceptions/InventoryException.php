<?php

namespace App\Exceptions;

use Exception;

class InventoryException extends Exception
{
    /**
     * Create a new inventory exception.
     */
    public function __construct(string $message = "Inventory operation failed.", int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
