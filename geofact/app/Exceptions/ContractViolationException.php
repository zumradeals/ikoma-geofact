<?php

namespace App\Exceptions;

use RuntimeException;

class ContractViolationException extends RuntimeException
{
    public function __construct(string $contract, string $message)
    {
        parent::__construct("[{$contract}] {$message}");
    }
}
