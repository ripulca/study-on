<?php

namespace App\Exception;

use Exception;
use Throwable;

class CourseValidationException extends Exception
{
    public function __construct($message = null, $code = 0, Throwable $previous = null)
    {
        $message = $message ?? 'Ошибка при создании курса';
        parent::__construct($message, $code, $previous);
    }
}