<?php

namespace App\Exceptions;

use Exception;

class InvalidStateTransitionException extends Exception
{
    public function __construct(string $message = 'Invalid state transition')
    {
        parent::__construct($message);
    }
}
