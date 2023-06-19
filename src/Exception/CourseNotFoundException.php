<?php

namespace App\Exception;

use Exception;
use Throwable;

class CourseNotFoundException extends Exception
{
    public function __construct($message = null, $code = 0, Throwable $previous = null)
    {
        $message = $message ?? 'Курс не найден';
        parent::__construct($message, $code, $previous);
    }
}