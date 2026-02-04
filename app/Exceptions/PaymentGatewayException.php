<?php

namespace App\Exceptions;

use Exception;

class PaymentGatewayException extends Exception
{
    protected $errors;

    public function __construct(string $message = "", int $code = 0, $errors = [])
    {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}