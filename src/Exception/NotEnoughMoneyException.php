<?php

namespace App\Exception;

use Exception;
use Throwable;

class NotEnoughMoneyException extends Exception
{
    public function __construct($message = null, $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}