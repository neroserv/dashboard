<?php

namespace App\Exceptions;

use Exception;

class InsufficientBalanceException extends Exception
{
    public function __construct(string $message = 'Das Guthaben reicht für diese Zahlung nicht aus.')
    {
        parent::__construct($message);
    }
}
